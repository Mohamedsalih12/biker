<?php

namespace App\Livewire\Admin;

use App\Models\User;
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

class UserTable extends PowerGridComponent
{
    use WithExport {
        exportToXLS as private exportWithPowerGridToXls;
        exportToCsv as private;
        prepareToExport as private prepareWithPowerGrid;
    }

    public string $tableName = 'user-table';

    public string $sortField = 'id';

    private int $exportedRowCount = 0;

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function setUp(): array
    {
        $this->showCheckBox();
        $this->persist(['columns'], 'user-'.auth()->id());

        return [
            PowerGrid::header()->showSearchInput()->showToggleColumns()->includeViewOnTop('admin.users.table-feedback'),
            PowerGrid::footer()->showPerPage(15, [15, 25, 50, 100])->showRecordCount(),
            PowerGrid::exportable('users')->type(Exportable::TYPE_XLS),
        ];
    }

    public function datasource(): Builder
    {
        Gate::authorize('manage-users');

        return User::query()->select(['id', 'name', 'email', 'phone', 'role', 'active_status']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('phone')
            ->add('role', fn (User $user): string => e(ucfirst($user->role)))
            ->add('active_status', fn (User $user): string => $user->active_status ? 'Active' : 'Inactive')
            ->add('row_actions', fn (User $user): string => view('admin.users.table-actions', ['user' => $user, 'type' => 'edit-delete'])->render())
            ->add('reset_password', fn (User $user): string => view('admin.users.table-actions', ['user' => $user, 'type' => 'reset-password'])->render())
            ->add('reset_2fa', fn (User $user): string => view('admin.users.table-actions', ['user' => $user, 'type' => 'reset-2fa'])->render());
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable()->visibleInExport(true),
            Column::make('Name', 'name')->sortable()->searchable()->visibleInExport(true),
            Column::make('Email', 'email')->sortable()->searchable()->visibleInExport(true),
            Column::make('Phone', 'phone')->sortable()->searchable()->visibleInExport(true),
            Column::make('Role', 'role')->sortable()->visibleInExport(true),
            Column::make('Active Status', 'active_status')->sortable()->visibleInExport(true),
            Column::make('Actions', 'row_actions')->visibleInExport(false),
            Column::make('Reset Password', 'reset_password')->visibleInExport(false),
            Column::make('Reset 2FA', 'reset_2fa')->visibleInExport(false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('role')->dataSource([
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'staff', 'label' => 'Staff'],
                ['value' => 'customer', 'label' => 'Customer'],
            ])->optionValue('value')->optionLabel('label'),
            Filter::select('active_status')->dataSource([
                ['value' => '1', 'label' => 'Active'],
                ['value' => '0', 'label' => 'Inactive'],
            ])->optionValue('value')->optionLabel('label'),
        ];
    }

    public function exportToXLS(bool $selected = false): BinaryFileResponse|bool
    {
        Gate::authorize('export', User::class);

        if ($selected) {
            $this->selectedUserIds();
        }

        data_set($this->setUp, 'exportable.fileName', 'users-'.Str::uuid());

        try {
            $result = $this->exportWithPowerGridToXls($selected);

            if ($result instanceof BinaryFileResponse) {
                $result->setContentDisposition('attachment', 'users.xlsx');

                activity('user')
                    ->causedBy(auth()->user())
                    ->event('export')
                    ->withProperties(['action' => 'export', 'format' => 'xlsx', 'count' => $this->exportedRowCount])
                    ->log('Users exported using PowerGrid');
            }

            return $result;
        } finally {
            data_set($this->setUp, 'exportable.fileName', 'users');
        }
    }

    public function prepareToExport(bool $selected = false): EloquentCollection|Collection
    {
        Gate::authorize('export', User::class);

        $rows = $this->prepareWithPowerGrid($selected);
        $this->exportedRowCount = $rows->count();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'No users match the current search or selection.']);
        }

        return $rows->map(function (object $row): object {
            foreach (['name', 'email', 'phone'] as $field) {
                $value = html_entity_decode((string) $row->{$field}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $row->{$field} = e(Spreadsheet::neutralize($value));
            }

            return $row;
        });
    }

    #[On('bulkDelete')]
    public function bulkDelete(): void
    {
        Gate::authorize('delete', User::class);

        $ids = $this->selectedUserIds();

        if (in_array((int) auth()->id(), $ids, true)) {
            throw ValidationException::withMessages(['ids' => 'You cannot delete your own account. No users were deleted.']);
        }

        try {
            $users = DB::transaction(function () use ($ids): EloquentCollection {
                $activeAdminIds = User::query()
                    ->where('role', 'admin')
                    ->where('active_status', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($activeAdminIds->isNotEmpty() && $activeAdminIds->diff($ids)->isEmpty()) {
                    throw ValidationException::withMessages(['ids' => 'The last active admin cannot be deleted. No users were deleted.']);
                }

                $users = User::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

                if ($users->count() !== count($ids)) {
                    throw ValidationException::withMessages(['ids' => 'The selection has changed. Refresh the table and try again.']);
                }

                foreach ($users as $user) {
                    Gate::authorize('delete', $user);
                    $user->delete();
                }

                return $users;
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['ids' => 'Selected users are linked to other records and cannot be deleted. No users were deleted.']);
        }

        activity('user')
            ->causedBy(auth()->user())
            ->performedOn($users->first())
            ->event('bulk_delete')
            ->withProperties(['action' => 'bulk_delete', 'count' => $users->count(), 'ids' => $ids])
            ->log('Bulk deleted users');

        session()->flash('message', $users->count().' users deleted successfully.');
        $this->redirect(route('xpress.users.index'));
    }

    /** @return list<int> */
    private function selectedUserIds(): array
    {
        $validated = validator(['ids' => $this->checkboxValues], [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ])->validate();

        return array_values(array_map('intval', $validated['ids']));
    }
}
