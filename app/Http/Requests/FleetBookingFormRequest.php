<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FleetBookingFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');
        abort_unless($vehicle instanceof Vehicle && $vehicle->status !== 'decommissioned', 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if (is_string($this->input('aadhaar'))) {
            $normalized['aadhaar'] = str_replace([' ', '-'], '', $this->input('aadhaar'));
        }

        if (is_string($this->input('phone'))) {
            $normalized['phone'] = str_replace([' ', '-', '(', ')'], '', $this->input('phone'));
        }

        $this->merge($normalized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'aadhaar' => ['bail', 'required', 'string', 'regex:/^[0-9]{12}$/'],
            'start_date' => [
                'required', 'date_format:Y-m-d', 'after_or_equal:today',
                'before_or_equal:'.today()->addDays(2)->toDateString(),
            ],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, including the country code if needed.',
            'aadhaar.regex' => 'Enter your 12-digit Aadhaar number.',
            'start_date.after_or_equal' => 'The start date must be today or later.',
            'start_date.before_or_equal' => 'The start date must be within the next 2 days.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
