<?php

namespace App\Livewire\Admin;

use App\Models\Vehicle;
use App\Support\IndexNumberColumn;
use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
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
        $this->persist(['columns'], 'user-'.auth()->id());

        return [
            PowerGrid::header()->showSearchInput()->showToggleColumns(),
            PowerGrid::footer()->showPerPage(15, [15, 25, 50, 100])->showRecordCount(),
            PowerGrid::exportable('vehicles')->type(Exportable::TYPE_XLS),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('manage-vehicles');

        return Vehicle::query()->with('maintenanceLogs:id,vehicle_id,status,delivered_date,expected_completion_date')->fromSub(
            Vehicle::query()->withCurrentStatus()->withSum('payments as total_amount_earned', 'amount_received'),
            'vehicles',
        )->select([
            'id', 'registration_number', 'brand', 'model', 'chassis_number', 'battery_serial_number',
            'odometer_reading', 'rate_per_unit', 'status', 'purchase_date', 'purchase_cost', 'insurance_policy_number', 'insurance_expires_at',
            'image', 'images', 'damages', 'current_status', 'total_amount_earned',
        ]);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('registration_number_plain', fn (Vehicle $vehicle): string => e($vehicle->registration_number))
            ->add('registration_number', fn (Vehicle $vehicle): string => view('admin.vehicles.table-registration', [
                'vehicle' => $vehicle, 'alert' => $vehicle->maintenanceAlert(),
            ])->render())
            ->add('brand')->add('model')
            ->add('chassis_number')->add('battery_serial_number')->add('odometer_reading')->add('rate_per_unit')
            ->add('status', fn (Vehicle $vehicle): string => $vehicle->current_status === 'maintenance' ? 'Under Maintenance' : e(ucfirst($vehicle->current_status)))
            ->add('purchase_date_formatted', fn (Vehicle $vehicle): string => $vehicle->purchase_date?->format('Y-m-d') ?? '')
            ->add('purchase_cost')
            ->add('insurance_policy_number')
            ->add('insurance_expires_at_formatted', fn (Vehicle $vehicle): string => $vehicle->insurance_expires_at?->format('Y-m-d') ?? '')
            ->add('image')
            ->add('image_preview', fn (Vehicle $vehicle): string => view('admin.vehicles.table-image', compact('vehicle'))->render())
            ->add('damages')
            ->add('total_amount_earned', fn (Vehicle $vehicle): mixed => $vehicle->total_amount_earned ?? 0)
            ->add('row_actions', fn (Vehicle $vehicle): string => view('admin.vehicles.table-actions', compact('vehicle'))->render());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable()->visibleInExport(true),
            Column::make('Photo', 'image_preview')->visibleInExport(false),
            Column::make('Registration Number', 'registration_number')->sortable()->searchable()->visibleInExport(true),
            Column::make('Brand', 'brand')->sortable()->searchable()->visibleInExport(true),
            Column::make('Model', 'model')->sortable()->searchable()->visibleInExport(true),
            Column::make('Chassis Number', 'chassis_number')->sortable()->searchable()->visibleInExport(true),
            Column::make('Battery Serial Number', 'battery_serial_number')->sortable()->searchable()->visibleInExport(true),
            IndexNumberColumn::make('Odometer Reading', 'odometer_reading')->sortable()->visibleInExport(true),
            IndexNumberColumn::make('Rate Per Unit', 'rate_per_unit')->sortable()->visibleInExport(true),
            Column::make('Status', 'status', 'current_status')->sortable()->visibleInExport(true),
            Column::make('Purchase Date', 'purchase_date_formatted', 'purchase_date')->sortable()->visibleInExport(true),
            IndexNumberColumn::make('Purchase Cost', 'purchase_cost')->sortable()->visibleInExport(true),
            Column::make('Insurance Policy Number', 'insurance_policy_number')->sortable()->searchable()->visibleInExport(true),
            Column::make('Insurance Expires At', 'insurance_expires_at_formatted', 'insurance_expires_at')->sortable()->visibleInExport(true),
            Column::make('Image Path', 'image')->hidden()->visibleInExport(true),
            Column::make('Damages', 'damages')->searchable()->bodyAttribute('text-wrap text-break', 'min-width: 15rem; max-width: 24rem;')->visibleInExport(true),
            IndexNumberColumn::make('Total Amount Earned', 'total_amount_earned')->sortable()->visibleInExport(false),
            Column::make('Actions', 'row_actions')->visibleInExport(false),
        ];
    }

    public function actionRules(): array
    {
        return [
            Rule::rows()->setAttribute('class', 'vehicle-maintenance-warning')
                ->when(fn (Vehicle $vehicle): bool => ($vehicle->maintenanceAlert()['severity'] ?? null) === 'warning'),
            Rule::rows()->setAttribute('class', 'vehicle-maintenance-danger')
                ->when(fn (Vehicle $vehicle): bool => ($vehicle->maintenanceAlert()['severity'] ?? null) === 'danger'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status')->dataSource(array_map(
                fn (string $status): array => ['value' => $status, 'label' => $status === 'maintenance' ? 'Under Maintenance' : ucfirst($status)],
                Vehicle::CURRENT_STATUSES,
            ))->optionValue('value')->optionLabel('label')
                ->builder(function (Builder $query, mixed $value): void {
                    if (filled($value)) {
                        $query->where('current_status', $value);
                    }
                }),
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
            $row->registration_number = $row->registration_number_plain;

            foreach (['registration_number', 'brand', 'model', 'chassis_number', 'battery_serial_number', 'insurance_policy_number', 'image', 'damages'] as $field) {
                $value = html_entity_decode((string) $row->{$field}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row->{$field} = e(Spreadsheet::neutralize($value));
            }

            return $row;
        });
    }

    public function removePhoto(int $vehicleId, ?string $photoKey = null): void
    {
        Gate::authorize('manage-vehicles');

        DB::transaction(function () use ($vehicleId, $photoKey): void {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            Gate::authorize('update', $vehicle);

            $images = $vehicle->imagePaths();
            if ($images === [] && $photoKey === null) {
                return;
            }

            if ($photoKey === null && count($images) > 1) {
                throw ValidationException::withMessages(['image' => 'Select a specific photo to remove. Refresh the table and try again.']);
            }

            $image = $photoKey === null ? $images[0] : collect($images)->first(
                fn (string $path): bool => hash('sha256', $path) === $photoKey,
            );

            if ($image === null) {
                throw ValidationException::withMessages(['image' => 'This photo has changed. Refresh the table and try again.']);
            }

            $remainingImages = array_values(array_diff($images, [$image]));
            $vehicle->image = $remainingImages[0] ?? null;
            $vehicle->images = $remainingImages;

            if ($vehicle->save() !== true) {
                throw ValidationException::withMessages(['image' => 'The photo could not be removed. Please try again.']);
            }

            if (! Storage::disk('public')->delete($image)) {
                throw ValidationException::withMessages(['image' => 'The photo file could not be deleted. Please try again.']);
            }
        });

        session()->flash('image_message', 'Vehicle photo removed successfully.');
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
