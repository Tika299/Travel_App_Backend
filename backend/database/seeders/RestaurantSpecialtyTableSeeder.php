<?php
namespace Database\Seeders;

use App\Models\RestaurantSpecialty;
use Illuminate\Database\Seeder;

class RestaurantSpecialtyTableSeeder extends Seeder
{
    public function run()
    {
        RestaurantSpecialty::create([
            'restaurant_id' => 1,
            'specialty_id' => 2
        ]);

        RestaurantSpecialty::create([
            'restaurant_id' => 1,
            'specialty_id' => 4
        ]);

        RestaurantSpecialty::create([
            'restaurant_id' => 2,
            'specialty_id' => 1
        ]);

        RestaurantSpecialty::create([
            'restaurant_id' => 3,
            'specialty_id' => 3
        ]);

        RestaurantSpecialty::create([
            'restaurant_id' => 5,
            'specialty_id' => 5
        ]);
    }
}