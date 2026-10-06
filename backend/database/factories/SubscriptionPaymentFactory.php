<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomElement([999, 1999, 4999]),
            'payment_date' => today()->toDateString(),
            'transaction_ref' => 'TXN'.fake()->unique()->numerify('########'),
            'status' => PaymentStatus::Paid->value,
        ];
    }
}
