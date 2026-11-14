<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-vehicles') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:255', Rule::unique('vehicles')->ignore($this->route('vehicle'))],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'chassis_number' => ['nullable', 'string', 'max:255'],
            'battery_serial_number' => ['nullable', 'string', 'max:255'],
            'odometer_reading' => ['nullable', 'integer', 'min:0', 'max:9223372036854775807'],
            'status' => ['sometimes', 'required', Rule::in(Vehicle::STATUSES)],
            'purchase_date' => ['nullable', 'date_format:Y-m-d'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'insurance_policy_number' => ['nullable', 'string', 'max:255'],
            'insurance_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'damages' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
