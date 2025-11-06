<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-1 month', 'now');
        return [
            'user_id' => User::inRandomOrder()->value('id') ?? 1,
            'content' => $this->faker->paragraph(),
            'rating' => $this->faker->numberBetween(1, 5),
            'created_at' =>  $createdAt,
            'updated_at' => now(),
        ];
    }
}
