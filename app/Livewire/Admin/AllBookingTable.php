<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
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

class AllBookingTable extends PowerGridComponent
{
    use WithExport {
        exportToXLS as private exportWithPowerGridToXls;
        exportToCsv as private;
        prepareToExport as private prepareWithPowerGrid;
    }

    public string $tableName = 'booking-table';

    public string $primaryKey = 'bookings.id';

    public ?string $primaryKeyAlias = 'id';

    public string $sortField = 'bookings.id';

    private int $exportedRowCount = 0;

    public function boot(): void
    {
        Gate::authorize('manage-bookings');
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()->showSearchInput()->includeViewOnTop('admin.bookings.table-feedback'),
            PowerGrid::footer()->showPerPage(15, [15, 25, 50, 100])->showRecordCount(),
            PowerGrid::exportable('bookings')->type(Exportable::TYPE_XLS),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('manage-bookings');

        return Booking::query()
            ->join('users as customers', 'customers.id', '=', 'bookings.customer_id')
            ->join('vehicles as booked_vehicles', 'booked_vehicles.id', '=', 'bookings.vehicle_id')
            ->join('users as creators', 'creators.id', '=', 'bookings.user_id')
            ->select(['bookings.*', 'customers.name as customer_name', 'booked_vehicles.registration_number as vehicle_registration', 'creators.name as creator_name']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()->add('id')->add('booking_code')->add('customer_name')->add('vehicle_registration')->add('creator_name')
            ->add('rental_type', fn (Booking $booking): string => e(ucfirst($booking->rental_type)))
            ->add('rate_per_unit')
            ->add('start_date_formatted', fn (Booking $booking): string => $booking->start_date?->format('Y-m-d') ?? '')
            ->add('end_date_formatted', fn (Booking $booking): string => $booking->end_date?->format('Y-m-d') ?? '')
            ->add('actual_return_date_formatted', fn (Booking $booking): string => $booking->actual_return_date?->format('Y-m-d') ?? '')
            ->add('start_odometer')->add('end_odometer')
            ->add('booking_status', fn (Booking $booking): string => e(ucfirst($booking->booking_status)))
            ->add('payment_status', fn (Booking $booking): string => e(ucfirst($booking->payment_status)))
            ->add('handover_notes')->add('return_notes')
            ->add('row_actions', fn (Booking $booking): string => view('admin.bookings.table-actions', compact('booking'))->render());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id', 'bookings.id')->sortable(),
            Column::make('Booking Code', 'booking_code', 'bookings.booking_code')->sortable()->searchable(),
            Column::make('Customer', 'customer_name', 'customers.name')->sortable()->searchable(),
            Column::make('Vehicle Registration', 'vehicle_registration', 'booked_vehicles.registration_number')->sortable()->searchable(),
            Column::make('Created By', 'creator_name', 'creators.name')->sortable(),
            Column::make('Rental Type', 'rental_type', 'bookings.rental_type')->sortable(),
            Column::make('Rate per Unit', 'rate_per_unit', 'bookings.rate_per_unit')->sortable(),
            Column::make('Start Date', 'start_date_formatted', 'bookings.start_date')->sortable(),
            Column::make('End Date', 'end_date_formatted', 'bookings.end_date')->sortable(),
            Column::make('Actual Return Date', 'actual_return_date_formatted', 'bookings.actual_return_date')->sortable(),
            Column::make('Start Odometer', 'start_odometer', 'bookings.start_odometer')->sortable(),
            Column::make('End Odometer', 'end_odometer', 'bookings.end_odometer')->sortable(),
            Column::make('Booking Status', 'booking_status', 'bookings.booking_status')->sortable(),
            Column::make('Payment Status', 'payment_status', 'bookings.payment_status')->sortable(),
            Column::make('Handover Notes', 'handover_notes')->hidden()->visibleInExport(true),
            Column::make('Return Notes', 'return_notes')->hidden()->visibleInExport(true),
            Column::make('Actions', 'row_actions')->visibleInExport(false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('booking_status', 'bookings.booking_status')->dataSource(array_map(
                fn (string $status): array => ['value' => $status, 'label' => ucfirst($status)], Booking::STATUSES,
            ))->optionValue('value')->optionLabel('label'),
            Filter::select('payment_status', 'bookings.payment_status')->dataSource(array_map(
                fn (string $status): array => ['value' => $status, 'label' => ucfirst($status)], Booking::PAYMENT_STATUSES,
            ))->optionValue('value')->optionLabel('label'),
            Filter::select('rental_type', 'bookings.rental_type')->dataSource(array_map(
                fn (string $type): array => ['value' => $type, 'label' => ucfirst($type)], Booking::RENTAL_TYPES,
            ))->optionValue('value')->optionLabel('label'),
        ];
    }

    public function exportToXLS(bool $selected = false): BinaryFileResponse|bool
    {
        Gate::authorize('export', Booking::class);
        if ($selected) {
            $this->selectedBookingIds();
        }
        data_set($this->setUp, 'exportable.fileName', 'bookings-'.Str::uuid());
        try {
            $result = $this->exportWithPowerGridToXls($selected);
            if ($result instanceof BinaryFileResponse) {
                $result->setContentDisposition('attachment', 'bookings.xlsx');
                activity('booking')->causedBy(auth()->user())->event('export')
                    ->withProperties(['action' => 'export', 'format' => 'xlsx', 'count' => $this->exportedRowCount])
                    ->log('Bookings exported using PowerGrid');
            }

            return $result;
        } finally {
            data_set($this->setUp, 'exportable.fileName', 'bookings');
        }
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        Gate::authorize('export', Booking::class);
        if ($selected) {
            $this->selectedBookingIds();
        }
        $rows = $this->prepareWithPowerGrid($selected);
        $this->exportedRowCount = $rows->count();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'No bookings match the current search or selection.']);
        }

        return $rows->map(function (object $row): object {
            foreach (['booking_code', 'customer_name', 'vehicle_registration', 'creator_name', 'handover_notes', 'return_notes'] as $field) {
                $value = html_entity_decode((string) $row->{$field}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row->{$field} = e(Spreadsheet::neutralize($value));
            }

            return $row;
        });
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        Gate::authorize('delete', Booking::class);
        $ids = $this->selectedBookingIds();
        try {
            DB::transaction(function () use ($ids): void {
                $bookings = Booking::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
                if ($bookings->count() !== count($ids)) {
                    throw ValidationException::withMessages(['ids' => 'The selection has changed. Refresh the table and try again.']);
                }
                foreach ($bookings as $booking) {
                    Gate::authorize('delete', $booking);
                    if ($booking->delete() !== true) {
                        throw ValidationException::withMessages(['ids' => 'A selected booking could not be deleted. No bookings were deleted.']);
                    }
                }
                activity('booking')->causedBy(auth()->user())->performedOn($bookings->first())->event('bulk_delete')
                    ->withProperties(['action' => 'bulk_delete', 'count' => count($ids), 'ids' => $ids])->log('Bulk deleted bookings');
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['ids' => 'Selected bookings are linked to other records and cannot be deleted. No bookings were deleted.']);
        }
        session()->flash('message', count($ids).' bookings deleted successfully.');
        $this->redirect(route('xpress.bookings.index'));
    }

    /** @return list<int> */
    private function selectedBookingIds(): array
    {
        $validated = validator(['ids' => $this->checkboxValues], [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:bookings,id'],
        ])->validate();

        return array_values(array_map('intval', $validated['ids']));
    }
}
