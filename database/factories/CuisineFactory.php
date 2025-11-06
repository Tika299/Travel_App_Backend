<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CuisineFactory extends Factory
{
    public function definition(): array
    {
        $ten_mon_an = [
            'Phở Bò Tái Lăn', 'Bún Chả Hà Nội', 'Bún Bò Huế Đặc Biệt', 'Cơm Tấm Sườn Bì Chả',
            'Bánh Mì Heo Quay', 'Hủ Tiếu Nam Vang', 'Bánh Xèo Miền Tây', 'Lẩu Thái Tomyum',
            'Gỏi Cuốn Tôm Thịt', 'Cao Lầu Hội An', 'Mì Quảng Ếch', 'Bò Lúc Lắc'
        ];

        $dia_chi = [
            '123 Lý Thường Kiệt, Q.10', '45A Đinh Tiên Hoàng, Q.1', '2 Trần Hưng Đạo, Q.5',
            '88 Nguyễn Trãi, Q.5', '200 Pasteur, Q.3', '10 Phan Xích Long, Phú Nhuận'
        ];

        $images = [
            'http://localhost:8000/storage/cuisine/2xWy68prmfDU0D2qzYjdGpzFMfcYuUvMnb4KH6Q2.jpg',
            'http://localhost:8000/storage/cuisine/27i9iEdckb5gCZWYgwuzM0Y4xQAOfiaYxB2WxZ06.jpg',
            'http://localhost:8000/storage/cuisine/906qXwWkVjHlYDKltjOTT3A7L9ajf5JWDnEeczmE.jpg',
            'http://localhost:8000/storage/cuisine/C6zjbBfjCLa7f8FnpwXcoTbetN0QCXQqUxtfIrxK.jpg',
            'http://localhost:8000/storage/cuisine/jV9KF3ZLqI6pZH7IDxSBPnmbdPFPvzDh0MccLw1t.jpg',
            'http://localhost:8000/storage/cuisine/kGlfYQNyk6x3lWRK3zXodCg2jOeRA0XpyZLLIXsm.jpg',
        ];

        return [
            // categories_id sẽ được gán trong Seeder
            'name' => $this->faker->randomElement($ten_mon_an),
            'image' => $this->faker->randomElement($images),
            'short_description' => 'Món ăn đậm đà hương vị truyền thống, nguyên liệu tươi ngon được chọn lọc kỹ càng.',
            'detailed_description' => $this->faker->realText(200, 2), // Tạo text tiếng Việt thật
            'region' => $this->faker->randomElement(['Miền Bắc', 'Miền Trung', 'Miền Nam']),
            'price' => $this->faker->numberBetween(35, 150) * 1000, // Giá từ 35.000 đến 150.000
            'address' => $this->faker->randomElement($dia_chi),
            'serving_time' => $this->faker->randomElement(['10-15 phút', '15-20 phút', '20-25 phút']),
            'delivery' => $this->faker->boolean(70), // 70% là có giao hàng
            'operating_hours' => '08:00 - 22:00',
            'suitable_for' => 'Gia đình, bạn bè',
            'status' => 'available',
        ];
    }
}