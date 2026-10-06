<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'start_date' => today()->subDays(10)->toDateString(),
            'end_date' => today()->addDays(30)->toDateString(),
            'status' => SubscriptionStatus::Active->value,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'start_date' => today()->subDays(40)->toDateString(),
            'end_date' => today()->subDays(10)->toDateString(),
            'status' => SubscriptionStatus::Expired->value,
        ]);
    }

    public function endingIn(int $days): static
    {
        return $this->state(fn () => ['end_date' => today()->addDays($days)->toDateString()]);
    }
}
