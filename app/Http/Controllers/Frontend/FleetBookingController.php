<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\FleetBookingFormRequest;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class FleetBookingController extends Controller
{
    public function create(Vehicle $vehicle): Response
    {
        abort_if($vehicle->status === 'decommissioned', 404);
        $rate = $vehicle->rate_per_unit !== null ? (float) $vehicle->rate_per_unit : null;

        return response()->view('booking', [
            'vehicle' => $vehicle,
            'canBook' => ! $vehicle->isUnderMaintenance()
                && ! $vehicle->bookings()->whereIn('booking_status', ['reserved', 'taken'])->exists()
                && $this->hasBookablePrice($vehicle),
            'bikeTitle' => trim(($vehicle->brand ?? '').' '.($vehicle->model ?? '')) ?: 'Electric bike',
            'price' => $rate !== null
                ? '₹'.Number::format(
                    $rate,
                    precision: floor($rate) === $rate ? 0 : 2,
                    locale: 'en_IN',
                )
                : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(FleetBookingFormRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validated();
        $booking = DB::transaction(function () use ($vehicle, $validated): Booking {
            $vehicle = Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            abort_if($vehicle->status === 'decommissioned', 404);

            if ($vehicle->isUnderMaintenance() || $vehicle->bookings()->whereIn('booking_status', ['reserved', 'taken'])->exists()) {
                throw ValidationException::withMessages([
                    'booking' => 'This bike is no longer available. Please choose another bike.',
                ]);
            }

            if (! $this->hasBookablePrice($vehicle)) {
                throw ValidationException::withMessages([
                    'booking' => 'Please contact our team to confirm pricing and reserve this bike.',
                ]);
            }

            $bookingCode = Booking::nextBookingCode();
            $matchingCustomers = User::query()
                ->whereRaw("REPLACE(REPLACE(aadhaar, ' ', ''), '-', '') = ?", [$validated['aadhaar']])
                ->lockForUpdate()->limit(2)->get(['id', 'role', 'active_status']);
            $customer = $matchingCustomers->first();

            if ($matchingCustomers->count() > 1
                || ($customer !== null && ($customer->role !== 'customer' || ! $customer->active_status))) {
                throw ValidationException::withMessages([
                    'aadhaar' => 'Please contact our team to complete this reservation.',
                ]);
            }

            if ($customer === null) {
                $customer = new User([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                ]);
                $customer->aadhaar = $validated['aadhaar'];
                $customer->role = 'customer';

                if (! $customer->save()) {
                    throw ValidationException::withMessages(['booking' => 'We could not save your reservation. Please try again.']);
                }
            }

            $booking = new Booking([
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'rental_type' => 'monthly',
                'rate_per_unit' => $vehicle->rate_per_unit,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'start_odometer' => $vehicle->odometer_reading ?? 0,
                'booking_status' => 'reserved',
                'payment_status' => 'pending',
            ]);
            $booking->booking_code = $bookingCode;
            $booking->user_id = $customer->id;
            $booking->payment_due_at = now()->addHours(24);

            if (! $booking->save()) {
                throw ValidationException::withMessages(['booking' => 'We could not save your reservation. Please try again.']);
            }

            return $booking;
        }, attempts: 3);

        return redirect()->route('frontend.index')->with('fleet_booking_code', $booking->booking_code);
    }

    private function hasBookablePrice(Vehicle $vehicle): bool
    {
        return $vehicle->rate_per_unit !== null
            && (float) $vehicle->rate_per_unit > 0
            && (float) $vehicle->rate_per_unit <= 9999999999.99;
    }
}
