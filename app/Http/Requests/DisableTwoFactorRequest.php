<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DisableTwoFactorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentUser = $this->user();

        if (! $currentUser) {
            return [];
        }

        $targetUser = User::find($this->input('user_id', $currentUser->id)) ?? $currentUser;

        if (! $targetUser->google2fa_secret || $currentUser->id !== $targetUser->id) {
            return [];
        }

        return [
            'otp' => ['required', 'digits:6'],
        ];
    }
}
