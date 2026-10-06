<?php

namespace App\Http\Requests\Api;

use App\Enums\DeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/auth/login — email, password, device_type (web|mobile), optional fcm_token.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_type' => ['required', Rule::enum(DeviceType::class)],
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
