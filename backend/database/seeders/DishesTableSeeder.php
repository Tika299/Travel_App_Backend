<?php
namespace Database\Seeders;

use App\Models\Dish;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;


class DishesTableSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('dishes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Dish::create([
            'restaurant_id' => 1,
            'name' => 'Phở bò chín nạm',
            'price' => 65000,
            'description' => 'Phở bò truyền thống với thịt chín và nạm',
            'is_best_seller' => true,
            'category' => 'Món chính',
            'image' => 'image/Pho.jpg',
        ]);

        Dish::create([
            'restaurant_id' => 1,
            'name' => 'Gỏi cuốn',
            'price' => 45000,
            'description' => 'Gỏi cuốn tôm thịt với bánh tráng và rau sống',
            'is_best_seller' => true,
            'category' => 'Khai vị',
            'image' => 'image/Goicuontomthit.jpg',
        ]);

        Dish::create([
            'restaurant_id' => 2,
            'name' => 'Bò Wellington',
            'price' => 850000,
            'description' => 'Món bò Wellington chuẩn Pháp',
            'is_best_seller' => false,
            'category' => 'Món chính',
            'image' => 'image/Pho.jpg',
        ]);

        Dish::create([
            'restaurant_id' => 3,
            'name' => 'Bún bò Huế',
            'price' => 75000,
            'description' => 'Bún bò Huế đúng chuẩn xứ Huế',
            'is_best_seller' => true,
            'category' => 'Món chính',
            'image' => 'image/Pho.jpg',
        ]);

        Dish::create([
            'restaurant_id' => 5,
            'name' => 'Tôm hùm nướng bơ tỏi',
            'price' => 1200000,
            'description' => 'Tôm hùm Alaska nướng bơ tỏi thơm ngon',
            'is_best_seller' => true,
            'category' => 'Hải sản',
            'image' => 'image/Pho.jpg',
        ]);
    }
}