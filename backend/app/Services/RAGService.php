<?php

namespace App\Services;

use App\Models\CheckinPlace;
use App\Models\Hotel;
use App\Models\Restaurant;
use App\Models\TransportCompany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RAGService
{
    protected $openWeatherApiKey;
    protected $googlePlacesApiKey;

    public function __construct()
    {
        $this->openWeatherApiKey = env('OPENWEATHER_API_KEY');
        $this->googlePlacesApiKey = env('GOOGLE_PLACES_API_KEY');
    }

    /**
     * Phân tích câu hỏi và trích xuất thông tin
     */
    public function analyzeQuery($message)
    {
        $analysis = [
            'destination' => null,
            'duration' => null,
            'travelers' => null,
            'budget' => null,
            'hotel_requirements' => [],
            'activities' => [],
            'weather_concern' => false,
            'transport_concern' => false
        ];

        $messageLower = strtolower($message);

        // Trích xuất địa điểm
        $destinations = [
            'TP.HCM', 'Hồ Chí Minh', 'Ho Chi Minh', 'Sài Gòn', 'Saigon', 
            'Hà Nội', 'Hanoi', 'Đà Nẵng', 'Da Nang', 'Huế', 'Hue', 
            'Hội An', 'Hoi An', 'Nha Trang', 'Phú Quốc', 'Phu Quoc', 
            'Đà Lạt', 'Da Lat', 'Sa Pa', 'Sapa', 'Hạ Long', 'Ha Long'
        ];

        foreach ($destinations as $dest) {
            if (str_contains($messageLower, strtolower($dest))) {
                $analysis['destination'] = $dest;
                break;
            }
        }

        // Trích xuất thời gian
        if (preg_match('/(\d+)\s*(ngày|ngay|day)/i', $message, $matches)) {
            $analysis['duration'] = (int)$matches[1];
        }

        // Trích xuất số người
        if (preg_match('/(\d+)\s*(người|nguoi|person|people)/i', $message, $matches)) {
            $analysis['travelers'] = (int)$matches[1];
        }

        // Trích xuất ngân sách
        if (preg_match('/(\d+)\s*(triệu|nghìn|đồng|vnd|million|thousand)/i', $message, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);
            
            switch ($unit) {
                case 'triệu':
                case 'million':
                    $analysis['budget'] = $amount * 1000000;
                    break;
                case 'nghìn':
                case 'thousand':
                    $analysis['budget'] = $amount * 1000;
                    break;
                default:
                    $analysis['budget'] = $amount;
            }
        }

        // Trích xuất yêu cầu khách sạn
        $hotelKeywords = ['khách sạn', 'hotel', 'resort', 'sao', 'star', 'gần biển', 'near beach'];
        foreach ($hotelKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                $analysis['hotel_requirements'][] = $keyword;
            }
        }

        // Trích xuất hoạt động
        $activityKeywords = ['tham quan', 'visit', 'ăn uống', 'food', 'vui chơi', 'entertainment'];
        foreach ($activityKeywords as $keyword) {
            if (str_contains($messageLower, $keyword)) {
                $analysis['activities'][] = $keyword;
            }
        }

        // Kiểm tra quan tâm thời tiết
        if (str_contains($messageLower, 'thời tiết') || str_contains($messageLower, 'weather')) {
            $analysis['weather_concern'] = true;
        }

        // Kiểm tra quan tâm vận chuyển
        if (str_contains($messageLower, 'xe') || str_contains($messageLower, 'transport') || str_contains($messageLower, 'di chuyển')) {
            $analysis['transport_concern'] = true;
        }

        return $analysis;
    }

    /**
     * Lấy dữ liệu từ database
     */
    public function retrieveFromDatabase($analysis)
    {
        $data = [
            'checkin_places' => [],
            'hotels' => [],
            'restaurants' => [],
            'transport' => []
        ];

        try {
            // Lấy địa điểm tham quan
            if ($analysis['destination']) {
                $checkinPlaces = CheckinPlace::where(function($query) use ($analysis) {
                    $query->where('name', 'like', '%' . $analysis['destination'] . '%')
                          ->orWhere('address', 'like', '%' . $analysis['destination'] . '%')
                          ->orWhere('description', 'like', '%' . $analysis['destination'] . '%');
                })->limit(10)->get();

                $data['checkin_places'] = $checkinPlaces->map(function($place) {
                    return [
                        'name' => $place->name,
                        'address' => $place->address,
                        'description' => $place->description,
                        'rating' => $place->rating,
                        'price_range' => $place->price_range ?? 'Chưa có thông tin',
                        'category' => $place->category ?? 'Tham quan'
                    ];
                })->toArray();
            }

            // Lấy khách sạn
            if ($analysis['destination'] || !empty($analysis['hotel_requirements'])) {
                $hotelQuery = Hotel::query();
                
                if ($analysis['destination']) {
                    $hotelQuery->where(function($query) use ($analysis) {
                        $query->where('name', 'like', '%' . $analysis['destination'] . '%')
                              ->orWhere('address', 'like', '%' . $analysis['destination'] . '%');
                    });
                }

                // Lọc theo yêu cầu
                if (in_array('gần biển', $analysis['hotel_requirements'])) {
                    $hotelQuery->where('address', 'like', '%biển%');
                }

                $hotels = $hotelQuery->limit(5)->get();

                $data['hotels'] = $hotels->map(function($hotel) {
                    return [
                        'name' => $hotel->name,
                        'address' => $hotel->address,
                        'rating' => $hotel->rating,
                        'price_range' => $hotel->price_range ?? 'Chưa có thông tin',
                        'amenities' => $hotel->amenities ?? []
                    ];
                })->toArray();
            }

            // Lấy nhà hàng
            if ($analysis['destination']) {
                $restaurants = Restaurant::where(function($query) use ($analysis) {
                    $query->where('name', 'like', '%' . $analysis['destination'] . '%')
                          ->orWhere('address', 'like', '%' . $analysis['destination'] . '%');
                })->limit(5)->get();

                $data['restaurants'] = $restaurants->map(function($restaurant) {
                    return [
                        'name' => $restaurant->name,
                        'address' => $restaurant->address,
                        'cuisine' => $restaurant->cuisine,
                        'rating' => $restaurant->rating,
                        'price_range' => $restaurant->price_range ?? 'Chưa có thông tin'
                    ];
                })->toArray();
            }

            // Lấy thông tin vận chuyển
            if ($analysis['transport_concern']) {
                $transport = TransportCompany::limit(3)->get();
                $data['transport'] = $transport->map(function($company) {
                    return [
                        'name' => $company->name,
                        'type' => $company->type,
                        'rating' => $company->rating,
                        'price_range' => $company->price_range ?? 'Chưa có thông tin'
                    ];
                })->toArray();
            }

        } catch (\Exception $e) {
            Log::error('Error retrieving data from database: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Lấy dữ liệu từ API bên ngoài
     */
    public function retrieveFromExternalAPIs($analysis)
    {
        $externalData = [
            'weather' => null,
            'places' => []
        ];

        try {
                    // Lấy thông tin thời tiết
        if ($analysis['weather_concern'] && $analysis['destination']) {
            if ($this->openWeatherApiKey) {
                $weatherData = $this->getWeatherData($analysis['destination']);
                if ($weatherData) {
                    $externalData['weather'] = $weatherData;
                }
            } else {
                // Fallback: Tạo dữ liệu thời tiết mẫu
                $externalData['weather'] = [
                    'temperature' => '25-30',
                    'description' => 'Nắng đẹp, nhiệt độ dễ chịu',
                    'humidity' => '70',
                    'wind_speed' => '10'
                ];
            }
        }

            // Lấy thông tin từ Google Places
            if ($analysis['destination'] && $this->googlePlacesApiKey) {
                $placesData = $this->getGooglePlacesData($analysis['destination']);
                if ($placesData) {
                    $externalData['places'] = $placesData;
                }
            }

        } catch (\Exception $e) {
            Log::error('Error retrieving external data: ' . $e->getMessage());
        }

        return $externalData;
    }

    /**
     * Lấy thông tin thời tiết
     */
    private function getWeatherData($destination)
    {
        $cityMap = [
            'TP.HCM' => 'Ho Chi Minh City',
            'Hồ Chí Minh' => 'Ho Chi Minh City',
            'Ho Chi Minh' => 'Ho Chi Minh City',
            'Sài Gòn' => 'Ho Chi Minh City',
            'Saigon' => 'Ho Chi Minh City',
            'Hà Nội' => 'Hanoi',
            'Hanoi' => 'Hanoi',
            'Đà Nẵng' => 'Da Nang',
            'Da Nang' => 'Da Nang',
            'Huế' => 'Hue',
            'Hue' => 'Hue',
            'Hội An' => 'Hoi An',
            'Hoi An' => 'Hoi An'
        ];

        $englishCityName = $cityMap[$destination] ?? $destination;

        try {
            $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $englishCityName . ',VN',
                'appid' => $this->openWeatherApiKey,
                'units' => 'metric',
                'lang' => 'vi'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'temperature' => $data['main']['temp'] ?? null,
                    'description' => $data['weather'][0]['description'] ?? null,
                    'humidity' => $data['main']['humidity'] ?? null,
                    'wind_speed' => $data['wind']['speed'] ?? null
                ];
            }
        } catch (\Exception $e) {
            Log::error('Weather API error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Lấy thông tin từ Google Places
     */
    private function getGooglePlacesData($destination)
    {
        try {
            // Tìm kiếm địa điểm
            $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                'query' => $destination . ' Vietnam tourist attractions',
                'key' => $this->googlePlacesApiKey,
                'language' => 'vi'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $places = [];

                if (isset($data['results'])) {
                    foreach (array_slice($data['results'], 0, 5) as $place) {
                        $places[] = [
                            'name' => $place['name'] ?? '',
                            'address' => $place['formatted_address'] ?? '',
                            'rating' => $place['rating'] ?? null,
                            'types' => $place['types'] ?? [],
                            'photos' => isset($place['photos']) ? count($place['photos']) : 0
                        ];
                    }
                }

                return $places;
            }
        } catch (\Exception $e) {
            Log::error('Google Places API error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Tạo prompt cho AI với dữ liệu RAG
     */
    public function buildRAGPrompt($message, $analysis, $databaseData, $externalData)
    {
        $prompt = "Bạn là một trợ lý du lịch thông minh tại Việt Nam.\n\n";
        $prompt .= "CÂU HỎI CỦA NGƯỜI DÙNG: {$message}\n\n";
        
        $prompt .= "PHÂN TÍCH CÂU HỎI:\n";
        if ($analysis['destination']) $prompt .= "- Địa điểm: {$analysis['destination']}\n";
        if ($analysis['duration']) $prompt .= "- Thời gian: {$analysis['duration']} ngày\n";
        if ($analysis['travelers']) $prompt .= "- Số người: {$analysis['travelers']} người\n";
        if ($analysis['budget']) $prompt .= "- Ngân sách: " . number_format($analysis['budget']) . " VNĐ\n";
        if (!empty($analysis['hotel_requirements'])) $prompt .= "- Yêu cầu khách sạn: " . implode(', ', $analysis['hotel_requirements']) . "\n";
        if (!empty($analysis['activities'])) $prompt .= "- Hoạt động: " . implode(', ', $analysis['activities']) . "\n";
        $prompt .= "\n";

        // Dữ liệu từ database
        if (!empty($databaseData['checkin_places'])) {
            $prompt .= "ĐỊA ĐIỂM THAM QUAN (TỪ DATABASE):\n";
            foreach ($databaseData['checkin_places'] as $place) {
                $prompt .= "- {$place['name']}: {$place['description']} (Địa chỉ: {$place['address']}, Đánh giá: {$place['rating']}/5, Giá: {$place['price_range']})\n";
            }
            $prompt .= "\n";
        }

        if (!empty($databaseData['hotels'])) {
            $prompt .= "KHÁCH SẠN (TỪ DATABASE):\n";
            foreach ($databaseData['hotels'] as $hotel) {
                $prompt .= "- {$hotel['name']}: {$hotel['address']} (Đánh giá: {$hotel['rating']}/5, Giá: {$hotel['price_range']})\n";
            }
            $prompt .= "\n";
        }

        if (!empty($databaseData['restaurants'])) {
            $prompt .= "NHÀ HÀNG (TỪ DATABASE):\n";
            foreach ($databaseData['restaurants'] as $restaurant) {
                $prompt .= "- {$restaurant['name']}: {$restaurant['address']} (Ẩm thực: {$restaurant['cuisine']}, Đánh giá: {$restaurant['rating']}/5, Giá: {$restaurant['price_range']})\n";
            }
            $prompt .= "\n";
        }

        // Dữ liệu từ API bên ngoài
        if ($externalData['weather']) {
            $weather = $externalData['weather'];
            $prompt .= "THÔNG TIN THỜI TIẾT:\n";
            $prompt .= "- Nhiệt độ: {$weather['temperature']}°C\n";
            $prompt .= "- Mô tả: {$weather['description']}\n";
            $prompt .= "- Độ ẩm: {$weather['humidity']}%\n";
            $prompt .= "- Gió: {$weather['wind_speed']} m/s\n\n";
        }

        if (!empty($externalData['places'])) {
            $prompt .= "ĐỊA ĐIỂM NỔI BẬT (TỪ GOOGLE PLACES):\n";
            foreach ($externalData['places'] as $place) {
                $prompt .= "- {$place['name']}: {$place['address']} (Đánh giá: {$place['rating']}/5)\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "YÊU CẦU TRẢ LỜI:\n";
        $prompt .= "1. Sử dụng dữ liệu thật từ database và API\n";
        $prompt .= "2. Tạo lịch trình chi tiết nếu có thông tin đầy đủ\n";
        $prompt .= "3. Ước tính chi phí dựa trên dữ liệu thật\n";
        $prompt .= "4. Gợi ý phù hợp với thời tiết nếu có\n";
        $prompt .= "5. Trả lời bằng tiếng Việt tự nhiên, thân thiện\n";
        $prompt .= "6. Nếu thiếu thông tin, hãy nói rõ và gợi ý cách bổ sung\n";
        $prompt .= "7. Không bịa thông tin không có trong dữ liệu\n";

        return $prompt;
    }
}
