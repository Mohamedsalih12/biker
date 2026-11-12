<?php

namespace App\Livewire\Admin;

use App\Models\Vehicle;
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

class VehicleTable extends PowerGridComponent
{
    use WithExport {
        exportToXLS as private exportWithPowerGridToXls;
        exportToCsv as private;
        prepareToExport as private prepareWithPowerGrid;
    }

    public string $tableName = 'vehicle-table';

    public string $sortField = 'id';

    private int $exportedRowCount = 0;

    public function boot(): void
    {
        Gate::authorize('manage-vehicles');
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()->showSearchInput()->includeViewOnTop('admin.vehicles.table-feedback'),
            PowerGrid::footer()->showPerPage(15, [15, 25, 50, 100])->showRecordCount(),
            PowerGrid::exportable('vehicles')->type(Exportable::TYPE_XLS),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('manage-vehicles');

        return Vehicle::query()->select([
            'id', 'registration_number', 'brand', 'model', 'chassis_number', 'battery_serial_number',
            'odometer_reading', 'status', 'purchase_date', 'purchase_cost', 'insurance_policy_number', 'insurance_expires_at',
        ]);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')->add('registration_number')->add('brand')->add('model')
            ->add('chassis_number')->add('battery_serial_number')->add('odometer_reading')
            ->add('status', fn (Vehicle $vehicle): string => e(ucfirst($vehicle->status)))
            ->add('purchase_date_formatted', fn (Vehicle $vehicle): string => $vehicle->purchase_date?->format('Y-m-d') ?? '')
            ->add('purchase_cost')
            ->add('insurance_policy_number')
            ->add('insurance_expires_at_formatted', fn (Vehicle $vehicle): string => $vehicle->insurance_expires_at?->format('Y-m-d') ?? '')
            ->add('row_actions', fn (Vehicle $vehicle): string => view('admin.vehicles.table-actions', compact('vehicle'))->render());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable(),
            Column::make('Registration Number', 'registration_number')->sortable()->searchable(),
            Column::make('Brand', 'brand')->sortable()->searchable(),
            Column::make('Model', 'model')->sortable()->searchable(),
            Column::make('Chassis Number', 'chassis_number')->sortable()->searchable(),
            Column::make('Battery Serial Number', 'battery_serial_number')->sortable()->searchable(),
            Column::make('Odometer Reading', 'odometer_reading')->sortable(),
            Column::make('Status', 'status')->sortable(),
            Column::make('Purchase Date', 'purchase_date_formatted', 'purchase_date')->sortable(),
            Column::make('Purchase Cost', 'purchase_cost')->sortable(),
            Column::make('Insurance Policy Number', 'insurance_policy_number')->sortable()->searchable(),
            Column::make('Insurance Expires At', 'insurance_expires_at_formatted', 'insurance_expires_at')->sortable(),
            Column::make('Actions', 'row_actions')->visibleInExport(false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status')->dataSource(array_map(
                fn (string $status): array => ['value' => $status, 'label' => ucfirst($status)],
                Vehicle::STATUSES,
            ))->optionValue('value')->optionLabel('label'),
        ];
    }

    public function exportToXLS(bool $selected = false): BinaryFileResponse|bool
    {
        Gate::authorize('export', Vehicle::class);

        if ($selected) {
            $this->selectedVehicleIds();
        }

        data_set($this->setUp, 'exportable.fileName', 'vehicles-'.Str::uuid());

        try {
            $result = $this->exportWithPowerGridToXls($selected);

            if ($result instanceof BinaryFileResponse) {
                $result->setContentDisposition('attachment', 'vehicles.xlsx');

                activity('vehicle')->causedBy(auth()->user())->event('export')
                    ->withProperties(['action' => 'export', 'format' => 'xlsx', 'count' => $this->exportedRowCount])
                    ->log('Vehicles exported using PowerGrid');
            }

            return $result;
        } finally {
            data_set($this->setUp, 'exportable.fileName', 'vehicles');
        }
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        Gate::authorize('export', Vehicle::class);

        if ($selected) {
            $this->selectedVehicleIds();
        }

        $rows = $this->prepareWithPowerGrid($selected);
        $this->exportedRowCount = $rows->count();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'No vehicles match the current search or selection.']);
        }

        return $rows->map(function (object $row): object {
            foreach (['registration_number', 'brand', 'model', 'chassis_number', 'battery_serial_number', 'insurance_policy_number'] as $field) {
                $value = html_entity_decode((string) $row->{$field}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row->{$field} = e(Spreadsheet::neutralize($value));
            }

            return $row;
        });
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        Gate::authorize('delete', Vehicle::class);
        $ids = $this->selectedVehicleIds();

        try {
            DB::transaction(function () use ($ids): void {
                $vehicles = Vehicle::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

                if ($vehicles->count() !== count($ids)) {
                    throw ValidationException::withMessages(['ids' => 'The selection has changed. Refresh the table and try again.']);
                }

                foreach ($vehicles as $vehicle) {
                    Gate::authorize('delete', $vehicle);
                    if ($vehicle->delete() !== true) {
                        throw ValidationException::withMessages(['ids' => 'A selected vehicle could not be deleted. No vehicles were deleted.']);
                    }
                }

                activity('vehicle')->causedBy(auth()->user())->performedOn($vehicles->first())->event('bulk_delete')
                    ->withProperties(['action' => 'bulk_delete', 'count' => count($ids), 'ids' => $ids])
                    ->log('Bulk deleted vehicles');
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['ids' => 'Selected vehicles are linked to other records and cannot be deleted. No vehicles were deleted.']);
        }

        session()->flash('message', count($ids).' vehicles deleted successfully.');
        $this->redirect(route('xpress.vehicles.index'));
    }

    /** @return list<int> */
    private function selectedVehicleIds(): array
    {
        $validated = validator(['ids' => $this->checkboxValues], [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:vehicles,id'],
        ])->validate();

        return array_values(array_map('intval', $validated['ids']));
    }
}
