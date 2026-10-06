<?php

namespace App\Http\Requests\SuperAdmin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permissions::SETTINGS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:60'],
            'entries_limit_active' => ['required', 'integer', 'min:1', 'max:100'],
            'entries_limit_expired' => ['required', 'integer', 'min:0', 'max:100', 'lte:entries_limit_active'],
            'reminder_days' => ['required', 'integer', 'min:1', 'max:60'],
            'show_more_text' => ['required', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:100', 'regex:/^[0-9+ ,\-]+$/'],
            'contact_whatsapp' => ['nullable', 'string', 'max:100', 'regex:/^[0-9+ ,]+$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'notice_text' => ['nullable', 'string', 'max:1000'],
            'notify_expired_users' => ['nullable', 'boolean'],
            'allow_registration' => ['nullable', 'boolean'],
        ];
    }
}
