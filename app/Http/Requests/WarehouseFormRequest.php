<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WarehouseFormRequest extends FormRequest
{
    /**
     * Determine if the current user is authenticated.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $warehouseId = optional($this->route('warehouse'))->id;

        return [
            'user_id' => 'required|integer',
            'warehouse_code' => 'required|string|max:255|unique:warehouses,warehouse_code,'.$warehouseId,
            'warehouse_name' => 'required|string|max:255',
            'active_status' => 'required|string|in:Active,Inactive',
        ];
    }

    /**
     * Get custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'warehouse_code.required' => 'The warehouse code is required.',
        ];
    }
}
