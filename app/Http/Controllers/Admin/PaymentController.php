<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentFormRequest;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Payment::class);
        activity('payment')->causedBy(auth()->user())->event('view')
            ->withProperties(['action' => 'view'])->log('Payments viewed');

        return view('admin.payments.index');
    }

    public function create(): View
    {
        Gate::authorize('create', Payment::class);

        return view('admin.payments.create', $this->formData(new Payment));
    }

    public function store(PaymentFormRequest $request): RedirectResponse
    {
        Gate::authorize('create', Payment::class);
        $this->savePayment($request, new Payment);

        return redirect()->route('xpress.payments.index')->with('message', 'Payment created successfully.');
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);
        $payment->load(['booking:id,booking_code', 'customer:id,name', 'vehicle:id,registration_number', 'user:id,name']);
        activity('payment')->causedBy(auth()->user())->performedOn($payment)->event('view')
            ->withProperties(['action' => 'view'])->log('Payment viewed');

        return view('admin.payments.show', compact('payment'));
    }

    public function edit(Payment $payment): View
    {
        Gate::authorize('update', $payment);

        return view('admin.payments.edit', $this->formData($payment));
    }

    public function update(PaymentFormRequest $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('update', $payment);
        $this->savePayment($request, $payment);

        return redirect()->route('xpress.payments.index')->with('message', 'Payment updated successfully.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        Gate::authorize('delete', $payment);
        try {
            DB::transaction(function () use ($payment): void {
                if ($payment->delete() !== true) {
                    throw ValidationException::withMessages(['payment' => 'This payment could not be deleted.']);
                }
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['payment' => 'This payment is linked to other records and cannot be deleted.']);
        }

        return redirect()->route('xpress.payments.index')->with('message', 'Payment deleted successfully.');
    }

    public function bookings(Request $request): JsonResponse
    {
        Gate::authorize('create', Payment::class);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $term = $validated['q'] ?? '';
        $bookings = Booking::query()->with(['customer:id,name', 'vehicle:id,registration_number'])
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('booking_code', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn (Builder $customer): Builder => $customer->where('name', 'like', '%'.$term.'%'))
                        ->orWhereHas('vehicle', fn (Builder $vehicle): Builder => $vehicle->where('registration_number', 'like', '%'.$term.'%'));
                });
            })->orderByDesc('id')->simplePaginate(20, ['id', 'booking_code', 'customer_id', 'vehicle_id']);

        return response()->json([
            'results' => $bookings->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'text' => $booking->booking_code.' — '.$booking->customer->name.' — '.$booking->vehicle->registration_number,
                'customer_id' => $booking->customer_id, 'customer_name' => $booking->customer->name,
                'vehicle_id' => $booking->vehicle_id, 'vehicle_registration' => $booking->vehicle->registration_number,
            ])->all(),
            'pagination' => ['more' => $bookings->hasMorePages()],
        ]);
    }

    private function savePayment(PaymentFormRequest $request, Payment $payment): void
    {
        $validated = $request->validated();
        DB::transaction(function () use ($request, $payment, $validated): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($validated['booking_id']);
            foreach (['customer_id', 'vehicle_id'] as $field) {
                if ((int) $validated[$field] !== $booking->{$field}) {
                    throw ValidationException::withMessages([$field => 'The booking has changed. Select it again and retry.']);
                }
            }
            $payment->fill($validated);
            if (! $payment->exists) {
                $payment->user_id = $request->user()->id;
            }
            if ($payment->save() !== true) {
                throw ValidationException::withMessages(['payment' => 'The payment could not be saved. Please try again.']);
            }
        });
    }

    /** @return array{payment: Payment, selectedBooking: ?Booking} */
    private function formData(Payment $payment): array
    {
        $bookingId = filter_var(old('booking_id', $payment), FILTER_VALIDATE_INT);
        $payment->load('user:id,name');

        return [
            'payment' => $payment,
            'selectedBooking' => $bookingId ? Booking::query()->with(['customer:id,name', 'vehicle:id,registration_number'])->find($bookingId) : null,
        ];
    }
}
