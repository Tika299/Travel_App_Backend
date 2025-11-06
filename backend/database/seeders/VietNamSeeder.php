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
            HCMCheckinPlacesSeeder::class,
            HCMHotelsSeeder::class,
            HCMRestaurantsSeeder::class,
            
            // Bình Định
            BinhDinhCheckinPlacesSeeder::class,
            BinhDinhHotelsSeeder::class,
            BinhDinhRestaurantsSeeder::class,
            
            // Hà Nội
            HaNoiCheckinPlacesSeeder::class,
            HaNoiHotelsSeeder::class,
            HaNoiRestaurantsSeeder::class,
            
            // Đà Nẵng
            DaNangCheckinPlacesSeeder::class,
            DaNangHotelsSeeder::class,
            DaNangRestaurantsSeeder::class,
            
            // Hải Phòng
            HaiPhongCheckinPlacesSeeder::class,
            HaiPhongHotelsSeeder::class,
            HaiPhongRestaurantsSeeder::class,
            
            // Khánh Hòa
            KhanhHoaCheckinPlacesSeeder::class,
            KhanhHoaHotelsSeeder::class,
            KhanhHoaRestaurantsSeeder::class,
            
            // Kiên Giang
            KienGiangHotelsSeeder::class, 
            KienGiangRestaurantsSeeder::class, 
            KienGiangCheckinPlacesSeeder::class, 

            HaLongHotelsSeeder::class, 
            HaLongRestaurantsSeeder::class, 
            HaLongCheckinPlacesSeeder::class,

            SaPaHotelsSeeder::class, 
            SaPaRestaurantsSeeder::class, 
            SaPaCheckinPlacesSeeder::class,

            CanThoHotelsSeeder::class, 
            CanThoRestaurantsSeeder::class, 
            CanThoCheckinPlacesSeeder::class,

            DaLatHotelsSeeder::class,
            DaLatRestaurantsSeeder::class, 
            DaLatCheckinPlacesSeeder::class, 

        ]);
    }
}
