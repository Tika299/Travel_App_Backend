<?php
 
namespace Database\Seeders;

use App\Models\TransportCompany;
use App\Models\Transportation;
use Illuminate\Database\Seeder;

class TransportCompaniesTableSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy danh sách loại phương tiện có sẵn
        $transportations = Transportation::all()->keyBy('name');

        // ✅ Danh sách mẫu theo từng loại
        $sampleCompanies = [
            'Ô tô' => ['Mai Lin h', 'Vinasun', 'GrabCar', 'BeCar', 'Xanh SM', 'Taxi Group', 'Vic Taxi', 'Open99', 'GoCar', 'G7 Taxi'],
            'Xe máy' => ['GrabBike', 'BeBike', 'GoBike', 'UberMoto', 'Ahamove Bike', 'Loship Bike', 'Baemin Bike', 'GoFast', 'ZuumBike', 'MoMoRide'],
            'Xe buýt' => ['Bus TP.HCM', 'Xe Buýt Hà Nội', 'Bus Đà Nẵng', 'Futa Bus', 'Open Bus', 'Bus Sài Gòn Tourist', 'Bus Cần Thơ', 'Bus Vũng Tàu', 'Bus Huế', 'VinBus'],
            'Máy bay' => ['Vietnam Airlines', 'VietJet Air', 'Bamboo Airways', 'Pacific Airlines', 'Jetstar', 'AirAsia VN', 'Korean Air', 'Singapore Airlines', 'Cathay Pacific', 'Emirates'],
            'Tàu hỏa' => ['Đường sắt VN', 'Tàu SE1', 'Tàu SE3', 'Tàu TN1', 'Tàu QB1', 'Tàu NA1', 'Tàu SPT1', 'Tàu LP7', 'Tàu Thống Nhất', 'Tàu Bắc Nam'],
        ];

        // Tạo hãng xe mẫu theo loại
        foreach ($sampleCompanies as $type => $companies) {
            if (!isset($transportations[$type])) continue;

            $transportationId = $transportations[$type]->id;

            foreach ($companies as $companyName) {
                TransportCompany::create([
                    'transportation_id' => $transportationId,
                    'name' => $companyName,
                  
                    'description' => $companyName . ' chuyên cung cấp dịch vụ vận chuyển bằng ' . strtolower($type) . ' trên toàn quốc.',
                    'address' => '123 Đường mẫu, Quận 1, TP.HCM',
                    'latitude' => fake()->latitude(10, 21),
                    'longitude' => fake()->longitude(105, 108),
                   
                    'logo' => 'uploads/logos/' . strtolower(str_replace(' ', '_', $companyName)) . '.png',
                    'operating_hours' => json_encode([
                        'Thứ 2- Chủ Nhật' => '24/7',
                        'Tổng Đài ' => '24/7',
                        'Thời gian phản hồi' => '3-5 phút',
                    
                    ]),
               
                    'price_range' => json_encode([
                        'base_km' => rand(10000, 20000),
                        'additional_km' => rand(8000, 15000),
                        'waiting_minute_fee' => rand(1000, 3000),
                    ]),
                    'phone_number' => '0901234567',
                    'email' => strtolower(str_replace(' ', '', $companyName)) . '@gmail.com',
                    'website' => 'https://www.' . strtolower(str_replace(' ', '', $companyName)) . '.com',
                    'payment_methods' => json_encode(['cash', 'bank_card', 'momo']),
                    'has_mobile_app' => rand(0, 1),
                  
                    
                    'status' => 'active',
                ]);
            }
        }
    }
}
