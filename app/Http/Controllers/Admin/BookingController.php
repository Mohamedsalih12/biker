<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingFormRequest;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
        $booking = DB::transaction(function () use ($request): Booking {
            $booking = new Booking($request->validated());
            $booking->user_id = $request->user()->id;
            $booking->save();

            return $booking;
        });

        return redirect()->route('xpress.bookings.edit', $booking)->with('message', 'Booking created successfully.');
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

    public function update(BookingFormRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('update', $booking);
        DB::transaction(fn (): bool => $booking->update($request->validated()));

        return redirect()->route('xpress.bookings.index')->with('message', 'Booking updated successfully.');
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

    /** @return array{booking: Booking, selectedCustomer: ?User, vehicles: Collection<int, Vehicle>} */
    private function formData(Booking $booking): array
    {
        $customerId = filter_var(old('customer_id', $booking), FILTER_VALIDATE_INT);
        $booking->load('user:id,name');

        return [
            'booking' => $booking,
            'selectedCustomer' => $customerId ? User::query()->where('role', 'customer')->select(['id', 'name', 'aadhaar'])->find($customerId) : null,
            'vehicles' => Vehicle::query()->orderBy('registration_number')->get(['id', 'registration_number']),
        ];
    }
}
