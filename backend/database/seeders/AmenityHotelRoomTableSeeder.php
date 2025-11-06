<?php
namespace Database\Seeders;

use App\Models\AmenityHotelRoom;
use Illuminate\Database\Seeder;

class AmenityHotelRoomTableSeeder extends Seeder
{
    public function run()
    {
        // Phòng Deluxe hướng biển (ID: 1)
        AmenityHotelRoom::create(['hotel_room_id' => 1, 'amenity_id' => 1]); // Wifi
        AmenityHotelRoom::create(['hotel_room_id' => 1, 'amenity_id' => 2]); // Điều hòa
        AmenityHotelRoom::create(['hotel_room_id' => 1, 'amenity_id' => 10]); // Dịch vụ phòng

        // Phòng Superior (ID: 2)
        AmenityHotelRoom::create(['hotel_room_id' => 2, 'amenity_id' => 1]); // Wifi
        AmenityHotelRoom::create(['hotel_room_id' => 2, 'amenity_id' => 2]); // Điều hòa

        // Suite Executive (ID: 3)
        AmenityHotelRoom::create(['hotel_room_id' => 3, 'amenity_id' => 1]); // Wifi
        AmenityHotelRoom::create(['hotel_room_id' => 3, 'amenity_id' => 2]); // Điều hòa
        AmenityHotelRoom::create(['hotel_room_id' => 3, 'amenity_id' => 10]); // Dịch vụ phòng

        // Phòng Family (ID: 4)
        AmenityHotelRoom::create(['hotel_room_id' => 4, 'amenity_id' => 1]); // Wifi
        AmenityHotelRoom::create(['hotel_room_id' => 4, 'amenity_id' => 2]); // Điều hòa

        // Villa hồ bơi riêng (ID: 5)
        AmenityHotelRoom::create(['hotel_room_id' => 5, 'amenity_id' => 1]); // Wifi
        AmenityHotelRoom::create(['hotel_room_id' => 5, 'amenity_id' => 2]); // Điều hòa
        AmenityHotelRoom::create(['hotel_room_id' => 5, 'amenity_id' => 3]); // Hồ bơi
        AmenityHotelRoom::create(['hotel_room_id' => 5, 'amenity_id' => 10]); // Dịch vụ phòng
    }
}