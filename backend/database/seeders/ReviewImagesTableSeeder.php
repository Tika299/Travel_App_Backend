<?php
namespace Database\Seeders;

use App\Models\ReviewImage;
use Illuminate\Database\Seeder;

class ReviewImagesTableSeeder extends Seeder
{
    public function run()
    {
        ReviewImage::create([
            'review_id' => 1,
            'image_path' => 'reviews/hotel1.jpg',
            'is_webcam' => false
        ]);

        ReviewImage::create([
            'review_id' => 1,
            'image_path' => 'reviews/hotel2.jpg',
            'is_webcam' => true
        ]);

        ReviewImage::create([
            'review_id' => 2,
            'image_path' => 'reviews/food1.jpg',
            'is_webcam' => false
        ]);

        ReviewImage::create([
            'review_id' => 3,
            'image_path' => 'reviews/lake1.jpg',
            'is_webcam' => true
        ]);

        ReviewImage::create([
            'review_id' => 5,
            'image_path' => 'reviews/hotel3.jpg',
            'is_webcam' => false
        ]);
    }
}