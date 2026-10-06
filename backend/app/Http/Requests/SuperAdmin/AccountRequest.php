<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\ActiveStatus;
use App\Enums\RoleKind;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create / update a staff or user/trader account (web and API). Password is required on create only.
 * Route permission (`can:staff.manage` / `can:users.manage`) is checked by middleware; the
 * escalation rules are enforced by AccountService.
 */
class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $account = $this->route('account') ?? $this->route('user') ?? $this->route('admin');
        $ignoreId = $account instanceof User ? $account->id : (is_numeric($account) ? (int) $account : null);
        $creating = $ignoreId === null;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($ignoreId)],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::enum(ActiveStatus::class)],
            // Staff only (ignored for traders):
            'role_id' => ['sometimes', 'nullable', 'integer',
                Rule::exists('roles', 'id')->where('kind', RoleKind::Staff->value)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
