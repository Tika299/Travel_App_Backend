<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReviewImage>
 */
class ReviewImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $images = [
            'post_review_1.jpg',
            'post_review_2.jpg',
            'post_review_3.jpg',
            'post_review_4.jpg',
            'post_review_5.jpg',
            'post_review_6.jpg'
        ];

        return [
            'review_id' => Review::inRandomOrder()->value('id'),
            'image_path' => 'http://localhost:8000/storage/review_images/' . $this->faker->randomElement($images),
            'is_main' => false,
        ];
    }
}
