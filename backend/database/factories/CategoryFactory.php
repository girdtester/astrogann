<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::upper(Str::slug($name, '_')),
            'status' => ActiveStatus::Active->value,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ActiveStatus::Inactive->value]);
    }
}
