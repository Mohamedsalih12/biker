<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BookingCustomerFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('manage-bookings') && $user->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('aadhaar'))) {
            $this->merge(['aadhaar' => str_replace([' ', '-'], '', $this->input('aadhaar'))]);
        }
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
            'phone' => ['required', 'string', 'max:30'],
            'aadhaar' => ['bail', 'required', 'string', 'regex:/^[0-9]{12}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::query()->whereRaw("REPLACE(REPLACE(aadhaar, ' ', ''), '-', '') = ?", [$value])->exists()) {
                        $fail('A customer with this Aadhaar already exists. Search for the existing customer.');
                    }
                },
            ],
        ];
    }
}
