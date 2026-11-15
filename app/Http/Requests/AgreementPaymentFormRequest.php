<?php

namespace App\Http\Requests;

use App\Models\Booking;

class AgreementPaymentFormRequest extends PaymentFormRequest
{
    protected function prepareForValidation(): void
    {
        /** @var Booking $booking */
        $booking = $this->route('booking');

        $this->merge([
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'vehicle_id' => $booking->vehicle_id,
            'payment_date' => today()->toDateString(),
            'notes' => null,
        ]);
    }
}
