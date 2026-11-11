<?php

namespace App\Livewire\Xpress;

use App\Models\Xpress\Warehouse;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WarehouseTable extends PowerGridComponent
{
    use WithExport {
        exportToCsv as private exportWithPowerGridToCsv;
        exportToXLS as private exportWithPowerGridToXls;
    }

    public string $tableName = 'warehouse-table';

    public string $sortField = 'warehouses.id';

    #[Url(as: 'searchBox')]
    public string $searchBox = '';

    // public function customThemeClass(): ?string
    // {
    //     return HeaderTheme::class;
    // }

    public function setUp(): array
    {
        if (Gate::allows('delete', Warehouse::class)) {
            $this->showCheckBox();
        }

        $setUp = [
            PowerGrid::header()
                ->showSearchInput(),

            PowerGrid::footer()
                ->showPerPage(15, [15, 25, 50, 100])
                ->showRecordCount(),
        ];

        if (Gate::allows('export', Warehouse::class)) {
            $setUp[] = PowerGrid::exportable('warehouses')
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV);
        }

        return $setUp;
    }

    public function datasource(): Builder
    {
        return Warehouse::query()
            ->leftJoin('users as warehouse_users', 'warehouses.user_id', '=', 'warehouse_users.id')
            ->select([
                'warehouses.*',
                'warehouse_users.name as user_name',
            ])
            ->when(filled($this->searchBox), function (Builder $query): void {
                $search = '%'.$this->searchBox.'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->where('warehouses.warehouse_name', 'like', $search);
                });
            });
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('user_name')
            ->add('warehouse_code')
            ->add('warehouse_name')
            ->add('active_status');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id', 'warehouses.id')
                ->sortable(),

            Column::make('Username', 'user_name', 'warehouse_users.name')
                ->searchable()
                ->sortable(),

            Column::make('WH Code', 'warehouse_code', 'warehouses.warehouse_code')
                ->searchable()
                ->sortable(),

            Column::make('Warehouse Name', 'warehouse_name', 'warehouses.warehouse_name')
                ->searchable()
                ->sortable(),

            Column::make('Active / Inactive', 'active_status', 'warehouses.active_status')
                ->searchable()
                ->sortable()
                ->contentClasses('!whitespace-normal')
                ->placeholder('placeholder')
                ->visibleInExport(visible: false)
                ->fixedOnResponsive(),

            Column::action('Actions'),
        ];
    }

    public function filters(): array
    {
        return [

            Filter::multiSelect('user_name', 'warehouse_users.name')
                ->dataSource(
                    DB::table('warehouses')
                        ->join('users as warehouse_users', 'warehouses.user_id', '=', 'warehouse_users.id')
                        ->select('warehouse_users.name as value', 'warehouse_users.name as label')
                        ->whereNotNull('warehouse_users.name')
                        ->distinct()
                        ->orderBy('label')
                        ->get()
                        ->map(static fn (object $option): array => (array) $option)
                        ->all(),
                )
                ->optionValue('value')
                ->optionLabel('label'),

            Filter::multiSelect('warehouse_code', 'warehouses.warehouse_code')
                ->dataSource(Warehouse::all())
                ->optionValue('warehouse_code')
                ->optionLabel('warehouse_code'),

        ];
    }

    public function actions(Warehouse $warehouse): array
    {
        if (! Gate::allows('update', $warehouse)) {
            return [];
        }

        return [
            Button::add('edit')
                ->slot('Edit')
                ->class('btn btn-sm btn-warning')
                ->route('xpress.warehouse.edit', ['warehouse' => $warehouse->id]),
        ];
    }

    public function exportToXLS(bool $selected = false): BinaryFileResponse|bool
    {
        return $this->runWarehouseExport(
            $selected,
            'xlsx',
            fn (): BinaryFileResponse|bool => $this->exportWithPowerGridToXls($selected),
        );
    }

    public function exportToCsv(bool $selected = false): BinaryFileResponse|bool
    {
        return $this->runWarehouseExport(
            $selected,
            'csv',
            fn (): BinaryFileResponse|bool => $this->exportWithPowerGridToCsv($selected),
        );
    }

    private function runWarehouseExport(bool $selected, string $format, Closure $export): BinaryFileResponse|bool
    {
        Gate::authorize('export', Warehouse::class);

        $result = $export();

        if ($result !== false) {
            activity('warehouse')
                ->causedBy(auth()->user())
                ->event('export')
                ->withProperties([
                    'action' => 'export',
                    'format' => $format,
                    'count' => $selected ? count($this->checkboxValues) : ($this->total() ?? 0),
                ])
                ->log('Warehouses exported using PowerGrid');
        }

        return $result;
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        Gate::authorize('delete', Warehouse::class);

        $ids = validator(
            ['ids' => $this->checkboxValues],
            [
                'ids' => ['required', 'array', 'max:15'],
                'ids.*' => ['integer', 'distinct', 'exists:warehouses,id'],
            ],
        )->validate()['ids'];

        $warehouses = DB::transaction(function () use ($ids) {
            $warehouses = Warehouse::query()->whereKey($ids)->get();
            $warehouses->each->delete();

            return $warehouses;
        });
        $deleted = $warehouses->count();

        activity('warehouse')
            ->causedBy(auth()->user())
            ->performedOn($warehouses->first())
            ->event('bulk_delete')
            ->withProperties([
                'action' => 'bulk_delete',
                'count' => $deleted,
                'ids' => $ids,
            ])
            ->log('Bulk deleted warehouses');

        session()->flash('message', 'Selected warehouses deleted successfully.');

        $this->redirect(route('xpress.warehouse.index'));
    }
}
