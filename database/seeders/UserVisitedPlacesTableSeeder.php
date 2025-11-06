<?php
namespace Database\Seeders;

use App\Models\UserVisitedPlace;
use Illuminate\Database\Seeder;

class UserVisitedPlacesTableSeeder extends Seeder
{
    public function run()
    {
        UserVisitedPlace::create([
            'user_id' => 2,
            'place_type' => 'App\Models\Location',
            'place_id' => 1,
            'visited_at' => '2025-01-15 10:30:00'
        ]);

        UserVisitedPlace::create([
            'user_id' => 2,
            'place_type' => 'App\Models\Hotel',
            'place_id' => 1,
            'visited_at' => '2025-02-20 14:00:00'
        ]);

        UserVisitedPlace::create([
            'user_id' => 3,
            'place_type' => 'App\Models\Restaurant',
            'place_id' => 2,
            'visited_at' => '2025-03-10 19:30:00'
        ]);

        UserVisitedPlace::create([
            'user_id' => 4,
            'place_type' => 'App\Models\Location',
            'place_id' => 3,
            'visited_at' => '2025-04-05 11:00:00'
        ]);

        UserVisitedPlace::create([
            'user_id' => 1,
            'place_type' => 'App\Models\Hotel',
            'place_id' => 4,
            'visited_at' => '2025-05-12 16:45:00'
        ]);
    }
}