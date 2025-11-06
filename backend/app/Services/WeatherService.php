<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private $apiKey;
    private $baseUrl = 'http://api.openweathermap.org/data/2.5';

    public function __construct()
    {
        $this->apiKey = env('OPENWEATHER_API_KEY', 'your_api_key_here');
    }

    /**
     * Lấy thời tiết cho một thành phố
     */
    public function getWeather($city, $country = 'VN')
    {
        try {
            // Sử dụng API thật OpenWeatherMap
            if ($this->apiKey && $this->apiKey !== 'your_api_key_here') {
                $response = Http::get("{$this->baseUrl}/weather", [
                    'q' => "{$city},{$country}",
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang' => 'vi'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return [
                        'success' => true,
                        'data' => [
                            'temperature' => $data['main']['temp'],
                            'feels_like' => $data['main']['feels_like'],
                            'humidity' => $data['main']['humidity'],
                            'description' => $data['weather'][0]['description'],
                            'main' => $data['weather'][0]['main'],
                            'icon' => $data['weather'][0]['icon'],
                            'wind_speed' => $data['wind']['speed'] ?? 0,
                            'rain' => $data['rain']['1h'] ?? 0,
                            'snow' => $data['snow']['1h'] ?? 0
                        ]
                    ];
                } else {
                    Log::warning('Weather API Error: ' . $response->body());
                }
            }

            // Fallback mock data nếu API không hoạt động
            $mockWeatherData = [
                'Đà Nẵng' => [
                    'temperature' => 28,
                    'feels_like' => 32,
                    'humidity' => 75,
                    'description' => 'nắng nhẹ',
                    'main' => 'Clear',
                    'icon' => '01d',
                    'wind_speed' => 5,
                    'rain' => 0,
                    'snow' => 0
                ],
                'TP. Hồ Chí Minh' => [
                    'temperature' => 32,
                    'feels_like' => 38,
                    'humidity' => 80,
                    'description' => 'mưa rào',
                    'main' => 'Rain',
                    'icon' => '10d',
                    'wind_speed' => 8,
                    'rain' => 15,
                    'snow' => 0
                ],
                'Hà Nội' => [
                    'temperature' => 25,
                    'feels_like' => 28,
                    'humidity' => 70,
                    'description' => 'mát mẻ',
                    'main' => 'Clouds',
                    'icon' => '03d',
                    'wind_speed' => 3,
                    'rain' => 0,
                    'snow' => 0
                ]
            ];

            // Sử dụng mock data cho các thành phố có sẵn
            $cityKey = $city;
            if (isset($mockWeatherData[$cityKey])) {
                Log::info("Using mock weather data for: {$city}");
                return [
                    'success' => true,
                    'data' => $mockWeatherData[$cityKey]
                ];
            }

            // Fallback cho các thành phố khác
            Log::info("Using fallback weather data for: {$city}");
            return [
                'success' => true,
                'data' => [
                    'temperature' => 26,
                    'feels_like' => 29,
                    'humidity' => 72,
                    'description' => 'thời tiết đẹp',
                    'main' => 'Clear',
                    'icon' => '01d',
                    'wind_speed' => 4,
                    'rain' => 0,
                    'snow' => 0
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Weather API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi khi lấy thông tin thời tiết: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lấy dự báo thời tiết 5 ngày
     */
    public function getForecast($city, $country = 'VN')
    {
        try {
            $response = Http::get("{$this->baseUrl}/forecast", [
                'q' => "{$city},{$country}",
                'appid' => $this->apiKey,
                'units' => 'metric',
                'lang' => 'vi'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['list']
                ];
            }

            return [
                'success' => false,
                'message' => 'Không thể lấy dự báo thời tiết'
            ];

        } catch (\Exception $e) {
            Log::error('Weather Forecast API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi khi lấy dự báo thời tiết'
            ];
        }
    }

    /**
     * Phân tích thời tiết và gợi ý hoạt động
     */
    public function getWeatherRecommendations($weatherData)
    {
        if (!$weatherData['success']) {
            return $this->getDefaultRecommendations();
        }

        $data = $weatherData['data'];
        $temp = $data['temperature'];
        $main = $data['main'];
        $rain = $data['rain'];
        $snow = $data['snow'];
        $wind = $data['wind_speed'];

        $recommendations = [];

        // Gợi ý dựa trên nhiệt độ
        if ($temp >= 30) {
            $recommendations['temperature'] = [
                'type' => 'hot',
                'message' => 'Nhiệt độ cao, nên chọn hoạt động trong nhà hoặc có bóng mát',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Trung tâm thương mại', 'Nhà hàng có điều hòa', 'Spa', 'Cà phê'],
                    'outdoor' => ['Bãi biển (buổi sáng/tối)', 'Công viên nước', 'Tham quan di tích có mái che']
                ]
            ];
        } elseif ($temp >= 20) {
            $recommendations['temperature'] = [
                'type' => 'pleasant',
                'message' => 'Thời tiết dễ chịu, phù hợp cho mọi hoạt động',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Spa', 'Cà phê', 'Trung tâm thương mại'],
                    'outdoor' => ['Tham quan di tích', 'Bãi biển', 'Công viên', 'Chụp ảnh']
                ]
            ];
        } elseif ($temp >= 10) {
            $recommendations['temperature'] = [
                'type' => 'cool',
                'message' => 'Thời tiết mát mẻ, nên mang theo áo ấm',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Cà phê', 'Spa'],
                    'outdoor' => ['Tham quan di tích', 'Công viên', 'Leo núi nhẹ', 'Chụp ảnh']
                ]
            ];
        } else {
            $recommendations['temperature'] = [
                'type' => 'cold',
                'message' => 'Thời tiết lạnh, ưu tiên hoạt động trong nhà',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Spa', 'Trung tâm thương mại', 'Cà phê'],
                    'outdoor' => ['Chỉ nên ra ngoài khi cần thiết']
                ]
            ];
        }

        // Gợi ý dựa trên mưa
        if ($rain > 0) {
            $recommendations['rain'] = [
                'type' => 'rainy',
                'message' => 'Có mưa, nên chọn hoạt động trong nhà',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Cà phê', 'Spa', 'Trung tâm thương mại'],
                    'outdoor' => ['Chỉ ra ngoài khi cần thiết, mang theo ô/dù']
                ]
            ];
        }

        // Gợi ý dựa trên tuyết
        if ($snow > 0) {
            $recommendations['snow'] = [
                'type' => 'snowy',
                'message' => 'Có tuyết, cần cẩn thận khi di chuyển',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Spa', 'Cà phê'],
                    'outdoor' => ['Chơi tuyết (nếu an toàn)', 'Chụp ảnh tuyết']
                ]
            ];
        }

        // Gợi ý dựa trên gió
        if ($wind > 20) {
            $recommendations['wind'] = [
                'type' => 'windy',
                'message' => 'Gió mạnh, nên chọn hoạt động trong nhà',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Cà phê', 'Spa', 'Trung tâm thương mại'],
                    'outdoor' => ['Tránh hoạt động ngoài trời']
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Gợi ý mặc định khi không có thông tin thời tiết
     */
    private function getDefaultRecommendations()
    {
        return [
            'default' => [
                'type' => 'unknown',
                'message' => 'Không có thông tin thời tiết, gợi ý hoạt động đa dạng',
                'activities' => [
                    'indoor' => ['Bảo tàng', 'Nhà hàng', 'Cà phê', 'Spa', 'Trung tâm thương mại'],
                    'outdoor' => ['Tham quan di tích', 'Đi bộ', 'Chụp ảnh', 'Bãi biển']
                ]
            ]
        ];
    }

    /**
     * Lọc địa điểm dựa trên thời tiết
     */
    public function filterPlacesByWeather($places, $weatherRecommendations)
    {
        $filteredPlaces = [];
        $indoorActivities = [];
        $outdoorActivities = [];

        // Tách hoạt động trong nhà và ngoài trời
        foreach ($weatherRecommendations as $recommendation) {
            if (isset($recommendation['activities']['indoor'])) {
                $indoorActivities = array_merge($indoorActivities, $recommendation['activities']['indoor']);
            }
            if (isset($recommendation['activities']['outdoor'])) {
                $outdoorActivities = array_merge($outdoorActivities, $recommendation['activities']['outdoor']);
            }
        }

        // Phân loại địa điểm
        foreach ($places as $place) {
            $placeType = $this->categorizePlace($place);
            
            if (in_array($placeType, $indoorActivities)) {
                $place['weather_suitable'] = 'indoor';
                $filteredPlaces[] = $place;
            } elseif (in_array($placeType, $outdoorActivities)) {
                $place['weather_suitable'] = 'outdoor';
                $filteredPlaces[] = $place;
            } else {
                $place['weather_suitable'] = 'general';
                $filteredPlaces[] = $place;
            }
        }

        return $filteredPlaces;
    }

    /**
     * Phân loại địa điểm
     */
    private function categorizePlace($place)
    {
        $name = strtolower($place['name']);
        $description = strtolower($place['description'] ?? '');

        // Địa điểm trong nhà
        if (str_contains($name, 'bảo tàng') || str_contains($description, 'bảo tàng')) {
            return 'Bảo tàng';
        }
        if (str_contains($name, 'nhà hàng') || str_contains($description, 'nhà hàng')) {
            return 'Nhà hàng';
        }
        if (str_contains($name, 'khách sạn') || str_contains($description, 'khách sạn')) {
            return 'Khách sạn';
        }
        if (str_contains($name, 'trung tâm') || str_contains($description, 'trung tâm')) {
            return 'Trung tâm thương mại';
        }
        if (str_contains($name, 'spa') || str_contains($description, 'spa')) {
            return 'Spa';
        }

        // Địa điểm ngoài trời
        if (str_contains($name, 'bãi biển') || str_contains($description, 'bãi biển')) {
            return 'Bãi biển';
        }
        if (str_contains($name, 'công viên') || str_contains($description, 'công viên')) {
            return 'Công viên';
        }
        if (str_contains($name, 'núi') || str_contains($description, 'núi')) {
            return 'Leo núi';
        }
        if (str_contains($name, 'cầu') || str_contains($description, 'cầu')) {
            return 'Tham quan';
        }

        return 'Tham quan';
    }

    /**
     * Tạo prompt AI với thông tin thời tiết
     */
    public function createWeatherAwarePrompt($destination, $weatherData, $recommendations)
    {
        $prompt = "Tạo lịch trình du lịch cho {$destination}.\n\n";
        
        if ($weatherData['success']) {
            $data = $weatherData['data'];
            $prompt .= "THÔNG TIN THỜI TIẾT:\n";
            $prompt .= "- Nhiệt độ: {$data['temperature']}°C\n";
            $prompt .= "- Mô tả: {$data['description']}\n";
            $prompt .= "- Độ ẩm: {$data['humidity']}%\n";
            if ($data['rain'] > 0) $prompt .= "- Có mưa: {$data['rain']}mm\n";
            if ($data['snow'] > 0) $prompt .= "- Có tuyết: {$data['snow']}mm\n";
            $prompt .= "- Gió: {$data['wind_speed']} m/s\n\n";
        }

        $prompt .= "GỢI Ý DỰA TRÊN THỜI TIẾT:\n";
        foreach ($recommendations as $type => $rec) {
            $prompt .= "- {$rec['message']}\n";
            if (isset($rec['activities']['indoor'])) {
                $prompt .= "  + Hoạt động trong nhà: " . implode(', ', $rec['activities']['indoor']) . "\n";
            }
            if (isset($rec['activities']['outdoor'])) {
                $prompt .= "  + Hoạt động ngoài trời: " . implode(', ', $rec['activities']['outdoor']) . "\n";
            }
        }

        $prompt .= "\nHãy tạo lịch trình phù hợp với thời tiết, ưu tiên các hoạt động phù hợp với điều kiện thời tiết hiện tại.";

        return $prompt;
    }
}
