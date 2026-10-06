<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserNotification>
 */
class UserNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationType::NewEntry->value,
            'message' => fake()->sentence(),
            'is_read' => false,
        ];
    }
}
