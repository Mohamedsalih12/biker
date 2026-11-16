<?php

namespace App\Livewire;

use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.fleet')]
class FleetDashboard extends Component
{
    public function render(): View
    {
        $bikes = Vehicle::query()
            ->select(['id', 'brand', 'model', 'rate_per_unit', 'odometer_reading', 'image', 'images'])
            ->where('status', '!=', 'decommissioned')
            ->with(['bookings' => fn (HasMany $query): HasMany => $query
                ->select(['id', 'vehicle_id', 'end_date'])
                ->whereIn('booking_status', ['reserved', 'taken'])
                ->orderByDesc('end_date')])
            ->orderBy('brand')->orderBy('model')->orderBy('id')
            ->get()
            ->map(function (Vehicle $vehicle): array {
                $available = $vehicle->bookings->isEmpty();
                $endDate = $vehicle->bookings->first()?->end_date;
                $nextDate = $endDate?->copy()->addDay();
                $dateConfirmed = $nextDate !== null && $nextDate->greaterThanOrEqualTo(today());
                $title = trim(($vehicle->brand ?? '').' '.($vehicle->model ?? '')) ?: 'Electric bike';
                $images = collect($vehicle->imagePaths())
                    ->take(6)
                    ->map(fn (string $image): string => asset('storage/'.ltrim($image, '/')))
                    ->values()->all();

                return [
                    'id' => $vehicle->id,
                    'title' => $title,
                    'search' => mb_strtolower($title),
                    'odometer' => $vehicle->odometer_reading !== null
                        ? 'Odometer: '.Number::format($vehicle->odometer_reading, locale: 'en_IN').' km'
                        : 'Odometer not listed',
                    'available' => $available,
                    'next_date' => $dateConfirmed ? $nextDate->format('d M Y') : null,
                    'prebook_date' => $dateConfirmed ? $nextDate->format('d M') : null,
                    'price' => $vehicle->rate_per_unit !== null
                        ? '₹'.Number::format(
                            (float) $vehicle->rate_per_unit,
                            precision: str_ends_with($vehicle->rate_per_unit, '.00') ? 0 : 2,
                            locale: 'en_IN',
                        )
                        : null,
                    'images' => $images,
                ];
            })->sortByDesc('available')->values()->all();

        $featuredBike = collect($bikes)->firstWhere('available', true)
            ?? collect($bikes)->whereNotNull('next_date')
                ->sortBy(fn (array $bike): int => strtotime($bike['next_date']))
                ->first()
            ?? ($bikes[0] ?? null);

        return view('livewire.fleet-dashboard', [
            'bikes' => $bikes,
            'featuredBike' => $featuredBike,
            'availableCount' => count(array_filter($bikes, fn (array $bike): bool => $bike['available'])),
        ]);
    }
}
