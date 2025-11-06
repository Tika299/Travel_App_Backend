<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $categoryIcons = [
            'Phở' => 'pho.png',
            'Bún' => 'bun.png',
            'Cơm' => 'com.png',
            'Bánh mì' => 'banhmi.png',
            'Lẩu' => 'lau.png',
            'Gỏi' => 'goi.png',
            'Hải sản' => 'haisan.png',
            'Món chay' => 'chay.png',
        ];
        $names = array_keys($categoryIcons);
        $name = $this->faker->randomElement($names);
        return [
            'name' => $name,
            'icon' => '/storage/category_icons/' . $categoryIcons[$name],
            'type' => 'food',
        ];
    }
}