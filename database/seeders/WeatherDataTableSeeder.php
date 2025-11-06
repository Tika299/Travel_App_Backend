<?php
namespace Database\Seeders;

use App\Models\WeatherData;
use Illuminate\Database\Seeder;

class WeatherDataTableSeeder extends Seeder
{
    public function run()
    {
        // Thời tiết Đà Nẵng
        WeatherData::create([
            'latitude' => 16.0600000,
            'longitude' => 108.2500000,
            'date' => '2025-07-15',
            'data' => json_encode([
                'temperature' => 28,
                'humidity' => 75,
                'condition' => 'Nắng nhẹ',
                'wind_speed' => 12,
                'precipitation' => 0
            ])
        ]);

        // Thời tiết TP.HCM
        WeatherData::create([
            'latitude' => 10.7820000,
            'longitude' => 106.7000000,
            'date' => '2025-07-16',
            'data' => json_encode([
                'temperature' => 30,
                'humidity' => 80,
                'condition' => 'Mưa rào',
                'wind_speed' => 15,
                'precipitation' => 5
            ])
        ]);

        // Thời tiết Sapa
        WeatherData::create([
            'latitude' => 22.3360000,
            'longitude' => 103.8440000,
            'date' => '2025-08-10',
            'data' => json_encode([
                'temperature' => 18,
                'humidity' => 85,
                'condition' => 'Sương mù',
                'wind_speed' => 8,
                'precipitation' => 0
            ])
        ]);

        // Thời tiết Phú Quốc
        WeatherData::create([
            'latitude' => 10.2230000,
            'longitude' => 103.9670000,
            'date' => '2025-06-21',
            'data' => json_encode([
                'temperature' => 32,
                'humidity' => 70,
                'condition' => 'Nắng nóng',
                'wind_speed' => 10,
                'precipitation' => 0
            ])
        ]);

        // Thời tiết Huế
        WeatherData::create([
            'latitude' => 16.4700000,
            'longitude' => 107.5900000,
            'date' => '2025-09-01',
            'data' => json_encode([
                'temperature' => 26,
                'humidity' => 78,
                'condition' => 'Mây rải rác',
                'wind_speed' => 12,
                'precipitation' => 1
            ])
        ]);
    }
}