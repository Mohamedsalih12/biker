<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use App\Support\IndexNumberColumn;
use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentTable extends PowerGridComponent
{
    use WithExport {
        exportToXLS as private exportWithPowerGridToXls;
        exportToCsv as private;
        prepareToExport as private prepareWithPowerGrid;
    }

    public string $tableName = 'payment-table';

    public string $primaryKey = 'payments.id';

    public ?string $primaryKeyAlias = 'id';

    public string $sortField = 'payments.id';

    private int $exportedRowCount = 0;

    public function boot(): void
    {
        Gate::authorize('manage-payments');
    }

    public function setUp(): array
    {
        $this->showCheckBox();
        $this->persist(['columns'], 'user-'.auth()->id());

        return [
            PowerGrid::header()->showSearchInput()->showToggleColumns()->includeViewOnTop('admin.payments.table-feedback'),
            PowerGrid::footer()->showPerPage(15, [15, 25, 50, 100])->showRecordCount(),
            PowerGrid::exportable('payments')->type(Exportable::TYPE_XLS),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('manage-payments');

        return Payment::query()
            ->join('bookings as paid_bookings', 'paid_bookings.id', '=', 'payments.booking_id')
            ->join('users as customers', 'customers.id', '=', 'payments.customer_id')
            ->join('vehicles as paid_vehicles', 'paid_vehicles.id', '=', 'payments.vehicle_id')
            ->join('users as creators', 'creators.id', '=', 'payments.user_id')
            ->select(['payments.*', 'paid_bookings.booking_code', 'customers.name as customer_name', 'paid_vehicles.registration_number as vehicle_registration', 'creators.name as creator_name']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()->add('id')->add('booking_code')
            ->add('payment_date_formatted', fn (Payment $payment): string => $payment->payment_date?->format('Y-m-d') ?? '')
            ->add('amount_received')
            ->add('payment_method', fn (Payment $payment): string => e(strtoupper($payment->payment_method)))
            ->add('customer_name')->add('vehicle_registration')->add('creator_name')->add('notes')
            ->add('row_actions', fn (Payment $payment): string => view('admin.payments.table-actions', compact('payment'))->render());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id', 'payments.id')->sortable()->visibleInExport(true),
            Column::make('Booking Code', 'booking_code', 'paid_bookings.booking_code')->sortable()->searchable()->visibleInExport(true),
            Column::make('Payment Date', 'payment_date_formatted', 'payments.payment_date')->sortable()->visibleInExport(true),
            IndexNumberColumn::make('Amount Received', 'amount_received', 'payments.amount_received')->sortable()->visibleInExport(true),
            Column::make('Payment Method', 'payment_method', 'payments.payment_method')->sortable()->visibleInExport(true),
            Column::make('Customer', 'customer_name', 'customers.name')->sortable()->searchable()->visibleInExport(true),
            Column::make('Vehicle Registration', 'vehicle_registration', 'paid_vehicles.registration_number')->sortable()->searchable()->visibleInExport(true),
            Column::make('Created By', 'creator_name', 'creators.name')->sortable()->searchable()->visibleInExport(true),
            Column::make('Notes', 'notes', 'payments.notes')->searchable()->bodyAttribute('text-wrap text-break', 'min-width: 15rem; max-width: 24rem;')->visibleInExport(true),
            Column::make('Actions', 'row_actions')->visibleInExport(false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('payment_method', 'payments.payment_method')->dataSource(array_map(
                fn (string $method): array => ['value' => $method, 'label' => strtoupper($method)], Payment::METHODS,
            ))->optionValue('value')->optionLabel('label'),
            Filter::inputText('payment_date_formatted', 'payments.payment_date')->operators(['is'])->placeholder('YYYY-MM-DD'),
        ];
    }

    public function exportToXLS(bool $selected = false): BinaryFileResponse|bool
    {
        Gate::authorize('export', Payment::class);
        if ($selected) {
            $this->selectedPaymentIds();
        }
        data_set($this->setUp, 'exportable.fileName', 'payments-'.Str::uuid());
        try {
            $result = $this->exportWithPowerGridToXls($selected);
            if ($result instanceof BinaryFileResponse) {
                $result->setContentDisposition('attachment', 'payments.xlsx');
                activity('payment')->causedBy(auth()->user())->event('export')
                    ->withProperties(['action' => 'export', 'format' => 'xlsx', 'count' => $this->exportedRowCount])
                    ->log('Payments exported using PowerGrid');
            }

            return $result;
        } finally {
            data_set($this->setUp, 'exportable.fileName', 'payments');
        }
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        Gate::authorize('export', Payment::class);
        if ($selected) {
            $this->selectedPaymentIds();
        }
        $rows = $this->prepareWithPowerGrid($selected);
        $this->exportedRowCount = $rows->count();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'No payments match the current search or selection.']);
        }

        return $rows->map(function (object $row): object {
            foreach (['booking_code', 'customer_name', 'vehicle_registration', 'creator_name', 'notes'] as $field) {
                $value = html_entity_decode((string) $row->{$field}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row->{$field} = e(Spreadsheet::neutralize($value));
            }

            return $row;
        });
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        Gate::authorize('delete', Payment::class);
        $ids = $this->selectedPaymentIds();
        try {
            DB::transaction(function () use ($ids): void {
                $payments = Payment::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
                if ($payments->count() !== count($ids)) {
                    throw ValidationException::withMessages(['ids' => 'The selection has changed. Refresh the table and try again.']);
                }
                foreach ($payments as $payment) {
                    Gate::authorize('delete', $payment);
                    if ($payment->delete() !== true) {
                        throw ValidationException::withMessages(['ids' => 'A selected payment could not be deleted. No payments were deleted.']);
                    }
                }
                activity('payment')->causedBy(auth()->user())->performedOn($payments->first())->event('bulk_delete')
                    ->withProperties(['action' => 'bulk_delete', 'count' => count($ids), 'ids' => $ids])->log('Bulk deleted payments');
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['ids' => 'Selected payments are linked to other records and cannot be deleted. No payments were deleted.']);
        }
        session()->flash('message', count($ids).' payments deleted successfully.');
        $this->redirect(route('xpress.payments.index'));
    }

    /** @return list<int> */
    private function selectedPaymentIds(): array
    {
        $validated = validator(['ids' => $this->checkboxValues], [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:payments,id'],
        ])->validate();

        return array_values(array_map('intval', $validated['ids']));
    }
}
