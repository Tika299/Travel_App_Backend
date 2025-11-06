<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DaNangCheckinPlacesSeeder extends Seeder
{
    public function run(): void
    {
        $daNangLocationId = DB::table('locations')->where('name', 'Đà Nẵng')->value('id');
        
        if (!$daNangLocationId) {
            $daNangLocationId = DB::table('locations')->insertGetId([
                'name' => 'Đà Nẵng',
                'description' => 'Thành phố Đà Nẵng - thành phố đáng sống với bãi biển đẹp và văn hóa phong phú',
                'latitude' => 16.0544,
                'longitude' => 108.2022,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $checkinPlaces = [
            [
                'name' => 'Bãi biển Mỹ Khê',
                'description' => 'Một trong những bãi biển đẹp nhất thế giới với cát trắng mịn và nước trong xanh',
                'address' => 'Phường Phước Mỹ, Quận Sơn Trà, TP. Đà Nẵng',
                'latitude' => 16.0583,
                'longitude' => 108.2417,
                'image' => 'checkin/bai_bien_my_khe.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '06:00', 'close' => '18:00']]),
                'images' => json_encode(['checkin/bai_bien_my_khe_1.jpg', 'checkin/bai_bien_my_khe_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Một trong những bãi biển đẹp nhất thế giới',
                'transport_options' => json_encode(['car', 'motorbike', 'taxi']),
                'status' => 'active',
            ],
            [
                'name' => 'Bán đảo Sơn Trà',
                'description' => 'Bán đảo với view toàn cảnh thành phố Đà Nẵng và biển',
                'address' => 'Quận Sơn Trà, TP. Đà Nẵng',
                'latitude' => 16.1000,
                'longitude' => 108.2500,
                'image' => 'checkin/ban_dao_son_tra.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '06:00', 'close' => '18:00']]),
                'images' => json_encode(['checkin/ban_dao_son_tra_1.jpg', 'checkin/ban_dao_son_tra_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'View toàn cảnh thành phố Đà Nẵng',
                'transport_options' => json_encode(['car', 'motorbike']),
                'status' => 'active',
            ],
            [
                'name' => 'Chùa Linh Ứng',
                'description' => 'Chùa với tượng Phật Quan Âm cao nhất Việt Nam trên bán đảo Sơn Trà',
                'address' => 'Bán đảo Sơn Trà, Quận Sơn Trà, TP. Đà Nẵng',
                'latitude' => 16.1000,
                'longitude' => 108.2500,
                'image' => 'checkin/chua_linh_ung.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '07:00', 'close' => '17:00']]),
                'images' => json_encode(['checkin/chua_linh_ung_1.jpg', 'checkin/chua_linh_ung_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Tượng Phật Quan Âm cao nhất Việt Nam',
                'transport_options' => json_encode(['car', 'motorbike']),
                'status' => 'active',
            ],
            [
                'name' => 'Bãi biển Non Nước',
                'description' => 'Bãi biển đẹp với cát trắng và nước trong, lý tưởng cho du lịch',
                'address' => 'Phường Hòa Hải, Quận Ngũ Hành Sơn, TP. Đà Nẵng',
                'latitude' => 16.0167,
                'longitude' => 108.2500,
                'image' => 'checkin/bai_bien_non_nuoc.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '06:00', 'close' => '18:00']]),
                'images' => json_encode(['checkin/bai_bien_non_nuoc_1.jpg', 'checkin/bai_bien_non_nuoc_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Bãi biển đẹp với cát trắng mịn',
                'transport_options' => json_encode(['car', 'motorbike', 'taxi']),
                'status' => 'active',
            ],
            [
                'name' => 'Ngũ Hành Sơn',
                'description' => 'Quần thể 5 ngọn núi đá cẩm thạch với nhiều hang động và chùa chiền',
                'address' => 'Quận Ngũ Hành Sơn, TP. Đà Nẵng',
                'latitude' => 16.0167,
                'longitude' => 108.2500,
                'image' => 'checkin/ngu_hanh_son.jpg',
                'location_id' => $daNangLocationId,
                'price' => 40000,
                'is_free' => false,
                'operating_hours' => json_encode([['open' => '07:00', 'close' => '17:00']]),
                'images' => json_encode(['checkin/ngu_hanh_son_1.jpg', 'checkin/ngu_hanh_son_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Quần thể núi đá cẩm thạch đẹp',
                'transport_options' => json_encode(['car', 'motorbike']),
                'status' => 'active',
            ],
            [
                'name' => 'Cầu Rồng',
                'description' => 'Cầu Rồng - biểu tượng của thành phố Đà Nẵng với màn trình diễn phun lửa',
                'address' => 'Sông Hàn, TP. Đà Nẵng',
                'latitude' => 16.0617,
                'longitude' => 108.2278,
                'image' => 'checkin/cau_rong.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '00:00', 'close' => '23:59']]),
                'images' => json_encode(['checkin/cau_rong_1.jpg', 'checkin/cau_rong_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Biểu tượng của thành phố Đà Nẵng',
                'transport_options' => json_encode(['car', 'motorbike', 'taxi', 'walking']),
                'status' => 'active',
            ],
            [
                'name' => 'Bảo tàng Chăm',
                'description' => 'Bảo tàng trưng bày các hiện vật văn hóa Chăm Pa',
                'address' => '2 Tháng 9, Quận Hải Châu, TP. Đà Nẵng',
                'latitude' => 16.0475,
                'longitude' => 108.2069,
                'image' => 'checkin/bao_tang_cham.jpg',
                'location_id' => $daNangLocationId,
                'price' => 60000,
                'is_free' => false,
                'operating_hours' => json_encode([['open' => '07:00', 'close' => '17:30']]),
                'images' => json_encode(['checkin/bao_tang_cham_1.jpg', 'checkin/bao_tang_cham_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Bảo tàng văn hóa Chăm Pa',
                'transport_options' => json_encode(['car', 'motorbike', 'taxi']),
                'status' => 'active',
            ],
            [
                'name' => 'Bãi biển Lăng Cô',
                'description' => 'Bãi biển đẹp với cát trắng và nước trong xanh',
                'address' => 'Huyện Phú Lộc, Thừa Thiên Huế (gần Đà Nẵng)',
                'latitude' => 16.2000,
                'longitude' => 108.0500,
                'image' => 'checkin/bai_bien_lang_co.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '06:00', 'close' => '18:00']]),
                'images' => json_encode(['checkin/bai_bien_lang_co_1.jpg', 'checkin/bai_bien_lang_co_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Bãi biển đẹp với cát trắng mịn',
                'transport_options' => json_encode(['car', 'motorbike']),
                'status' => 'active',
            ],
            [
                'name' => 'Đèo Hải Vân',
                'description' => 'Đèo đẹp với view toàn cảnh biển và núi',
                'address' => 'Ranh giới Đà Nẵng - Thừa Thiên Huế',
                'latitude' => 16.2000,
                'longitude' => 108.1000,
                'image' => 'checkin/deo_hai_van.jpg',
                'location_id' => $daNangLocationId,
                'price' => 0,
                'is_free' => true,
                'operating_hours' => json_encode([['open' => '00:00', 'close' => '23:59']]),
                'images' => json_encode(['checkin/deo_hai_van_1.jpg', 'checkin/deo_hai_van_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Đèo đẹp với view toàn cảnh',
                'transport_options' => json_encode(['car', 'motorbike']),
                'status' => 'active',
            ],
            [
                'name' => 'Công viên Châu Á',
                'description' => 'Công viên giải trí với nhiều trò chơi và show diễn',
                'address' => 'Phường Hòa Hải, Quận Ngũ Hành Sơn, TP. Đà Nẵng',
                'latitude' => 16.0167,
                'longitude' => 108.2500,
                'image' => 'checkin/cong_vien_chau_a.jpg',
                'location_id' => $daNangLocationId,
                'price' => 850000,
                'is_free' => false,
                'operating_hours' => json_encode([['open' => '15:00', 'close' => '22:00']]),
                'images' => json_encode(['checkin/cong_vien_chau_a_1.jpg', 'checkin/cong_vien_chau_a_2.jpg']),
                'region' => 'Nam Trung Bộ',
                'caption' => 'Công viên giải trí hàng đầu',
                'transport_options' => json_encode(['car', 'motorbike', 'taxi']),
                'status' => 'active',
            ],
        ];

        foreach ($checkinPlaces as $place) {
            DB::table('checkin_places')->updateOrInsert(
                [
                    'name' => $place['name'],
                    'location_id' => $place['location_id']
                ],
                array_merge($place, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}


