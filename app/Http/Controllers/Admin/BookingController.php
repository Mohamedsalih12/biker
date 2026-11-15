<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingFormRequest;
use App\Jobs\SendBookingTelegramNotification;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BookingController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Booking::class);
        activity('booking')->causedBy(auth()->user())->event('view')
            ->withProperties(['action' => 'view'])->log('Bookings viewed');

        return view('admin.bookings.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Booking::class);

        return view('admin.bookings.create', $this->formData(new Booking));
    }

    public function store(BookingFormRequest $request): RedirectResponse
    {
        Gate::authorize('create', Booking::class);
        $validated = $request->validated();
        $booking = DB::transaction(function () use ($request, $validated): Booking {
            $this->ensureVehicleAvailable((int) $validated['vehicle_id']);
            $booking = new Booking($validated);
            $booking->user_id = $request->user()->id;
            $booking->save();
            $this->syncVehicleOdometer($booking, $validated);
            $this->syncCustomerNotes($booking);
            $this->notifyAdmin($booking->refresh(), 'created');

            return $booking;
        });

        return redirect()->route('xpress.bookings.agreement', $booking)->with('message', 'Booking created successfully.');
    }

    public function show(Booking $booking): View
    {
        Gate::authorize('view', $booking);
        $booking->load(['customer:id,name', 'vehicle:id,registration_number', 'user:id,name']);
        activity('booking')->causedBy(auth()->user())->performedOn($booking)->event('view')
            ->withProperties(['action' => 'view'])->log('Booking viewed');

        return view('admin.bookings.show', compact('booking'));
    }

    public function edit(Booking $booking): View
    {
        Gate::authorize('update', $booking);

        return view('admin.bookings.edit', $this->formData($booking));
    }

    public function agreement(Booking $booking): View
    {
        Gate::authorize('view', $booking);
        $booking->load(['customer:id,name,aadhaar,address,phone', 'vehicle:id,brand,model,registration_number']);
        activity('booking')->causedBy(auth()->user())->performedOn($booking)->event('view')
            ->withProperties(['action' => 'view'])->log('Booking agreement draft viewed');

        return view('admin.agreement.agreement', compact('booking'));
    }

    public function downloadAgreement(Booking $booking): Response
    {
        Gate::authorize('view', $booking);
        $booking->load(['customer:id,name,aadhaar,address,phone', 'vehicle:id,brand,model,registration_number']);

        $response = Pdf::loadView('admin.agreement.agreement', ['booking' => $booking, 'isPdf' => true])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isJavascriptEnabled' => false])
            ->download("agreement-{$booking->booking_code}.pdf");

        activity('booking')->causedBy(auth()->user())->performedOn($booking)->event('download')
            ->withProperties(['action' => 'download'])->log('Booking agreement PDF downloaded');

        return $response;
    }

    public function update(BookingFormRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('update', $booking);
        $validated = $request->validated();
        DB::transaction(function () use ($booking, $validated): void {
            $this->ensureVehicleAvailable((int) $validated['vehicle_id'], $booking);
            $booking->refreshForUpdate();
            $booking->update($validated);
            $bookingChanged = $booking->wasChanged(array_keys($validated));
            $this->syncVehicleOdometer($booking, $validated);
            $this->syncCustomerNotes($booking);

            if ($bookingChanged) {
                $this->notifyAdmin($booking, 'updated');
            }
        });

        return redirect()->route('xpress.bookings.agreement', $booking)->with('message', 'Booking updated successfully.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        Gate::authorize('delete', $booking);
        try {
            DB::transaction(function () use ($booking): void {
                if ($booking->delete() !== true) {
                    throw ValidationException::withMessages(['booking' => 'This booking could not be deleted.']);
                }
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['booking' => 'This booking is linked to other records and cannot be deleted.']);
        }

        return redirect()->route('xpress.bookings.index')->with('message', 'Booking deleted successfully.');
    }

    private function notifyAdmin(Booking $booking, string $event): void
    {
        if (blank(config('services.telegram.bot_token')) || blank(config('services.telegram.admin_chat_id'))) {
            return;
        }

        $job = new SendBookingTelegramNotification($booking, $event, auth()->user()->name);

        DB::afterCommit(function () use ($job): void {
            try {
                dispatch($job);
            } catch (Throwable) {
                report(new RuntimeException('Booking saved, but the Telegram notification could not be queued.'));
            }
        });
    }

    private function ensureVehicleAvailable(int $vehicleId, ?Booking $booking = null): void
    {
        Vehicle::query()->whereKey($vehicleId)->lockForUpdate()->firstOrFail();

        if (Booking::query()->where('vehicle_id', $vehicleId)->whereIn('booking_status', ['taken', 'reserved'])
            ->when($booking !== null, fn (Builder $query): Builder => $query->whereKeyNot($booking->id))
            ->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => 'This vehicle already has a taken or reserved booking.']);
        }
    }

    private function syncCustomerNotes(Booking $booking): void
    {
        if (blank($booking->return_notes) || (! $booking->wasRecentlyCreated && ! $booking->wasChanged(['return_notes', 'customer_id']))) {
            return;
        }

        $customer = $booking->customer()->lockForUpdate()->firstOrFail();
        $customer->notes = filled($customer->notes) ? $customer->notes."\n".$booking->return_notes : $booking->return_notes;

        if (! $customer->save()) {
            throw ValidationException::withMessages(['return_notes' => 'The customer notes could not be updated.']);
        }
    }

    /** @param array<string, mixed> $validated */
    private function syncVehicleOdometer(Booking $booking, array $validated): void
    {
        if (! array_key_exists('end_odometer', $validated) || $booking->end_odometer === null) {
            return;
        }

        if (! $booking->vehicle()->firstOrFail()->update(['odometer_reading' => $booking->end_odometer])) {
            throw ValidationException::withMessages(['end_odometer' => 'The vehicle odometer could not be updated.']);
        }
    }

    /** @return array{booking: Booking, selectedCustomer: ?User, vehicles: Collection<int, Vehicle>} */
    private function formData(Booking $booking): array
    {
        $customerId = filter_var(old('customer_id', $booking), FILTER_VALIDATE_INT);
        $booking->load('user:id,name');

        return [
            'booking' => $booking,
            'selectedCustomer' => $customerId ? User::query()->where('role', 'customer')->select(['id', 'name', 'aadhaar'])->find($customerId) : null,
            'vehicles' => Vehicle::query()->whereDoesntHave('bookings', function (Builder $query) use ($booking): void {
                $query->whereIn('booking_status', ['taken', 'reserved'])
                    ->when($booking->exists, fn (Builder $query): Builder => $query->whereKeyNot($booking->id));
            })->orderBy('registration_number')->get(['id', 'registration_number', 'odometer_reading']),
        ];
    }
}
