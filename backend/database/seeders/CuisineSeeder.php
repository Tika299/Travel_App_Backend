<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Cuisine;

class CuisineSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Xóa dữ liệu cũ (nếu muốn)
        Cuisine::query()->delete();
        Category::query()->delete();

        // 2. Tạo 8 danh mục cố định
        $categoriesData = [
            ['name' => 'Phở', 'icon' => 'http://localhost:8000/storage/category_icons/pho.png', 'type' => 'food'],
            ['name' => 'Bún', 'icon' => 'http://localhost:8000/storage/category_icons/bun.png', 'type' => 'food'],
            ['name' => 'Cơm', 'icon' => 'http://localhost:8000/storage/category_icons/com.png', 'type' => 'food'],
            ['name' => 'Bánh mì', 'icon' => 'http://localhost:8000/storage/category_icons/banhmi.png', 'type' => 'food'],
            ['name' => 'Lẩu', 'icon' => 'http://localhost:8000/storage/category_icons/lau.png', 'type' => 'food'],
            ['name' => 'Gỏi', 'icon' => 'http://localhost:8000/storage/category_icons/goi.png', 'type' => 'food'],
            ['name' => 'Hải sản', 'icon' => 'http://localhost:8000/storage/category_icons/haisan.png', 'type' => 'food'],
            ['name' => 'Món chay', 'icon' => 'http://localhost:8000/storage/category_icons/chay.png', 'type' => 'food'],
        ];

        foreach ($categoriesData as $data) {
            Category::create($data);
        }

        // Lấy lại tất cả các category đã tạo
        $categories = Category::all();

        // 3. Tạo 50 món ăn, mỗi món thuộc về một danh mục ngẫu nhiên
        Cuisine::factory()->count(50)->make()->each(function ($cuisine) use ($categories) {
            // Gán categories_id bằng id của một category ngẫu nhiên
            $cuisine->categories_id = $categories->random()->id;
            $cuisine->save();
        });
    }
}