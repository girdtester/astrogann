<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Entry>
 */
class EntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'admin_id' => User::factory()->admin(),
            'image_path' => 'uploads/entries/placeholder.png',
            'entry_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'entry_time' => fake()->time('H:i:00'),
        ];
    }
}
