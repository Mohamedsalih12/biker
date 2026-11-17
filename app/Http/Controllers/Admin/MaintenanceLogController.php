<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceLogFormRequest;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MaintenanceLogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', MaintenanceLog::class);
        activity('maintenance_log')->causedBy(auth()->user())->event('view')->withProperties(['action' => 'view'])->log('Maintenance logs viewed');

        return view('admin.maintenance-logs.index');
    }

    public function create(): View
    {
        Gate::authorize('create', MaintenanceLog::class);

        return view('admin.maintenance-logs.create', $this->formData(new MaintenanceLog));
    }

    public function store(MaintenanceLogFormRequest $request): RedirectResponse
    {
        Gate::authorize('create', MaintenanceLog::class);
        $this->saveMaintenanceLog($request, new MaintenanceLog);

        return redirect()->route('xpress.maintenance-logs.index')->with('message', 'Maintenance log created successfully.');
    }

    public function show(MaintenanceLog $maintenanceLog): View
    {
        Gate::authorize('view', $maintenanceLog);
        $maintenanceLog->load(['vehicle:id,registration_number', 'user:id,name']);
        activity('maintenance_log')->causedBy(auth()->user())->performedOn($maintenanceLog)->event('view')->withProperties(['action' => 'view'])->log('Maintenance log viewed');

        return view('admin.maintenance-logs.show', compact('maintenanceLog'));
    }

    public function edit(MaintenanceLog $maintenanceLog): View
    {
        Gate::authorize('update', $maintenanceLog);

        return view('admin.maintenance-logs.edit', $this->formData($maintenanceLog));
    }

    public function update(MaintenanceLogFormRequest $request, MaintenanceLog $maintenanceLog): RedirectResponse
    {
        Gate::authorize('update', $maintenanceLog);
        $this->saveMaintenanceLog($request, $maintenanceLog);

        return redirect()->route('xpress.maintenance-logs.index')->with('message', 'Maintenance log updated successfully.');
    }

    public function destroy(MaintenanceLog $maintenanceLog): RedirectResponse
    {
        Gate::authorize('delete', $maintenanceLog);
        try {
            DB::transaction(function () use ($maintenanceLog): void {
                if ($maintenanceLog->delete() !== true) {
                    throw ValidationException::withMessages(['maintenance_log' => 'The maintenance log could not be deleted.']);
                }
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['maintenance_log' => 'This maintenance log is linked to other records and cannot be deleted.']);
        }

        return redirect()->route('xpress.maintenance-logs.index')->with('message', 'Maintenance log deleted successfully.');
    }

    public function vehicles(Request $request): JsonResponse
    {
        Gate::authorize('manage-maintenance-logs');
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $vehicles = Vehicle::query()->when(filled($validated['q'] ?? null), fn ($query) => $query->where('registration_number', 'like', '%'.$validated['q'].'%'))
            ->orderBy('registration_number')->simplePaginate(20, ['id', 'registration_number', 'odometer_reading']);

        return response()->json([
            'results' => $vehicles->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id, 'text' => $vehicle->registration_number, 'odometer_reading' => $vehicle->odometer_reading,
            ])->all(),
            'pagination' => ['more' => $vehicles->hasMorePages()],
        ]);
    }

    private function saveMaintenanceLog(MaintenanceLogFormRequest $request, MaintenanceLog $maintenanceLog): void
    {
        DB::transaction(function () use ($request, $maintenanceLog): void {
            if ($maintenanceLog->exists) {
                $maintenanceLog = MaintenanceLog::query()->lockForUpdate()->findOrFail($maintenanceLog->id);
            }
            $attributes = $request->validated();
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($attributes['vehicle_id']);
            if (! $maintenanceLog->exists && $vehicle->bookings()->whereIn('booking_status', ['reserved', 'taken'])->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'Cannot create a maintenance log for a vehicle with a reserved or taken booking.',
                ]);
            }
            if (! $maintenanceLog->exists || $maintenanceLog->vehicle_id !== $vehicle->id) {
                $maintenanceLog->odometer_reading = $vehicle->odometer_reading;
            }
            if (! $maintenanceLog->exists) {
                $maintenanceLog->user_id = $request->user()->id;
            }
            $maintenanceLog->fill($attributes);
            if ($maintenanceLog->save() !== true) {
                throw ValidationException::withMessages(['maintenance_log' => 'The maintenance log could not be saved. Please try again.']);
            }
        });
    }

    /** @return array{maintenanceLog: MaintenanceLog, selectedVehicle: ?Vehicle} */
    private function formData(MaintenanceLog $maintenanceLog): array
    {
        $vehicleId = filter_var(old('vehicle_id', $maintenanceLog), FILTER_VALIDATE_INT);
        $maintenanceLog->load('user:id,name');

        return ['maintenanceLog' => $maintenanceLog, 'selectedVehicle' => $vehicleId ? Vehicle::query()->select(['id', 'registration_number', 'odometer_reading'])->find($vehicleId) : null];
    }
}
