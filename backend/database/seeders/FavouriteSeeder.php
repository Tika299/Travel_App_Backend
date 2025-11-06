<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Favourite;
use App\Models\User;
use App\Models\Hotel; // Ví dụ bạn có model Hotel

class FavouriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        $hotel = Hotel::first();

        if ($user && $hotel) {
            Favourite::create([
                'user_id' => $user->id,
                'favouritable_id' => $hotel->id,
                'favouritable_type' => Hotel::class,
            ]);
        }
    }
}
