<?php
namespace Database\Seeders;

use App\Models\Hotel;

use Illuminate\Database\Seeder;

class HotelsTableSeeder extends Seeder
{
    public function run()
    {
        Hotel::create([
            'name' => 'Vinpearl Luxury Đà Nẵng',
            'description' => 'Khách sạn 5 sao ven biển Đà Nẵng',
            'address' => '07 Trường Sa, Phường Hòa Hải, Quận Ngũ Hành Sơn, Đà Nẵng',
            'images' => '/public/img/Hotel-Vinpearl-Luxury-Da-Nang.jpg',
            'latitude' => 16.0600000,
            'longitude' => 108.2500000,
            'rating' => 4.8,
            'review_count' => 1500,
            'email' => 'reservation@vinpearl.com',
            'phone' => '1900 232389',
            'wheelchair_access' => true
        ]);

        Hotel::create([
            'name' => 'InterContinental Saigon',
            'description' => 'Khách sạn cao cấp tại trung tâm TP.HCM',
            'address' => 'Corner Hai Ba Trung St. & Le Duan Blvd, District 1, TP.HCM',
            'images' => '/public/img/Hotel-InterContinental-Saigon.jpg',
            'latitude' => 10.7820000,
            'longitude' => 106.7000000,
            'rating' => 4.7,
            'review_count' => 1200,
            'email' => 'reservations@icsaigon.com',
            'phone' => '028 3520 9999',
            'wheelchair_access' => true
        ]);

        Hotel::create([
            'name' => 'Mường Thanh Luxury Sapa',
            'description' => 'Khách sạn view núi tại thị trấn Sapa',
            'address' => 'Ngũ Chỉ Sơn, Thị trấn Sapa, Lào Cai',
            'images' => '/public/img/Hotel-Muong-Thanh-Luxury-Sapa.jpg',
            'latitude' => 22.3360000,
            'longitude' => 103.8440000,
            'rating' => 4.3,
            'review_count' => 800,
            'email' => 'info@muongthanhsapa.com',
            'phone' => '020 387 2999',
        ]);

        Hotel::create([
            'name' => 'Fusion Suite Phú Quốc',
            'description' => 'Resort sang trọng tại đảo Ngọc',
            'address' => 'Bãi Trường, Dương Tơ, Phú Quốc, Kiên Giang',
            'images' => '/public/img/Hotel-Fusion-Suite-Phu-Quoc.jpg',
            'latitude' => 10.2230000,
            'longitude' => 103.9670000,
            'rating' => 4.6,
            'review_count' => 950,
            'email' => 'booking@fusionphuquoc.com',
            'phone' => '029 769 9999',
            'wheelchair_access' => true
        ]);

        Hotel::create([
            'name' => 'Azerai La Residence Huế',
            'description' => 'Khách sạn di sản bên sông Hương',
            'address' => '05 Lê Lợi, Vĩnh Ninh, Thành phố Huế',
            'images' => '/public/img/Hotel-Azerai-La-Residence-Hue.jpg',
            'latitude' => 16.4700000,
            'longitude' => 107.5900000,
            'rating' => 4.5,
            'review_count' => 600,
            'email' => 'reservations@azerai.com',
            'phone' => '023 483 7475',
        ]);
    }
} 