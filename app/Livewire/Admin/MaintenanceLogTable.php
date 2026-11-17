<?php

namespace App\Livewire\Admin;

use App\Models\MaintenanceLog;
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

class MaintenanceLogTable extends PowerGridComponent
{
    use WithExport {
        exportToXLS as private exportWithPowerGridToXls;
        exportToCsv as private;
        prepareToExport as private prepareWithPowerGrid;
    }

    public string $tableName = 'maintenance-log-table';

    public string $primaryKey = 'maintenance_logs.id';

    public ?string $primaryKeyAlias = 'id';

    public string $sortField = 'maintenance_logs.id';

    private int $exportedRowCount = 0;

    public function boot(): void
    {
        Gate::authorize('manage-maintenance-logs');
    }

    public function setUp(): array
    {
        $this->showCheckBox();
        $this->persist(['columns'], 'user-'.auth()->id());

        return [
            PowerGrid::header()->showSearchInput()->showToggleColumns(),
            PowerGrid::footer()->showPerPage(15, [15, 25, 50, 100])->showRecordCount(),
            PowerGrid::exportable('maintenance-logs')->type(Exportable::TYPE_XLS),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('manage-maintenance-logs');

        return MaintenanceLog::query()
            ->join('vehicles as serviced_vehicles', 'serviced_vehicles.id', '=', 'maintenance_logs.vehicle_id')
            ->join('users as creators', 'creators.id', '=', 'maintenance_logs.user_id')
            ->select(['maintenance_logs.*', 'serviced_vehicles.registration_number as vehicle_registration', 'creators.name as creator_name']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()->add('id')->add('vehicle_registration')->add('service_description')
            ->add('delivered_date_formatted', fn (MaintenanceLog $log): string => $log->delivered_date?->format('Y-m-d') ?? '')
            ->add('expected_completion_date_formatted', fn (MaintenanceLog $log): string => $log->expected_completion_date?->format('Y-m-d') ?? '')
            ->add('cost')->add('odometer_reading')
            ->add('vendor_name', fn (MaintenanceLog $log): string => e(ucwords(str_replace('_', ' ', $log->vendor_name))))
            ->add('status', fn (MaintenanceLog $log): string => e(ucwords(str_replace('_', ' ', $log->status))))
            ->add('creator_name')
            ->add('row_actions', fn (MaintenanceLog $maintenanceLog): string => view('admin.maintenance-logs.table-actions', compact('maintenanceLog'))->render());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id', 'maintenance_logs.id')->sortable()->visibleInExport(true),
            Column::make('Vehicle Registration', 'vehicle_registration', 'serviced_vehicles.registration_number')->sortable()->searchable()->visibleInExport(true),
            Column::make('Service Description', 'service_description', 'maintenance_logs.service_description')->sortable()->searchable()->bodyAttribute('text-wrap text-break', 'min-width: 15rem; max-width: 24rem;')->visibleInExport(true),
            Column::make('Delivered Date', 'delivered_date_formatted', 'maintenance_logs.delivered_date')->sortable()->visibleInExport(true),
            Column::make('Expected Completion Date', 'expected_completion_date_formatted', 'maintenance_logs.expected_completion_date')->sortable()->visibleInExport(true),
            IndexNumberColumn::make('Cost', 'cost', 'maintenance_logs.cost')->sortable()->visibleInExport(true),
            IndexNumberColumn::make('Odometer Reading', 'odometer_reading', 'maintenance_logs.odometer_reading')->sortable()->visibleInExport(true),
            Column::make('Vendor', 'vendor_name', 'maintenance_logs.vendor_name')->sortable()->visibleInExport(true),
            Column::make('Status', 'status', 'maintenance_logs.status')->sortable()->visibleInExport(true),
            Column::make('Created By', 'creator_name', 'creators.name')->sortable()->searchable()->visibleInExport(true),
            Column::make('Actions', 'row_actions')->visibleInExport(false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'maintenance_logs.status')->dataSource(array_map(
                fn (string $status): array => ['value' => $status, 'label' => ucwords(str_replace('_', ' ', $status))], MaintenanceLog::STATUSES,
            ))->optionValue('value')->optionLabel('label'),
            Filter::select('vendor_name', 'maintenance_logs.vendor_name')->dataSource(array_map(
                fn (string $vendor): array => ['value' => $vendor, 'label' => ucwords(str_replace('_', ' ', $vendor))], MaintenanceLog::VENDORS,
            ))->optionValue('value')->optionLabel('label'),
            Filter::inputText('delivered_date_formatted', 'maintenance_logs.delivered_date')->operators(['is'])->placeholder('YYYY-MM-DD')
                ->builder(function (Builder $query, array $values): void {
                    if (filled($values['value'])) {
                        $query->whereDate('maintenance_logs.delivered_date', $values['value']);
                    }
                }),
            Filter::inputText('expected_completion_date_formatted', 'maintenance_logs.expected_completion_date')->operators(['is'])->placeholder('YYYY-MM-DD')
                ->builder(function (Builder $query, array $values): void {
                    if (filled($values['value'])) {
                        $query->whereDate('maintenance_logs.expected_completion_date', $values['value']);
                    }
                }),
        ];
    }

    public function exportToXLS(bool $selected = false): BinaryFileResponse|bool
    {
        Gate::authorize('export', MaintenanceLog::class);
        if ($selected) {
            $this->selectedMaintenanceLogIds();
        }
        data_set($this->setUp, 'exportable.fileName', 'maintenance-logs-'.Str::uuid());
        try {
            $result = $this->exportWithPowerGridToXls($selected);
            if ($result instanceof BinaryFileResponse) {
                $result->setContentDisposition('attachment', 'maintenance-logs.xlsx');
                activity('maintenance_log')->causedBy(auth()->user())->event('export')
                    ->withProperties(['action' => 'export', 'format' => 'xlsx', 'count' => $this->exportedRowCount])
                    ->log('Maintenance logs exported using PowerGrid');
            }

            return $result;
        } finally {
            data_set($this->setUp, 'exportable.fileName', 'maintenance-logs');
        }
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        Gate::authorize('export', MaintenanceLog::class);
        if ($selected) {
            $this->selectedMaintenanceLogIds();
        }
        $rows = $this->prepareWithPowerGrid($selected);
        $this->exportedRowCount = $rows->count();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'No maintenance logs match the current search or selection.']);
        }

        return $rows->map(function (object $row): object {
            foreach (['vehicle_registration', 'service_description', 'creator_name'] as $field) {
                $value = html_entity_decode((string) $row->{$field}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row->{$field} = e(Spreadsheet::neutralize($value));
            }

            return $row;
        });
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        Gate::authorize('delete', MaintenanceLog::class);
        $ids = $this->selectedMaintenanceLogIds();
        try {
            DB::transaction(function () use ($ids): void {
                $logs = MaintenanceLog::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
                if ($logs->count() !== count($ids)) {
                    throw ValidationException::withMessages(['ids' => 'The selection has changed. Refresh the table and try again.']);
                }
                foreach ($logs as $log) {
                    Gate::authorize('delete', $log);
                    if ($log->delete() !== true) {
                        throw ValidationException::withMessages(['ids' => 'A selected maintenance log could not be deleted. No maintenance logs were deleted.']);
                    }
                }
                activity('maintenance_log')->causedBy(auth()->user())->performedOn($logs->first())->event('bulk_delete')
                    ->withProperties(['action' => 'bulk_delete', 'count' => count($ids), 'ids' => $ids])->log('Bulk deleted maintenance logs');
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['ids' => 'Selected maintenance logs are linked to other records and cannot be deleted. No maintenance logs were deleted.']);
        }
        session()->flash('message', count($ids).' maintenance logs deleted successfully.');
        $this->redirect(route('xpress.maintenance-logs.index'));
    }

    /** @return list<int> */
    private function selectedMaintenanceLogIds(): array
    {
        $validated = validator(['ids' => $this->checkboxValues], [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:maintenance_logs,id'],
        ])->validate();

        return array_values(array_map('intval', $validated['ids']));
    }
}
