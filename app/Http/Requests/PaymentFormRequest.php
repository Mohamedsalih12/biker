<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-payments') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(Payment::METHODS)],
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['booking_id', 'customer_id', 'vehicle_id'])) {
                return;
            }
            $booking = Booking::query()->findOrFail($this->integer('booking_id'));
            foreach (['customer_id', 'vehicle_id'] as $field) {
                if ($this->integer($field) !== $booking->{$field}) {
                    $validator->errors()->add($field, 'The selected '.str_replace('_id', '', $field).' must match the booking.');
                }
            }
        }];
    }
}
