<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VietNamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // Hồ Chí Minh
            HCMCheckInPlacesSeeder::class,
            HCMHotelsSeeder::class,
            HCMRestaurantsSeeder::class,
            
            // Bình Định
            BinhDinhCheckInPlacesSeeder::class,
            BinhDinhHotelsSeeder::class,
            BinhDinhRestaurantsSeeder::class,
            
            // Hà Nội
            HaNoiCheckInPlacesSeeder::class,
            HaNoiHotelsSeeder::class,
            HaNoiRestaurantsSeeder::class,
            
            // Đà Nẵng
            DaNangCheckInPlacesSeeder::class,
            DaNangHotelsSeeder::class,
            DaNangRestaurantsSeeder::class,
            
            // Hải Phòng
            HaiPhongCheckInPlacesSeeder::class,
            HaiPhongHotelsSeeder::class,
            HaiPhongRestaurantsSeeder::class,
            
            // Khánh Hòa
            KhanhHoaCheckInPlacesSeeder::class,
            KhanhHoaHotelsSeeder::class,
            KhanhHoaRestaurantsSeeder::class,
            
            // Kiên Giang
            KienGiangHotelsSeeder::class, 
            KienGiangRestaurantsSeeder::class, 
            KienGiangCheckInPlacesSeeder::class, 

            HaLongHotelsSeeder::class, 
            HaLongRestaurantsSeeder::class, 
            HaLongCheckInPlacesSeeder::class,

            SaPaHotelsSeeder::class, 
            SaPaRestaurantsSeeder::class, 
            SaPaCheckInPlacesSeeder::class,

            CanThoHotelsSeeder::class, 
            CanThoRestaurantsSeeder::class, 
            CanThoCheckInPlacesSeeder::class,

            DaLatHotelsSeeder::class,
            DaLatRestaurantsSeeder::class, 
            DaLatCheckInPlacesSeeder::class, 

        ]);
    }
}
