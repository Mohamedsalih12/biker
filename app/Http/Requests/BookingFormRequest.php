<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-bookings') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'rental_type' => ['sometimes', 'required', Rule::in(Booking::RENTAL_TYPES)],
            'rate_per_unit' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'actual_return_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_odometer' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            'end_odometer' => ['nullable', 'integer', 'min:0', 'max:9223372036854775807', 'gte:start_odometer'],
            'status' => ['sometimes', 'required', Rule::in(Booking::STATUSES)],
            'handover_notes' => ['nullable', 'string', 'max:10000'],
            'return_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
