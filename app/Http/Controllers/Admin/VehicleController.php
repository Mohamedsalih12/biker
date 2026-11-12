<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleFormRequest;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VehicleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Vehicle::class);

        activity('vehicle')->causedBy(auth()->user())->event('view')
            ->withProperties(['action' => 'view'])->log('Vehicles viewed');

        return view('admin.vehicles.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Vehicle::class);

        return view('admin.vehicles.create', ['vehicle' => new Vehicle]);
    }

    public function store(VehicleFormRequest $request): RedirectResponse
    {
        Gate::authorize('create', Vehicle::class);
        $vehicle = DB::transaction(fn (): Vehicle => Vehicle::create($request->validated()));

        return redirect()->route('xpress.vehicles.edit', $vehicle)->with('message', 'Vehicle created successfully.');
    }

    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);

        activity('vehicle')->causedBy(auth()->user())->performedOn($vehicle)->event('view')
            ->withProperties(['action' => 'view'])->log('Vehicle viewed');

        return view('admin.vehicles.show', compact('vehicle'));
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        return view('admin.vehicles.edit', compact('vehicle'));
    }

    public function update(VehicleFormRequest $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);
        DB::transaction(fn (): bool => $vehicle->update($request->validated()));

        return redirect()->route('xpress.vehicles.index')->with('message', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        try {
            DB::transaction(function () use ($vehicle): void {
                if ($vehicle->delete() !== true) {
                    throw ValidationException::withMessages(['vehicle' => 'This vehicle could not be deleted.']);
                }
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['vehicle' => 'This vehicle is linked to other records and cannot be deleted.']);
        }

        return redirect()->route('xpress.vehicles.index')->with('message', 'Vehicle deleted successfully.');
    }
}
