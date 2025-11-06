<?php
namespace Database\Seeders;

use App\Models\ItineraryItem;
use Illuminate\Database\Seeder;

class ItineraryItemsTableSeeder extends Seeder
{
    public function run()
    {
        ItineraryItem::create([
            'itinerary_id' => 1,
            'item_type' => 'App\Models\Location',
            'item_id' => 2,
            'date' => '2025-07-15',
            'start_time' => '09:00',
            'end_time' => '11:30',
            'order' => 1,
            'notes' => 'Tham quan bảo tàng vào buổi sáng'
        ]);

        ItineraryItem::create([
            'itinerary_id' => 1,
            'item_type' => 'App\Models\Restaurant',
            'item_id' => 1,
            'date' => '2025-07-15',
            'start_time' => '12:00',
            'order' => 2,
            'notes' => 'Ăn trưa tại nhà hàng Ngon'
        ]);

        ItineraryItem::create([
            'itinerary_id' => 2,
            'item_type' => 'App\Models\Hotel',
            'item_id' => 3,
            'date' => '2025-08-10',
            'start_time' => '14:00',
            'order' => 1,
            'notes' => 'Nhận phòng khách sạn Mường Thanh'
        ]);

        ItineraryItem::create([
            'itinerary_id' => 3,
            'item_type' => 'App\Models\Restaurant',
            'item_id' => 2,
            'date' => '2025-09-01',
            'start_time' => '19:00',
            'order' => 1,
            'notes' => 'Tối thưởng thức ẩm thực Pháp'
        ]);

        ItineraryItem::create([
            'itinerary_id' => 4,
            'item_type' => 'App\Models\Location',
            'item_id' => 3,
            'date' => '2025-06-21',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'order' => 2,
            'notes' => 'Cả ngày tắm biển Mỹ Khê'
        ]);
    }
}