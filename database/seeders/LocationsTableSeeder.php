<?php
namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationsTableSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Location::truncate();

        // 3. Bật lại kiểm tra khóa ngoại
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $cities = [
            [
                'name' => 'Hà Nội',
                'description' => 'Thủ đô ngàn năm văn hiến với nhiều di tích lịch sử và văn hóa.',
                'image' => 'images/cities/hanoi.jpg', // Đường dẫn giả định tới ảnh của Hà Nội
                'latitude' => 21.028511,
                'longitude' => 105.804817,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Thành phố Hồ Chí Minh',
                'description' => 'Trung tâm kinh tế sầm uất và năng động nhất phía Nam Việt Nam.',
                'image' => 'images/cities/hochiminh.jpg', // Đường dẫn giả định tới ảnh của TP.HCM
                'latitude' => 10.823099,
                'longitude' => 106.629664,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Đà Nẵng',
                'description' => 'Thành phố đáng sống với bãi biển đẹp và cầu Rồng nổi tiếng.',
                'image' => 'images/cities/danang.jpg', // Đường dẫn giả định tới ảnh của Đà Nẵng
                'latitude' => 16.054407,
                'longitude' => 108.202167,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Huế',
                'description' => 'Cố đô Huế với vẻ đẹp cổ kính, lãng mạn và di sản văn hóa thế giới.',
                'image' => 'images/cities/hue.jpg',
                'latitude' => 16.463704,
                'longitude' => 107.590867,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nha Trang',
                'description' => 'Thành phố biển xinh đẹp với những bãi cát trắng và đảo san hô.',
                'image' => 'images/cities/nhatrang.jpg',
                'latitude' => 12.238791,
                'longitude' => 109.196749,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Chèn dữ liệu vào bảng locations
        foreach ($cities as $city) {
            Location::create($city);
        }

        $this->command->info('Locations (cities) seeded!');
    }
} 