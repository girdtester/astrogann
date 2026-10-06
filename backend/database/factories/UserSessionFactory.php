<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserSession>
 */
class UserSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_type' => DeviceType::Web->value,
            'is_active' => true,
        ];
    }
}
