<?php

namespace App\Http\Requests;

use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Self sign-up (web /register and POST /api/auth/register, DECISIONS D-85).
 * Only allowed while the Super Admin setting "allow_registration" is on.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(SettingsService::class)->bool('allow_registration');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+ \-]{6,30}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_type' => ['sometimes', 'in:web,mobile'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this e-mail already exists. Please log in or use "Forgot password".',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'Sign-up is closed. Please contact the admin for an account.');
    }
}
