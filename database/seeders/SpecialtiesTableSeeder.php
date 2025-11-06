<?php
namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;

class SpecialtiesTableSeeder extends Seeder
{
    public function run()
    {
        Specialty::create([
            'name' => 'Phở Hà Nội',
            'description' => 'Món phở truyền thống của Hà Nội',
            'region' => 'Miền Bắc',
            'price_range' => '30,000 - 70,000 VND'
        ]);

        Specialty::create([
            'name' => 'Bánh mì Sài Gòn',
            'description' => 'Bánh mì đa dạng nhân với pate đặc trưng',
            'region' => 'Miền Nam',
            'price_range' => '15,000 - 50,000 VND'
        ]);

        Specialty::create([
            'name' => 'Bún bò Huế',
            'description' => 'Món bún đặc trưng xứ Huế với nước dùng đậm đà',
            'region' => 'Miền Trung',
            'price_range' => '35,000 - 80,000 VND'
        ]);

        Specialty::create([
            'name' => 'Cao lầu Hội An',
            'description' => 'Món mì đặc sản chỉ có ở Hội An',
            'region' => 'Hội An',
            'price_range' => '40,000 - 90,000 VND'
        ]);

        Specialty::create([
            'name' => 'Bánh xèo miền Tây',
            'description' => 'Bánh xèo giòn với nhân hải sản phong phú',
            'region' => 'Miền Tây',
            'price_range' => '25,000 - 60,000 VND'
        ]);
    }
}