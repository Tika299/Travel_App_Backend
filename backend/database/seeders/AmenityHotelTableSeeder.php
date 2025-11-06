<?php
namespace Database\Seeders;

use App\Models\AmenityHotel;
use Illuminate\Database\Seeder;

class AmenityHotelTableSeeder extends Seeder
{
    public function run()
    {
        // Vinpearl Luxury Đà Nẵng (ID: 1)
        AmenityHotel::create(['hotel_id' => 1, 'amenity_id' => 1]); // Wifi
        AmenityHotel::create(['hotel_id' => 1, 'amenity_id' => 2]); // Điều hòa
        AmenityHotel::create(['hotel_id' => 1, 'amenity_id' => 3]); // Hồ bơi
        AmenityHotel::create(['hotel_id' => 1, 'amenity_id' => 5]); // Nhà hàng
        AmenityHotel::create(['hotel_id' => 1, 'amenity_id' => 7]); // Spa

        // InterContinental Saigon (ID: 2)
        AmenityHotel::create(['hotel_id' => 2, 'amenity_id' => 1]); // Wifi
        AmenityHotel::create(['hotel_id' => 2, 'amenity_id' => 2]); // Điều hòa
        AmenityHotel::create(['hotel_id' => 2, 'amenity_id' => 6]); // Quầy bar
        AmenityHotel::create(['hotel_id' => 2, 'amenity_id' => 8]); // Phòng gym
        AmenityHotel::create(['hotel_id' => 2, 'amenity_id' => 10]); // Dịch vụ phòng

        // Mường Thanh Luxury Sapa (ID: 3)
        AmenityHotel::create(['hotel_id' => 3, 'amenity_id' => 1]); // Wifi
        AmenityHotel::create(['hotel_id' => 3, 'amenity_id' => 2]); // Điều hòa
        AmenityHotel::create(['hotel_id' => 3, 'amenity_id' => 4]); // Bãi đậu xe
        AmenityHotel::create(['hotel_id' => 3, 'amenity_id' => 5]); // Nhà hàng

        // Fusion Suite Phú Quốc (ID: 4)
        AmenityHotel::create(['hotel_id' => 4, 'amenity_id' => 1]); // Wifi
        AmenityHotel::create(['hotel_id' => 4, 'amenity_id' => 3]); // Hồ bơi
        AmenityHotel::create(['hotel_id' => 4, 'amenity_id' => 7]); // Spa
        AmenityHotel::create(['hotel_id' => 4, 'amenity_id' => 9]); // Thang máy

        // Azerai La Residence Huế (ID: 5)
        AmenityHotel::create(['hotel_id' => 5, 'amenity_id' => 1]); // Wifi
        AmenityHotel::create(['hotel_id' => 5, 'amenity_id' => 5]); // Nhà hàng
        AmenityHotel::create(['hotel_id' => 5, 'amenity_id' => 6]); // Quầy bar
        AmenityHotel::create(['hotel_id' => 5, 'amenity_id' => 10]); // Dịch vụ phòng
    }
}