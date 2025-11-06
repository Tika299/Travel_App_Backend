<?php
namespace Database\Seeders;

use App\Models\Image;
use Illuminate\Database\Seeder;

class ImagesTableSeeder extends Seeder
{
    public function run()
    {
        Image::create([
            'imageable_type' => 'App\Models\Hotel',
            'imageable_id' => 1,
            'image_path' => 'hotels/vinpearl.jpg',
            'is_cover' => true
        ]);

        Image::create([
            'imageable_type' => 'App\Models\Restaurant',
            'imageable_id' => 1,
            'image_path' => 'restaurants/nha-hang-ngon.jpg',
            'is_cover' => true
        ]);

        Image::create([
            'imageable_type' => 'App\Models\Location',
            'imageable_id' => 1,
            'image_path' => 'locations/ho-hoan-kiem.jpg',
            'is_cover' => true
        ]);

        Image::create([
            'imageable_type' => 'App\Models\Hotel',
            'imageable_id' => 2,
            'image_path' => 'hotels/intercontinental.jpg',
            'is_cover' => true
        ]);

        Image::create([
            'imageable_type' => 'App\Models\Specialty',
            'imageable_id' => 1,
            'image_path' => 'specialties/pho-ha-noi.jpg',
            'is_cover' => true
        ]);
    }
}