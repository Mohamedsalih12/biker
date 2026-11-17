<?php

namespace App\Http\Requests;

use App\Models\MaintenanceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceLogFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-maintenance-logs') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post')) {
            $this->mergeIfMissing(['delivered_date' => today()->toDateString(), 'status' => 'in_progress']);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'service_description' => ['required', 'string', 'max:10000'],
            'delivered_date' => ['required', 'date_format:Y-m-d'],
            'expected_completion_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:delivered_date'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'vendor_name' => ['required', Rule::in(MaintenanceLog::VENDORS)],
            'status' => ['required', Rule::in(MaintenanceLog::STATUSES)],
        ];
    }
}
