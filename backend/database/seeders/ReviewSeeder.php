<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all check-in places from the checkin_places table
        $checkinPlaces = DB::table('checkin_places')->get();

        // Generate 50 reviews for each check-in place
        foreach ($checkinPlaces as $place) {
            Review::factory()->count(50)->create([
                'reviewable_id' => $place->id,
                'reviewable_type' => 'App\Models\CheckinPlace',
            ])->each(function ($review) {
                $imagesCount = rand(1, 5);
                // Create associated review images
                ReviewImage::factory()->count($imagesCount)->create([
                    'review_id' => $review->id,
                ]);
            });
        }

        // Get all hotels from the hotels table
        $hotels = DB::table('hotels')->get();

        // Generate 50 reviews for each hotel
        foreach ($hotels as $hotel) {
            Review::factory()->count(50)->create([
                'reviewable_id' => $hotel->id,
                'reviewable_type' => 'App\Models\Hotel',
            ])->each(function ($review) {
                $imagesCount = rand(1, 5);
                // Create associated review images
                ReviewImage::factory()->count($imagesCount)->create([
                    'review_id' => $review->id,
                ]);
            });
        }

        // Comments cho mỗi review
        Review::all()->each(function ($review) {
            Comment::factory()->count(5)->create([
                'commentable_id' => $review->id,
                'commentable_type' => '\App\Models\Review',
            ]);
        });
    }
}
