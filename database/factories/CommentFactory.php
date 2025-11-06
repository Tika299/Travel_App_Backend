<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        $createdAt = $this->faker->dateTimeBetween('-1 month', 'now');
        return [
            'user_id' => User::inRandomOrder()->value('id') ?? 1,
            'content' => $this->faker->paragraph(),
            'created_at' =>  $createdAt,
            'updated_at' => now(),
        ];
    }
}
