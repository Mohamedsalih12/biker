<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::authorize('isAdmin')->allowed();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Footer links accept only HTTP(S), excluding data and FTP URLs.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'website_name' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'meta_keyword' => ['nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number_1' => ['nullable', 'string', 'max:255'],
            'phone_number_2' => ['nullable', 'string', 'max:255'],
            'email_1' => ['nullable', 'email:filter', 'max:255'],
            'email_2' => ['nullable', 'email:filter', 'max:255'],
            'facebook' => ['nullable', 'url:http,https', 'max:255'],
            'twitter' => ['nullable', 'url:http,https', 'max:255'],
            'instagram' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'youtube' => ['nullable', 'url:http,https', 'max:255'],
        ];
    }
}
