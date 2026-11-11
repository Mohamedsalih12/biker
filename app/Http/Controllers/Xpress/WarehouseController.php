<?php

namespace App\Http\Controllers\Xpress;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseFormRequest;
use App\Models\User;
use App\Models\Xpress\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    protected $paginationTheme = 'bootstrap';

    public function index(Request $request)
    {
        $this->authorize('viewAny', Warehouse::class);

        // If clear filter button pressed, redirect to clean route (no query params)
        if ($request->has('clear')) {
            return redirect()->route('xpress.warehouse.index');
        }
        $firstRecord = Warehouse::first();

        $log = activity('warehouse')
            ->causedBy(Auth::user())
            ->event('view')
            ->withProperties([
                'action' => 'view',
            ]);
        if ($firstRecord) {
            $log->performedOn($firstRecord);
        }
        $log->log('Warehouses viewed');

        return view('xpress.warehouse.index');
    }

    public function bin(): View
    {
        $this->authorize('viewAny', Warehouse::class);

        $deletedWarehouses = Warehouse::deletedModels()
            ->latest()
            ->paginate(15);

        return view('xpress.warehouse.bin', compact('deletedWarehouses'));
    }

    public function restore(string $deletedModel): RedirectResponse
    {
        $this->authorize('delete', Warehouse::class);

        $deletedWarehouse = Warehouse::deletedModels()->findOrFail($deletedModel);
        $warehouse = Warehouse::restore($deletedWarehouse->getAttribute('key'));

        activity('warehouse')
            ->causedBy(Auth::user())
            ->performedOn($warehouse)
            ->event('restored')
            ->withProperties([
                'action' => 'restore',
                'deleted_model_id' => $deletedWarehouse->getKey(),
            ])
            ->log('Warehouse restored from Bin');

        return redirect()->route('xpress.warehouse.bin')->with('message', 'Warehouse restored successfully.');
    }

    public function create()
    {
        $this->authorize('create', Warehouse::class);

        $userNames = User::select('id', 'name')->get()->unique('name');

        return view('xpress.warehouse.create', compact('userNames'));
    }

    public function store(WarehouseFormRequest $request)
    {
        $this->authorize('create', Warehouse::class);
        $validatedData = $request->validated();
        Warehouse::create($validatedData);

        return redirect()->route('xpress.warehouse.index')->with('message', 'Warehouse added successfully.');
    }

    public function edit(Warehouse $warehouse)
    {
        $this->authorize('update', $warehouse);
        $userNames = User::select('id', 'name')->get()->unique('name');

        return view('xpress.warehouse.edit', compact('warehouse', 'userNames'));
    }

    public function update(WarehouseFormRequest $request, Warehouse $warehouse)
    {
        $this->authorize('update', $warehouse);
        $validatedData = $request->validated();
        $warehouse->update($validatedData);

        return redirect()->route('xpress.warehouse.index')->with('message', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return redirect()->route('xpress.warehouse.index')->with('message', 'Warehouse deleted successfully.');
    }
}
