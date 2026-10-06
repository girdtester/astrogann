<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permissions::SUBSCRIPTIONS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'payment_date' => ['required', 'date'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(PaymentStatus::class)],
        ];
    }
}
