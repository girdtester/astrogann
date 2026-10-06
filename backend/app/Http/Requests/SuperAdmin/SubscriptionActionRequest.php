<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Enums\RoleKind;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * activate / extend / expire a subscription, optionally with a manual payment (web and POST /api/admin/subscription).
 */
class SubscriptionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permissions::SUBSCRIPTIONS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'user_id' => [$this->route('user') ? 'prohibited' : 'required', 'integer',
                Rule::exists('users', 'id')->whereIn('role_id', Role::query()->ofKind(RoleKind::User)->pluck('id')->all())],
            'action' => ['required', Rule::in(['activate', 'extend', 'expire'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['required_if:action,activate,extend', 'nullable', 'date',
                Rule::when($this->filled('start_date'), ['after_or_equal:start_date'])],
            'notes' => ['nullable', 'string', 'max:255'],
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'payment.payment_date' => ['nullable', 'date'],
            'payment.transaction_ref' => ['nullable', 'string', 'max:100'],
            'payment.status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ];
    }

    public function messages(): array
    {
        return ['end_date.required_if' => 'Choose the end date.'];
    }
}
