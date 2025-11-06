<?php
namespace Database\Seeders;

use App\Models\ItineraryWeather;
use Illuminate\Database\Seeder;

class ItineraryWeatherTableSeeder extends Seeder
{
    public function run()
    {
        ItineraryWeather::create([
            'itinerary_id' => 1, // Hành trình Sài Gòn
            'weather_data_id' => 2 // Thời tiết TP.HCM
        ]);

        ItineraryWeather::create([
            'itinerary_id' => 2, // Du lịch Đà Lạt
            'weather_data_id' => 3 // Thời tiết Sapa (tạm dùng cho Đà Lạt)
        ]);

        ItineraryWeather::create([
            'itinerary_id' => 4, // Biển Nha Trang
            'weather_data_id' => 4 // Thời tiết Phú Quốc (tạm dùng cho Nha Trang)
        ]);

        ItineraryWeather::create([
            'itinerary_id' => 3, // Ẩm thực Hà Nội
            'weather_data_id' => 1 // Thời tiết Đà Nẵng (tạm dùng cho Hà Nội)
        ]);

        ItineraryWeather::create([
            'itinerary_id' => 5, // Phượt miền Tây
            'weather_data_id' => 2 // Thời tiết TP.HCM
        ]);
    }
}