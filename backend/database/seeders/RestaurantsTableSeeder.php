<?php
namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class RestaurantsTableSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('restaurants')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Restaurant::create([
            'name' => 'Nhà hàng Ngon',
            'description' => 'Phục vụ các món ăn truyền thống Việt Nam',
            'address' => '160 Pasteur, Bến Nghé, Quận 1, TP.HCM',
            'latitude' => 10.779722,
            'longitude' => 106.701944,
            'rating' => 3.6,
            'price_range' => '100,000 - 300,000 VND',
            'image' => 'image/nhahangngon.jpg',
        ]);

        Restaurant::create([
            'name' => 'La Verticale',
            'description' => 'Nhà hàng Pháp tại Hà Nội',
            'address' => '19 Ngô Văn Sở, Hoàn Kiếm, Hà Nội',
            'latitude' => 21.029167,
            'longitude' => 105.850833,
            'rating' => 4.5,
            'price_range' => '500,000 - 1,500,000 VND',
            'image' => 'image/LaVerticale.jpg',
        ]);

        Restaurant::create([
            'name' => 'Bếp Mẹ Ỉn',
            'description' => 'Ẩm thực Huế đặc sắc',
            'address' => '10 Nguyễn Thị Minh Khai, Phú Hội, Huế',
            'latitude' => 16.468056,
            'longitude' => 107.590833,
            'rating' => 4.4,
            'price_range' => '150,000 - 400,000 VND',
            'image' => 'image/Bepmein.jpg',
        ]);

        Restaurant::create([
            'name' => 'Quán Ăn Ngon Đà Nẵng',
            'description' => 'Hải sản tươi sống và đặc sản miền Trung',
            'address' => 'Lô 12-13 Võ Nguyên Giáp, Sơn Trà, Đà Nẵng',
            'latitude' => 16.063889,
            'longitude' => 108.241667,
            'rating' => 4.3,
            'price_range' => '200,000 - 600,000 VND',
            'image' => 'image/Quananngondanang.jpg',
        ]);

        Restaurant::create([
            'name' => 'The Deck Saigon',
            'description' => 'Nhà hàng sang trọng bên sông Sài Gòn',
            'address' => '38 Nguyễn Ư Dĩ, Thảo Điền, Quận 2, TP.HCM',
            'latitude' => 10.796944,
            'longitude' => 106.732778,
            'rating' => 4.7,
            'price_range' => '800,000 - 2,000,000 VND',
            'image' => 'image/the-deck-saigon.jpg',
        ]);
        //
        Restaurant::create([
    'name' => 'Nhà hàng Horizon Group 1',
    'description' => 'Không gian ấm cúng và món ăn ngon',
    'address' => '58219 Wilson Key, Lake Natalie, SC 58045',
    'latitude' => 15.342131,
    'longitude' => 104.214316,
    'rating' => 3.9,
    'price_range' => '100,000 - 300,000 VND',
    'image' => 'image/Horizon.jpg',
]);

Restaurant::create([
    'name' => 'Nhà hàng Emerson PLC 2',
    'description' => 'Phong cách hiện đại và phục vụ tận tình',
    'address' => '38757 Bailey Plain Suite 768, Port Joshuaside, AZ 10441',
    'latitude' => 20.285446,
    'longitude' => 103.214823,
    'rating' => 4.6,
    'price_range' => '200,000 - 600,000 VND',
    'image' => 'image/Emerson.jpg',
]);

Restaurant::create([
    'name' => 'Nhà hàng Mendoza-Wade 3',
    'description' => 'Địa điểm lý tưởng cho gia đình và bạn bè',
    'address' => '21630 Nicole Plain Apt. 118, Port Lisaport, DE 86703',
    'latitude' => 12.949385,
    'longitude' => 109.192347,
    'rating' => 4.0,
    'price_range' => '150,000 - 400,000 VND',
    'image' => 'image/Mendoza-Wade.jpg',
]);
        $faker = Faker::create('vi_VN');
            for ($i = 101; $i <= 200; $i++) {
                Restaurant::create([
                'name' => 'Nhà hàng ' . $faker->company . " $i",
                'description' => $faker->sentence(10),
                'address' => $faker->address,
                'latitude' => $faker->latitude(8, 24),
                'longitude' => $faker->longitude(102, 110),
                'rating' => $faker->randomFloat(1, 3, 5),
                'price_range' => $faker->randomElement([
                    '100,000 - 300,000 VND',
                    '300,000 - 600,000 VND',
                    '600,000 - 1,000,000 VND',
                    '1,000,000 - 2,000,000 VND'
                ]),
                    'image' => 'image/' . $faker->lexify('imagesDefaut') . '.png',
            ]);
        }
    }
}