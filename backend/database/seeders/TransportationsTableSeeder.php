<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transportation;

class TransportationsTableSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'Ô tô',
                'icon' => 'transportations/icons/car.svg',
                'banner' => 'transportations/banners/banner_car.jpg',
                'average_price' => 15000,
                'description' => 'Ô tô là phương tiện phổ biến, tiện nghi và an toàn cho nhóm người.',
                'tags' => ['pho_bien', 'uy_tin'],
                'features' => ['has_app', 'card_payment', 'insurance'],
                
            ],
            [
                'name' => 'Xe máy',
                'icon' => 'transportations/icons/motorbike.svg',
                'banner' => 'transportations/banners/banner_bike.jpg',
                'average_price' => 8000,
                'description' => 'Xe máy là phương tiện linh hoạt, tiết kiệm và nhanh chóng trong thành phố.',
                'tags' => ['pho_bien', 'di_tiet_kiem'],
                'features' => ['card_payment'],
                
            ],
            [
                'name' => 'Xe đạp',
                'icon' => 'transportations/icons/bicycle.svg',
                'banner' => 'transportations/banners/banner_bicycle.jpg',
                'average_price' => 0,
                'description' => 'Xe đạp thân thiện với môi trường và tốt cho sức khoẻ.',
                'tags' => ['xanh', 'suc_khoe'],
                'features' => [],
                
            ],
            [
                'name' => 'Máy bay',
                'icon' => 'transportations/icons/airplane.svg',
                'banner' => 'transportations/banners/banner_plane.jpg',
                'average_price' => 1200000,
                'description' => 'Máy bay là phương tiện nhanh nhất cho các hành trình xa.',
                'tags' => ['cao_cap', 'toc_do'],
                'features' => ['insurance', 'card_payment'],
                
            ],
            [
                'name' => 'Tàu hỏa',
                'icon' => 'transportations/icons/train.svg',
                'banner' => 'transportations/banners/banner_train.jpg',
                'average_price' => 500000,
                'description' => 'Tàu hoả an toàn, rộng rãi và tiết kiệm cho các chuyến đi dài.',
                'tags' => ['pho_bien', 'an_toan'],
                'features' => ['card_payment'],
                
            ],
            [
                'name' => 'Xe buýt',
                'icon' => 'transportations/icons/bus.svg',
                'banner' => 'transportations/banners/banner_bus.jpg',
                'average_price' => 7000,
                'description' => 'Xe buýt là phương tiện công cộng giá rẻ, phù hợp cho học sinh sinh viên.',
                'tags' => ['gia_re', 'pho_bien'],
                'features' => [],
                
            ],
            [
                'name' => 'Tàu thủy',
                'icon' => 'transportations/icons/ship.svg',
                'banner' => 'transportations/banners/banner_ship.jpg',
                'average_price' => 250000,
                'description' => 'Tàu thuỷ dùng cho vận chuyển đường biển và trải nghiệm du lịch.',
                'tags' => ['du_lich', 'thu_gian'],
                'features' => ['insurance'],
                
            ],
            [
                'name' => 'Ca nô',
                'icon' => 'transportations/icons/canoe.svg',
                'banner' => 'transportations/banners/banner_cano.jpg',
                'average_price' => 300000,
                'description' => 'Ca nô phục vụ các tuyến ngắn trên sông hoặc biển đảo.',
                'tags' => ['toc_do'],
                'features' => ['insurance'],
                
            ],
            [
                'name' => 'Xe điện',
                'icon' => 'transportations/icons/electric-car.svg',
                'banner' => 'transportations/banners/banner_electric.jpg',
                'average_price' => 10000,
                'description' => 'Xe điện thân thiện môi trường, tiện nghi và yên tĩnh.',
                'tags' => ['xanh', 'cong_nghe'],
                'features' => ['has_app', 'card_payment'],
                
            ],
            [
                'name' => 'Xe kéo',
                'icon' => 'transportations/icons/rickshaw.svg',
                'banner' => 'transportations/banners/banner_rickshaw.jpg',
                'average_price' => 3000,
                'description' => 'Xe kéo là phương tiện truyền thống vẫn tồn tại tại một số vùng quê và du lịch.',
                'tags' => ['truyen_thong', 'thu_vi'],
                'features' => [],
                
            ],
        ];

        foreach ($data as $item) {
            Transportation::create([
                ...$item,
                'tags' => json_encode($item['tags']),
                'features' => json_encode($item['features']),
                'is_visible' => true,
            ]);
        }
    }
}

