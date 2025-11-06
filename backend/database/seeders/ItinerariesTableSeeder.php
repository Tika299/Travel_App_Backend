<?php
namespace Database\Seeders;

use App\Models\Itinerary;
use Illuminate\Database\Seeder;

class ItinerariesTableSeeder extends Seeder
{
    public function run()
    {
        Itinerary::create([
            'user_id' => 2,
            'title' => 'Hành trình khám phá Sài Gòn 3 ngày',
            'start_date' => '2025-07-15',
            'end_date' => '2025-07-17',
            'budget' => 5000000,
            'people_count' => 2,
            'status' => 'published'
        ]);

        Itinerary::create([
            'user_id' => 3,
            'title' => 'Du lịch Đà Lạt cuối tuần',
            'start_date' => '2025-08-10',
            'end_date' => '2025-08-11',
            'budget' => 3000000,
            'people_count' => 4,
            'status' => 'published'
        ]);

        Itinerary::create([
            'user_id' => 1,
            'title' => 'Ẩm thực Hà Nội (bản nháp)',
            'start_date' => '2025-09-01',
            'end_date' => '2025-09-03',
            'budget' => 4000000,
            'people_count' => 2,
            'status' => 'draft'
        ]);

        Itinerary::create([
            'user_id' => 4,
            'title' => 'Biển Nha Trang 5 ngày',
            'start_date' => '2025-06-20',
            'end_date' => '2025-06-24',
            'budget' => 8000000,
            'people_count' => 3,
            'status' => 'published'
        ]);

        Itinerary::create([
            'user_id' => 2,
            'title' => 'Phượt miền Tây',
            'start_date' => '2025-10-05',
            'end_date' => '2025-10-08',
            'budget' => 6000000,
            'people_count' => 5,
            'status' => 'published'
        ]);
    }
}