<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleFormRequest;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        $vehicle = $this->saveVehicle($request, new Vehicle);

        return redirect()->route('xpress.vehicles.edit', $vehicle)->with('message', 'Vehicle created successfully.');
    }

    public function show(Vehicle $vehicle): View
    {
        Gate::authorize('view', $vehicle);
        $vehicle = Vehicle::query()->withCurrentStatus()->findOrFail($vehicle->id);

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
        $this->saveVehicle($request, $vehicle);

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

    private function saveVehicle(VehicleFormRequest $request, Vehicle $vehicle): Vehicle
    {
        $attributes = $request->safe()->except(['image', 'images', 'replacement_images', 'remove_images']);
        $newImages = [];

        try {
            [$vehicle, $removedImages] = DB::transaction(function () use ($request, $vehicle, $attributes, &$newImages): array {
                if ($vehicle->exists) {
                    $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                }

                $oldImages = $vehicle->imagePaths();
                $knownImages = array_combine(array_map(fn (string $path): string => hash('sha256', $path), $oldImages), $oldImages);
                $removals = $request->validated('remove_images', []) ?? [];
                $replacements = $request->file('replacement_images', []);

                foreach (['remove_images' => $removals, 'replacement_images' => array_keys($replacements)] as $field => $keys) {
                    if (array_diff($keys, array_keys($knownImages)) !== []) {
                        throw ValidationException::withMessages([$field => 'A selected photo has changed. Refresh this page and try again.']);
                    }
                }

                if (array_intersect($removals, array_keys($replacements)) !== []) {
                    throw ValidationException::withMessages(['remove_images' => 'Choose either replace or remove for each photo, not both.']);
                }

                if ($request->hasFile('image') && ($removals !== [] || $replacements !== [])) {
                    throw ValidationException::withMessages(['image' => 'Do not combine a legacy cover upload with individual photo changes. Refresh this page and try again.']);
                }

                $images = [];
                foreach ($knownImages as $key => $path) {
                    if (in_array($key, $removals, true)) {
                        continue;
                    }

                    $images[] = isset($replacements[$key])
                        ? $this->storePhoto($replacements[$key], $newImages, 'replacement_images.'.$key)
                        : $path;
                }

                if ($request->hasFile('image')) {
                    $images[0] = $this->storePhoto($request->file('image'), $newImages, 'image');
                }

                foreach ($request->file('images', []) as $index => $photo) {
                    $images[] = $this->storePhoto($photo, $newImages, 'images.'.$index);
                }

                $vehicle->fill($attributes);
                $vehicle->image = $images[0] ?? null;
                $vehicle->images = $images;

                if ($vehicle->save() !== true) {
                    throw ValidationException::withMessages(['vehicle' => 'The vehicle could not be saved. Please try again.']);
                }

                return [$vehicle, array_values(array_diff($oldImages, $images))];
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newImages);

            throw $exception;
        }

        if ($removedImages !== []) {
            Storage::disk('public')->delete($removedImages);
        }

        return $vehicle;
    }

    /** @param list<string> $newImages */
    private function storePhoto(UploadedFile $photo, array &$newImages, string $field): string
    {
        $path = $photo->store('vehicles', 'public');

        if ($path === false) {
            throw ValidationException::withMessages([$field => 'The photo could not be stored. Please try again.']);
        }

        $newImages[] = $path;

        return $path;
    }
}
