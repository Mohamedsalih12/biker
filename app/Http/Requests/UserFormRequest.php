<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user
            ? Gate::authorize('update', $user)->allowed()
            : Gate::authorize('create', User::class)->allowed();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255', $user ? Rule::unique('users', 'email')->ignore($user) : 'unique:users'],
        ];

        if ($user) {
            return $rules + ['active_status' => ['boolean']];
        }

        return $rules + [
            'password' => ['required', 'string', Password::default()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.required' => 'A password is required to secure your account.',
            'password.min' => 'Your password must be at least :min characters long.',
            'password.mixed' => 'Please include both uppercase and lowercase letters.',
            'password.numbers' => 'Your password needs at least one number.',
            'password.symbols' => 'Add a special character (like @, #, or $) for extra security.',
            'password.uncompromised' => 'This password has appeared in a data leak. Please choose a more unique one.',
        ];
    }
}
