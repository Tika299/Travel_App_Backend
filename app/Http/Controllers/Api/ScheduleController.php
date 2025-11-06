<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Schedule::with(['user', 'checkinPlace'])->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'checkin_place_id' => 'required|exists:checkin_places,id',
            'participants' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'status' => 'required|in:upcoming,completed,planning',
            'progress' => 'required|integer|min:0|max:100',
            'user_id' => 'required|exists:users,id',
        ]);
        $schedule = Schedule::create($validated);
        return response()->json($schedule, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $schedule = Schedule::with(['user', 'checkinPlace'])->findOrFail($id);
        return response()->json($schedule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'checkin_place_id' => 'sometimes|required|exists:checkin_places,id',
            'participants' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'budget' => 'nullable|numeric',
            'status' => 'sometimes|required|in:upcoming,completed,planning',
            'progress' => 'sometimes|required|integer|min:0|max:100',
            'user_id' => 'sometimes|required|exists:users,id',
        ]);
        $schedule->update($validated);
        return response()->json($schedule);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();
        return response()->json(null, 204);
    }

    /**
     * Lấy thông tin thời tiết từ OpenWeatherMap API
     */
    private function getWeatherData($lat, $lon, $startDate, $endDate)
    {
        try {
            $apiKey = env('OPENWEATHER_API_KEY');
            if (!$apiKey) {
                \Log::warning('OpenWeather API key not configured');
                return null;
            }

            // Tính số ngày giữa start và end date
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $daysDiff = $end->diff($start)->days;

            // Lấy thời tiết cho từng ngày
            $weatherData = [];
            for ($i = 0; $i <= $daysDiff; $i++) {
                $currentDate = clone $start;
                $currentDate->add(new \DateInterval("P{$i}D"));
                $timestamp = $currentDate->getTimestamp();

                $response = Http::get("https://api.openweathermap.org/data/2.5/forecast", [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'cnt' => 1
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['list'])) {
                        $weather = $data['list'][0];
                        $weatherData[] = [
                            'date' => $currentDate->format('Y-m-d'),
                            'temperature' => $weather['main']['temp'],
                            'description' => $weather['weather'][0]['description'],
                            'main' => $weather['weather'][0]['main'],
                            'humidity' => $weather['main']['humidity'],
                            'wind_speed' => $weather['wind']['speed'] ?? 0
                        ];
                    }
                }
            }

            return $weatherData;
        } catch (\Exception $e) {
            \Log::error('Weather API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Geocoding: Chuyển địa điểm thành tọa độ
     */
    private function getCoordinates($address)
    {
        try {
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            if (!$apiKey) {
                \Log::warning('Google Maps API key not configured');
                return null;
            }

            $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json", [
                'address' => $address,
                'key' => $apiKey
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    $location = $data['results'][0]['geometry']['location'];
                    return [
                        'lat' => $location['lat'],
                        'lng' => $location['lng']
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Geocoding error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lọc địa điểm theo khoảng cách và ngân sách
     */
    private function filterPlacesByDistanceAndBudget($places, $centerLat, $centerLng, $budget, $maxDistance = 50)
    {
        $filteredPlaces = [];
        
        foreach ($places as $place) {
            if (!isset($place['latitude']) || !isset($place['longitude'])) {
                continue;
            }

            // Tính khoảng cách (Haversine formula)
            $distance = $this->calculateDistance(
                $centerLat, $centerLng,
                $place['latitude'], $place['longitude']
            );

            // Kiểm tra khoảng cách và ngân sách
            if ($distance <= $maxDistance) {
                $place['distance'] = round($distance, 2);
                
                // Nếu có ngân sách, kiểm tra giá
                if ($budget && isset($place['price'])) {
                    if ($place['price'] <= $budget) {
                        $filteredPlaces[] = $place;
                    }
                } else {
                    $filteredPlaces[] = $place;
                }
            }
        }

        // Sắp xếp theo rating và khoảng cách
        usort($filteredPlaces, function($a, $b) {
            $scoreA = ($a['rating'] ?? 0) * 0.7 + (1 / ($a['distance'] ?? 1)) * 0.3;
            $scoreB = ($b['rating'] ?? 0) * 0.7 + (1 / ($b['distance'] ?? 1)) * 0.3;
            return $scoreB <=> $scoreA;
        });

        return $filteredPlaces;
    }

    /**
     * Tính khoảng cách giữa 2 điểm (km)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        return $miles * 1.609344; // Chuyển sang km
    }

    /**
     * Gợi ý lịch trình bằng AI và lưu vào schedules
     */
    public function aiSuggestSchedule(Request $request)
    {
        try {
            $request->validate([
                'prompt' => 'required|string',
                'name' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'checkin_place_id' => 'required|exists:checkin_places,id',
                'participants' => 'required|integer|min:1',
                'user_id' => 'required|exists:users,id',
                'budget' => 'nullable|numeric',
                'filterType' => 'nullable|string|in:weather_only,budget_only,both,general',
                'location' => 'required|string', // Thêm địa điểm để geocoding
            ]);

        // Lấy loại lọc từ request
        $filterType = $request->filterType ?? 'general';
        
        // 1. Geocoding để lấy tọa độ
        $coordinates = $this->getCoordinates($request->location);
        $weatherData = null;
        
        if ($coordinates) {
            // 2. Lấy thông tin thời tiết
            $weatherData = $this->getWeatherData(
                $coordinates['lat'], 
                $coordinates['lng'], 
                $request->start_date, 
                $request->end_date
            );
        }

        // 3. Lấy dữ liệu thực từ database
        $places = \App\Models\CheckinPlace::where('status', 'active')->get()->toArray();
        $hotels = \App\Models\Hotel::all()->toArray();
        $restaurants = \App\Models\Restaurant::all()->toArray();

        // Thêm type cho mỗi loại địa điểm
        foreach ($places as &$place) {
            $place['type'] = 'checkin_place';
            $place['price'] = $place['price'] ?? 0;
        }
        foreach ($hotels as &$hotel) {
            $hotel['type'] = 'hotel';
            $hotel['price'] = 0; // Hotels không có price field
        }
        foreach ($restaurants as &$restaurant) {
            $restaurant['type'] = 'restaurant';
            $restaurant['price'] = 0; // Restaurants không có price field
        }

        // 4. Lọc địa điểm theo khoảng cách và ngân sách
        $filteredPlaces = [];
        $allPlaces = array_merge($places, $hotels, $restaurants);
        
        if ($coordinates) {
            $filteredPlaces = $this->filterPlacesByDistanceAndBudget(
                $allPlaces, 
                $coordinates['lat'], 
                $coordinates['lng'], 
                $request->budget
            );
        } else {
            // Nếu không có coordinates, lấy tất cả địa điểm
            $filteredPlaces = array_slice($allPlaces, 0, 15);
        }

        // Log để debug
        \Log::info('Database data loaded:', [
            'places_count' => count($places),
            'hotels_count' => count($hotels),
            'restaurants_count' => count($restaurants),
            'filtered_places_count' => count($filteredPlaces)
        ]);

        // 5. Tạo prompt động dựa trên filterType
        $enhancedPrompt = $this->createDynamicPrompt(
            $request->prompt,
            $filterType,
            $weatherData,
            $filteredPlaces,
            $request->budget,
            $request->start_date,
            $request->end_date
        );

        // 6. Gọi OpenAI API hoặc sử dụng dữ liệu database thực tế
        $aiEvents = [];
        
        // Kiểm tra nếu có OpenAI API key và không bị lỗi quota
        if (env('OPENAI_API_KEY') && !str_contains(env('OPENAI_API_KEY'), 'sk-')) {
            try {
                $openaiRes = Http::withHeaders([
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $enhancedPrompt]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.7,
                ]);

                $result = $openaiRes->json();
                \Log::info('OpenAI raw result:', [$result]);
                
                if (!empty($result['choices'][0]['message']['content'])) {
                    $content = $result['choices'][0]['message']['content'];
                    \Log::info('OpenAI content:', [$content]);
                    
                    if (preg_match('/\[.*\]/s', $content, $matches)) {
                        $aiEvents = json_decode($matches[0], true);
                    } else {
                        $aiEvents = json_decode($content, true);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('OpenAI API error: ' . $e->getMessage());
            }
        }
        
        // Nếu không có AI response hoặc AI trả về dữ liệu không hợp lệ, tạo lịch trình từ database thực tế
        if (empty($aiEvents) || !is_array($aiEvents)) {
            $aiEvents = $this->createScheduleFromRealDatabase($filteredPlaces, $request->start_date, $request->end_date, $request->location);
            \Log::info('Using real database schedule');
        } else {
            // Kiểm tra và lọc bỏ mock data từ AI response
            $filteredAiEvents = [];
            
            // Lấy danh sách địa điểm thực tế từ database để so sánh
            $realPlaceNames = [];
            foreach ($filteredPlaces as $place) {
                $realPlaceNames[] = strtolower(trim($place['name']));
            }
            
            foreach ($aiEvents as $event) {
                $title = $event['title'] ?? '';
                $location = $event['location'] ?? '';
                
                // Lọc bỏ các sự kiện mock
                $isMockData = strpos($title, 'Hoạt động tự do') !== false ||
                             strpos($title, 'Địa điểm tự chọn') !== false ||
                             strpos($location, 'Địa điểm tự chọn') !== false ||
                             strpos($title, 'Free activity') !== false ||
                             strpos($title, 'Self-chosen') !== false;
                
                // Kiểm tra xem địa điểm có thực sự tồn tại trong database không
                $hasRealLocation = false;
                foreach ($realPlaceNames as $realName) {
                    if (strpos(strtolower($title), $realName) !== false || 
                        strpos(strtolower($location), $realName) !== false) {
                        $hasRealLocation = true;
                        break;
                    }
                }
                
                // Chỉ giữ lại sự kiện có địa điểm thực tế và không phải mock data
                if (!$isMockData && $hasRealLocation) {
                    $filteredAiEvents[] = $event;
                } else {
                    \Log::info('Filtered out mock data or non-database location:', [
                        'title' => $title, 
                        'location' => $location,
                        'isMockData' => $isMockData,
                        'hasRealLocation' => $hasRealLocation
                    ]);
                }
            }
            
            // Nếu sau khi lọc không còn sự kiện nào, tạo từ database
            if (empty($filteredAiEvents)) {
                $aiEvents = $this->createScheduleFromRealDatabase($filteredPlaces, $request->start_date, $request->end_date, $request->location);
                \Log::info('AI returned only mock data or non-database locations, using real database schedule');
            } else {
                $aiEvents = $filteredAiEvents;
                \Log::info('Filtered AI response, kept real database data only');
            }
        }

        // 7. Lưu lịch trình vào DB
        $schedule = Schedule::create([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'checkin_place_id' => $request->checkin_place_id,
            'participants' => $request->participants,
            'description' => 'Lịch trình AI gợi ý với thời tiết và ngân sách',
            'budget' => $request->budget,
            'status' => 'planning',
            'progress' => 0,
            'user_id' => $request->user_id,
        ]);

        return response()->json([
            'schedule' => $schedule,
            'ai_events' => $aiEvents,
            'weather_data' => $weatherData,
            'filtered_places' => array_slice($filteredPlaces, 0, 10), // Trả về top 10
        ], 201);
        } catch (\Exception $e) {
            \Log::error('AI Schedule Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo prompt động dựa trên loại filter
     */
    private function createDynamicPrompt($userPrompt, $filterType, $weatherData, $filteredPlaces, $budget, $startDate, $endDate)
    {
        $basePrompt = "Bạn là trợ lý tạo lịch trình du lịch thông minh. Tạo lịch trình bằng tiếng Việt:\n\n";
        
        // Thêm thông tin thời tiết nếu có
        if ($weatherData && in_array($filterType, ['weather_only', 'both'])) {
            $basePrompt .= "THÔNG TIN THỜI TIẾT:\n";
            foreach ($weatherData as $weather) {
                $basePrompt .= "- {$weather['date']}: {$weather['description']}, {$weather['temperature']}°C\n";
            }
            $basePrompt .= "\n";
        }

        // Thêm thông tin địa điểm thực từ database
        if ($filteredPlaces) {
            $basePrompt .= "ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE:\n";
            
            // Phân loại địa điểm theo type
            $checkinPlaces = array_filter($filteredPlaces, fn($p) => $p['type'] === 'checkin_place');
            $hotels = array_filter($filteredPlaces, fn($p) => $p['type'] === 'hotel');
            $restaurants = array_filter($filteredPlaces, fn($p) => $p['type'] === 'restaurant');
            
            if (!empty($checkinPlaces)) {
                $basePrompt .= "ĐỊA ĐIỂM THAM QUAN:\n";
                foreach (array_slice($checkinPlaces, 0, 8) as $place) {
                    $price = $place['is_free'] ? 'Miễn phí' : number_format($place['price']) . ' VND';
                    $basePrompt .= "- {$place['name']}: {$place['description']}, Rating {$place['rating']}/5, {$price}\n";
                }
                $basePrompt .= "\n";
            }
            
            if (!empty($hotels)) {
                $basePrompt .= "KHÁCH SẠN:\n";
                foreach (array_slice($hotels, 0, 5) as $hotel) {
                    $basePrompt .= "- {$hotel['name']}: {$hotel['description']}, Rating {$hotel['rating']}/5\n";
                }
                $basePrompt .= "\n";
            }
            
            if (!empty($restaurants)) {
                $basePrompt .= "NHÀ HÀNG:\n";
                foreach (array_slice($restaurants, 0, 5) as $restaurant) {
                    $priceRange = $restaurant['price_range'] ?? 'medium';
                    $basePrompt .= "- {$restaurant['name']}: {$restaurant['description']}, Rating {$restaurant['rating']}/5, Giá {$priceRange}\n";
                }
                $basePrompt .= "\n";
            }
        }

        // Thêm ngân sách nếu có
        if ($budget) {
            $basePrompt .= "NGÂN SÁCH: " . number_format($budget) . " VND\n\n";
        }

        // Thêm quy tắc sắp xếp thông minh
        $basePrompt .= "QUY TẮC SẮP XẾP THÔNG MINH:\n";
        $basePrompt .= "1. THỜI GIAN:\n";
        $basePrompt .= "   - 08:00-10:00: Hoạt động ngoài trời (công viên, chùa, bảo tàng)\n";
        $basePrompt .= "   - 10:30-12:00: Tham quan địa điểm văn hóa\n";
        $basePrompt .= "   - 12:00-13:30: Ăn trưa tại nhà hàng\n";
        $basePrompt .= "   - 14:00-16:00: Hoạt động trong nhà (trung tâm thương mại, bảo tàng)\n";
        $basePrompt .= "   - 16:30-18:00: Tham quan chiều\n";
        $basePrompt .= "   - 18:30-20:00: Ăn tối\n";
        $basePrompt .= "   - 20:30-22:00: Hoạt động tối (giải trí, thư giãn)\n\n";
        
        $basePrompt .= "2. ĐỊA ĐIỂM:\n";
        $basePrompt .= "   - Ưu tiên địa điểm gần nhau để tiết kiệm thời gian di chuyển\n";
        $basePrompt .= "   - Sáng: Địa điểm ngoài trời, văn hóa\n";
        $basePrompt .= "   - Trưa: Nhà hàng, quán ăn\n";
        $basePrompt .= "   - Chiều: Địa điểm trong nhà, mua sắm\n";
        $basePrompt .= "   - Tối: Nhà hàng, giải trí\n\n";
        
        $basePrompt .= "3. THỜI GIAN DI CHUYỂN:\n";
        $basePrompt .= "   - Tính toán 15-30 phút di chuyển giữa các địa điểm\n";
        $basePrompt .= "   - Tránh xung đột thời gian\n";
        $basePrompt .= "   - Ưu tiên địa điểm cùng khu vực\n\n";

        $basePrompt .= "QUY TẮC QUAN TRỌNG:\n";
        $basePrompt .= "1. CHỈ SỬ DỤNG ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE, KHÔNG TẠO ĐỊA ĐIỂM GIẢ\n";
        $basePrompt .= "2. KHÔNG SỬ DỤNG 'Hoạt động tự do', 'Địa điểm tự chọn' hoặc các địa điểm không có thật\n";
        $basePrompt .= "3. CHỈ SỬ DỤNG TÊN ĐỊA ĐIỂM CÓ TRONG DANH SÁCH DATABASE\n";
        $basePrompt .= "4. NẾU KHÔNG ĐỦ ĐỊA ĐIỂM, SỬ DỤNG LẠI ĐỊA ĐIỂM ĐÃ CÓ THAY VÌ TẠO MỚI\n\n";

        // Tạo prompt theo loại filter
        switch ($filterType) {
            case 'weather_only':
                $basePrompt .= "QUY TẮC THỜI TIẾT: Ưu tiên hoạt động trong nhà khi trời mưa, hoạt động ngoài trời khi trời đẹp. CHỈ SỬ DỤNG ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE.\n";
                break;
            case 'budget_only':
                $basePrompt .= "QUY TẮC NGÂN SÁCH: Tối ưu chi phí, không vượt quá ngân sách. Ưu tiên địa điểm miễn phí. CHỈ SỬ DỤNG ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE.\n";
                break;
            case 'both':
                $basePrompt .= "QUY TẮC TỔNG HỢP: Kết hợp tối ưu cả thời tiết và ngân sách. CHỈ SỬ DỤNG ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE.\n";
                break;
            default:
                $basePrompt .= "QUY TẮC CHUNG: Tạo lịch trình tổng quát phù hợp với thời gian và địa điểm. CHỈ SỬ DỤNG ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE.\n";
        }

        $basePrompt .= "\nFORMAT JSON:\n";
        $basePrompt .= "[\n";
        $basePrompt .= "  {\n";
        $basePrompt .= "    \"title\": \"Tên hoạt động\",\n";
        $basePrompt .= "    \"start\": \"YYYY-MM-DDTHH:MM:SS\",\n";
        $basePrompt .= "    \"end\": \"YYYY-MM-DDTHH:MM:SS\",\n";
        $basePrompt .= "    \"location\": \"Địa chỉ chi tiết\",\n";
        $basePrompt .= "    \"description\": \"Mô tả hoạt động\",\n";
        $basePrompt .= "    \"cost\": \"Chi phí dự kiến\",\n";
        $basePrompt .= "    \"weather\": \"Thông tin thời tiết\",\n";
        $basePrompt .= "    \"travel_time\": \"Thời gian di chuyển từ địa điểm trước\",\n";
        $basePrompt .= "    \"priority\": \"high/medium/low\"\n";
        $basePrompt .= "  }\n";
        $basePrompt .= "]\n\n";
        $basePrompt .= "Yêu cầu người dùng: {$userPrompt}\n\n";
        $basePrompt .= "Tạo lịch trình chi tiết cho {$startDate} đến {$endDate}. CHỈ SỬ DỤNG ĐỊA ĐIỂM THỰC TẾ TỪ DATABASE, KHÔNG tạo địa điểm chung chung hoặc giả. Tất cả bằng tiếng Việt.";
        $basePrompt .= "\n\nLƯU Ý CUỐI: Nếu không đủ địa điểm trong database, hãy sử dụng lại các địa điểm đã có thay vì tạo mới. KHÔNG BAO GIỜ tạo 'Hoạt động tự do' hoặc 'Địa điểm tự chọn'.";

        return $basePrompt;
    }

    /**
     * Tạo lịch trình từ dữ liệu database thực tế
     */
    private function createScheduleFromRealDatabase($filteredPlaces, $startDate, $endDate, $location)
    {
        // Kiểm tra xem có đủ dữ liệu không
        if (empty($filteredPlaces)) {
            \Log::warning('No real database places available');
            return [];
        }

        $events = [];
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end->modify('+1 day'));
        
        // Phân loại địa điểm theo loại
        $checkinPlaces = array_filter($filteredPlaces, fn($place) => $place['type'] === 'checkin_place');
        $restaurants = array_filter($filteredPlaces, fn($place) => $place['type'] === 'restaurant');
        $hotels = array_filter($filteredPlaces, fn($place) => $place['type'] === 'hotel');
        
        // Chuyển thành array để có thể shuffle
        $checkinPlaces = array_values($checkinPlaces);
        $restaurants = array_values($restaurants);
        $hotels = array_values($hotels);
        
        // Kiểm tra xem có đủ dữ liệu thực tế không
        if (empty($checkinPlaces) && empty($restaurants)) {
            \Log::warning('No real checkin places or restaurants available');
            return [];
        }
        
        // Theo dõi địa điểm đã sử dụng trong toàn bộ lịch trình
        $globalUsedPlaces = [];
        
        $dayCount = 0;
        foreach ($period as $date) {
            $dayCount++;
            $dateStr = $date->format('Y-m-d');
            
            // Tạo lịch trình thông minh cho từng ngày với địa điểm thực tế
            $dayEvents = $this->createSmartDaySchedule(
                $checkinPlaces, 
                $restaurants, 
                $hotels, 
                $dateStr, 
                $dayCount,
                $globalUsedPlaces
            );
            
            $events = array_merge($events, $dayEvents);
        }
        
        return $events;
    }
    
    /**
     * Tạo lịch trình thông minh cho một ngày
     */
    private function createSmartDaySchedule($checkinPlaces, $restaurants, $hotels, $dateStr, $dayCount, &$globalUsedPlaces)
    {
        $events = [];
        
        // Thời gian biểu thông minh - thay đổi theo ngày
        $timeSlots = $this->getDaySpecificTimeSlots($dayCount);
        
        // Xáo trộn địa điểm để tạo sự đa dạng
        $shuffledCheckinPlaces = $this->shufflePlacesForDay($checkinPlaces, $dayCount);
        $shuffledRestaurants = $this->shufflePlacesForDay($restaurants, $dayCount);
        $shuffledHotels = $this->shufflePlacesForDay($hotels, $dayCount);
        
        $usedPlaces = [];
        $currentTime = 0;
        
        foreach ($timeSlots as $slotIndex => $slot) {
            [$startTime, $endTime, $period] = $slot;
            
            // Chọn địa điểm phù hợp cho thời gian này
            $selectedPlace = $this->selectOptimalPlace(
                $shuffledCheckinPlaces, 
                $shuffledRestaurants, 
                $shuffledHotels, 
                $period, 
                $usedPlaces,
                $slotIndex,
                $dayCount,
                $globalUsedPlaces
            );
            
            if ($selectedPlace) {
                $event = [
                    'title' => $this->generateSmartEventTitle($selectedPlace, $period, $slotIndex, $dayCount),
                    'start' => $dateStr . 'T' . $startTime . ':00',
                    'end' => $dateStr . 'T' . $endTime . ':00',
                    'location' => $selectedPlace['address'] ?? $selectedPlace['name'],
                    'description' => $this->generateSmartDescription($selectedPlace, $period, $dayCount),
                    'cost' => $this->generateCost($selectedPlace),
                    'weather' => $this->getWeatherAdvice($period),
                    'travel_time' => $this->calculateTravelTime($currentTime, $slotIndex),
                    'priority' => $this->getActivityPriority($period)
                ];
                
                $events[] = $event;
                $usedPlaces[] = $selectedPlace['id'] ?? $selectedPlace['name'];
                $globalUsedPlaces[] = $selectedPlace['id'] ?? $selectedPlace['name'];
                $currentTime = $slotIndex;
            }
        }
        
        return $events;
    }
    
    /**
     * Lấy thời gian biểu cụ thể cho từng ngày
     */
    private function getDaySpecificTimeSlots($dayCount)
    {
        // Thời gian biểu khác nhau cho từng ngày
        $daySchedules = [
            1 => [ // Ngày 1: Khám phá văn hóa
                ['08:00', '10:00', 'morning'],
                ['10:30', '12:00', 'morning'],
                ['12:00', '13:30', 'lunch'],
                ['14:00', '16:00', 'afternoon'],
                ['16:30', '18:00', 'afternoon'],
                ['18:30', '20:00', 'dinner'],
                ['20:30', '22:00', 'evening']
            ],
            2 => [ // Ngày 2: Tham quan và mua sắm
                ['09:00', '11:00', 'morning'],
                ['11:30', '13:00', 'morning'],
                ['13:00', '14:30', 'lunch'],
                ['15:00', '17:00', 'afternoon'],
                ['17:30', '19:00', 'afternoon'],
                ['19:30', '21:00', 'dinner'],
                ['21:30', '23:00', 'evening']
            ],
            3 => [ // Ngày 3: Giải trí và thư giãn
                ['08:30', '10:30', 'morning'],
                ['11:00', '12:30', 'morning'],
                ['12:30', '14:00', 'lunch'],
                ['14:30', '16:30', 'afternoon'],
                ['17:00', '18:30', 'afternoon'],
                ['19:00', '20:30', 'dinner'],
                ['21:00', '22:30', 'evening']
            ]
        ];
        
        // Nếu nhiều hơn 3 ngày, lặp lại với offset
        $scheduleKey = (($dayCount - 1) % 3) + 1;
        return $daySchedules[$scheduleKey] ?? $daySchedules[1];
    }
    
    /**
     * Xáo trộn địa điểm cho từng ngày để tạo sự đa dạng
     */
    private function shufflePlacesForDay($places, $dayCount)
    {
        if (empty($places)) {
            return [];
        }
        
        // Tạo seed dựa trên ngày để có sự đa dạng nhưng có thể dự đoán
        $seed = $dayCount * 12345;
        srand($seed);
        
        $shuffled = $places;
        shuffle($shuffled);
        
        // Reset seed
        srand();
        
        return $shuffled;
    }
    
    /**
     * Chọn địa điểm tối ưu cho thời gian cụ thể
     */
    private function selectOptimalPlace($checkinPlaces, $restaurants, $hotels, $period, $usedPlaces, $slotIndex, $dayCount, &$globalUsedPlaces)
    {
        $availablePlaces = [];
        
        // Logic chọn địa điểm dựa trên ngày
        $dayFocus = $this->getDayFocus($dayCount);
        
        switch ($period) {
            case 'lunch':
            case 'dinner':
                // Ưu tiên nhà hàng thực tế cho bữa ăn
                $availablePlaces = array_filter($restaurants, function($place) use ($usedPlaces, $globalUsedPlaces) {
                    $placeId = $place['id'] ?? $place['name'];
                    return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces);
                });
                if (empty($availablePlaces)) {
                    $availablePlaces = array_filter($checkinPlaces, function($place) use ($usedPlaces, $globalUsedPlaces) {
                        $placeId = $place['id'] ?? $place['name'];
                        return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces);
                    });
                }
                break;
                
            case 'morning':
                // Sáng sớm - ưu tiên địa điểm thực tế ngoài trời
                $availablePlaces = array_filter($checkinPlaces, function($place) use ($usedPlaces, $dayFocus, $globalUsedPlaces) {
                    $placeId = $place['id'] ?? $place['name'];
                    $isOutdoor = strpos(strtolower($place['name']), 'công viên') !== false ||
                                strpos(strtolower($place['name']), 'bảo tàng') !== false ||
                                strpos(strtolower($place['name']), 'chùa') !== false ||
                                strpos(strtolower($place['name']), 'đền') !== false ||
                                strpos(strtolower($place['name']), 'hồ') !== false ||
                                strpos(strtolower($place['name']), 'vịnh') !== false;
                    
                    // Ưu tiên địa điểm phù hợp với focus của ngày
                    $matchesDayFocus = $this->matchesDayFocus($place, $dayFocus);
                    
                    return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces) && ($isOutdoor || $matchesDayFocus);
                });
                break;
                
            case 'afternoon':
                // Chiều - ưu tiên địa điểm thực tế tham quan, mua sắm
                $availablePlaces = array_filter($checkinPlaces, function($place) use ($usedPlaces, $dayFocus, $globalUsedPlaces) {
                    $placeId = $place['id'] ?? $place['name'];
                    $isIndoor = strpos(strtolower($place['name']), 'trung tâm') !== false ||
                               strpos(strtolower($place['name']), 'mall') !== false ||
                               strpos(strtolower($place['name']), 'bảo tàng') !== false ||
                               strpos(strtolower($place['name']), 'chợ') !== false ||
                               strpos(strtolower($place['name']), 'dinh') !== false ||
                               strpos(strtolower($place['name']), 'nhà thờ') !== false;
                    
                    // Ưu tiên địa điểm phù hợp với focus của ngày
                    $matchesDayFocus = $this->matchesDayFocus($place, $dayFocus);
                    
                    return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces) && ($isIndoor || $matchesDayFocus);
                });
                break;
                
            case 'evening':
                // Tối - ưu tiên địa điểm thực tế giải trí, nhà hàng
                $availablePlaces = array_filter($restaurants, function($place) use ($usedPlaces, $globalUsedPlaces) {
                    $placeId = $place['id'] ?? $place['name'];
                    return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces);
                });
                if (empty($availablePlaces)) {
                    $availablePlaces = array_filter($checkinPlaces, function($place) use ($usedPlaces, $globalUsedPlaces) {
                        $placeId = $place['id'] ?? $place['name'];
                        return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces);
                    });
                }
                break;
                
            default:
                // Các thời gian khác - lấy tất cả địa điểm thực tế chưa dùng
                $availablePlaces = array_filter($checkinPlaces, function($place) use ($usedPlaces, $globalUsedPlaces) {
                    $placeId = $place['id'] ?? $place['name'];
                    return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces);
                });
        }
        
        // Sắp xếp theo độ ưu tiên và chọn địa điểm đầu tiên
        if (!empty($availablePlaces)) {
            return reset($availablePlaces);
        }
        
        // Nếu không có địa điểm phù hợp, lấy bất kỳ địa điểm thực tế nào chưa dùng
        $allPlaces = array_merge($checkinPlaces, $restaurants, $hotels);
        $unusedPlaces = array_filter($allPlaces, function($place) use ($usedPlaces, $globalUsedPlaces) {
            $placeId = $place['id'] ?? $place['name'];
            return !in_array($placeId, $usedPlaces) && !in_array($placeId, $globalUsedPlaces);
        });
        
        return !empty($unusedPlaces) ? reset($unusedPlaces) : null;
    }
    
    /**
     * Lấy focus của ngày cụ thể
     */
    private function getDayFocus($dayCount)
    {
        $dayFocuses = [
            1 => 'cultural',      // Ngày 1: Văn hóa
            2 => 'shopping',      // Ngày 2: Mua sắm
            3 => 'entertainment', // Ngày 3: Giải trí
            4 => 'nature',        // Ngày 4: Thiên nhiên
            5 => 'food'           // Ngày 5: Ẩm thực
        ];
        
        $focusKey = (($dayCount - 1) % 5) + 1;
        return $dayFocuses[$focusKey] ?? 'cultural';
    }
    
    /**
     * Kiểm tra địa điểm có phù hợp với focus của ngày không
     */
    private function matchesDayFocus($place, $dayFocus)
    {
        $placeName = strtolower($place['name']);
        $placeDesc = strtolower($place['description'] ?? '');
        
        switch ($dayFocus) {
            case 'cultural':
                return strpos($placeName, 'bảo tàng') !== false ||
                       strpos($placeName, 'chùa') !== false ||
                       strpos($placeName, 'đền') !== false ||
                       strpos($placeName, 'nhà thờ') !== false ||
                       strpos($placeDesc, 'văn hóa') !== false;
                       
            case 'shopping':
                return strpos($placeName, 'trung tâm') !== false ||
                       strpos($placeName, 'mall') !== false ||
                       strpos($placeName, 'chợ') !== false ||
                       strpos($placeName, 'siêu thị') !== false ||
                       strpos($placeDesc, 'mua sắm') !== false;
                       
            case 'entertainment':
                return strpos($placeName, 'công viên') !== false ||
                       strpos($placeName, 'vui chơi') !== false ||
                       strpos($placeName, 'giải trí') !== false ||
                       strpos($placeDesc, 'giải trí') !== false;
                       
            case 'nature':
                return strpos($placeName, 'công viên') !== false ||
                       strpos($placeName, 'rừng') !== false ||
                       strpos($placeName, 'hồ') !== false ||
                       strpos($placeDesc, 'thiên nhiên') !== false;
                       
            case 'food':
                return strpos($placeName, 'nhà hàng') !== false ||
                       strpos($placeName, 'quán') !== false ||
                       strpos($placeName, 'cafe') !== false ||
                       strpos($placeDesc, 'ẩm thực') !== false;
                       
            default:
                return false;
        }
    }
    
    /**
     * Tạo tiêu đề sự kiện thông minh
     */
    private function generateSmartEventTitle($place, $period, $slotIndex, $dayCount)
    {
        $activities = [
            'morning' => [
                'Khám phá buổi sáng tại ' . $place['name'],
                'Tham quan sáng sớm ' . $place['name'],
                'Dạo chơi buổi sáng ' . $place['name'],
                'Khám phá ngày ' . $dayCount . ' tại ' . $place['name'],
                'Tham quan ' . $place['name'] . ' - Ngày ' . $dayCount
            ],
            'lunch' => [
                'Thưởng thức bữa trưa tại ' . $place['name'],
                'Ăn trưa tại ' . $place['name'],
                'Nghỉ trưa và ăn tại ' . $place['name'],
                'Bữa trưa ngày ' . $dayCount . ' tại ' . $place['name'],
                'Thưởng thức ẩm thực tại ' . $place['name']
            ],
            'afternoon' => [
                'Tham quan chiều tại ' . $place['name'],
                'Khám phá buổi chiều ' . $place['name'],
                'Hoạt động chiều tại ' . $place['name'],
                'Tham quan ' . $place['name'] . ' - Chiều ngày ' . $dayCount,
                'Khám phá ' . $place['name'] . ' vào buổi chiều'
            ],
            'dinner' => [
                'Thưởng thức bữa tối tại ' . $place['name'],
                'Ăn tối tại ' . $place['name'],
                'Dinner tại ' . $place['name'],
                'Bữa tối ngày ' . $dayCount . ' tại ' . $place['name'],
                'Thưởng thức ẩm thực tối tại ' . $place['name']
            ],
            'evening' => [
                'Hoạt động tối tại ' . $place['name'],
                'Giải trí buổi tối ' . $place['name'],
                'Thư giãn tối tại ' . $place['name'],
                'Hoạt động tối ngày ' . $dayCount . ' tại ' . $place['name'],
                'Giải trí tại ' . $place['name'] . ' - Tối ngày ' . $dayCount
            ]
        ];
        
        $periodActivities = $activities[$period] ?? $activities['afternoon'];
        $index = ($slotIndex + $dayCount) % count($periodActivities);
        return $periodActivities[$index];
    }
    
    /**
     * Tạo mô tả thông minh
     */
    private function generateSmartDescription($place, $period, $dayCount)
    {
        $dayDescriptions = [
            1 => 'Ngày đầu tiên của chuyến đi, ',
            2 => 'Ngày thứ hai, ',
            3 => 'Ngày thứ ba, ',
            4 => 'Ngày thứ tư, ',
            5 => 'Ngày thứ năm, '
        ];
        
        $dayPrefix = $dayDescriptions[$dayCount] ?? 'Ngày ' . $dayCount . ', ';
        
        $descriptions = [
            'morning' => $dayPrefix . 'thời điểm lý tưởng để tham quan và chụp ảnh. Thời tiết mát mẻ, ít đông đúc.',
            'lunch' => $dayPrefix . 'thưởng thức ẩm thực địa phương và nghỉ ngơi sau buổi sáng.',
            'afternoon' => $dayPrefix . 'tiếp tục khám phá và tìm hiểu văn hóa địa phương.',
            'dinner' => $dayPrefix . 'bữa tối thịnh soạn sau một ngày tham quan.',
            'evening' => $dayPrefix . 'hoạt động nhẹ nhàng, thư giãn trước khi về khách sạn.'
        ];
        
        return $descriptions[$period] ?? $dayPrefix . 'hoạt động tham quan và khám phá.';
    }
    
    /**
     * Lấy lời khuyên thời tiết
     */
    private function getWeatherAdvice($period)
    {
        $advice = [
            'morning' => 'Thời tiết mát mẻ, thích hợp cho hoạt động ngoài trời',
            'lunch' => 'Nắng gắt, nên tìm nơi có mái che hoặc trong nhà',
            'afternoon' => 'Nhiệt độ cao, cần mang theo nước và kem chống nắng',
            'dinner' => 'Thời tiết dễ chịu, thích hợp cho bữa tối ngoài trời',
            'evening' => 'Mát mẻ, thích hợp cho hoạt động buổi tối'
        ];
        
        return $advice[$period] ?? 'Thời tiết thuận lợi cho hoạt động';
    }
    
    /**
     * Tính thời gian di chuyển
     */
    private function calculateTravelTime($currentSlot, $nextSlot)
    {
        $travelTimes = [
            0 => 'Không cần di chuyển',
            1 => 'Di chuyển 15-20 phút',
            2 => 'Di chuyển 20-30 phút',
            3 => 'Di chuyển 30-45 phút'
        ];
        
        $slotDiff = $nextSlot - $currentSlot;
        return $travelTimes[$slotDiff] ?? 'Di chuyển 15-30 phút';
    }
    
    /**
     * Lấy độ ưu tiên hoạt động
     */
    private function getActivityPriority($period)
    {
        $priorities = [
            'lunch' => 'high',
            'dinner' => 'high',
            'morning' => 'medium',
            'afternoon' => 'medium',
            'evening' => 'low'
        ];
        
        return $priorities[$period] ?? 'medium';
    }
    
    /**
     * Tạo chi phí dựa trên loại địa điểm
     */
    private function generateCost($place)
    {
        if ($place['type'] === 'checkin_place') {
            if ($place['is_free'] ?? false) {
                return 'Miễn phí';
            } else {
                return number_format($place['price'] ?? 50000) . ' VND';
            }
        } elseif ($place['type'] === 'restaurant') {
            $priceRanges = [
                'low' => '50.000 - 150.000 VND',
                'medium' => '150.000 - 300.000 VND',
                'high' => '300.000 - 500.000 VND'
            ];
            $priceRange = $place['price_range'] ?? 'medium';
            return $priceRanges[$priceRange] ?? $priceRanges['medium'];
        } else {
            return 'Liên hệ để biết giá';
        }
    }

    /**
     * Lưu event đơn giản từ calendar
     */
    public function storeEvent(Request $request)
    {
        // Kiểm tra user đã đăng nhập chưa
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để lưu event',
                'require_login' => true
            ], 401);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|string',
            'end' => 'required|string',
            'all_day' => 'boolean',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'repeat' => 'nullable|string|in:none,daily,weekly,monthly,yearly',
            'hotel_id' => 'nullable|exists:hotels,id',
            'restaurant_id' => 'nullable|exists:restaurants,id',
            'checkin_place_id' => 'nullable|exists:checkin_places,id',
        ]);

        // Kiểm tra end date phải sau hoặc bằng start date
        if (strtotime($validated['end']) < strtotime($validated['start'])) {
            return response()->json([
                'message' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu',
                'errors' => ['end' => ['Ngày kết thúc phải sau hoặc bằng ngày bắt đầu']]
            ], 422);
        }

        // Xử lý địa điểm được chọn
        $checkinPlaceId = $validated['checkin_place_id'] ?? null;
        $hotelId = $validated['hotel_id'] ?? null;
        $restaurantId = $validated['restaurant_id'] ?? null;
        
        // Nếu không có địa điểm nào được chọn, sử dụng checkin_place đầu tiên làm mặc định
        if (!$checkinPlaceId && !$hotelId && !$restaurantId) {
            $checkinPlace = \App\Models\CheckInPlace::first();
            if ($checkinPlace) {
                $checkinPlaceId = $checkinPlace->id;
            }
        }

        // Tự động detect all-day event dựa trên format datetime
        $isAllDay = $validated['all_day'] ?? false;
        
        // Nếu không có all_day flag, kiểm tra format datetime
        if (!$isAllDay) {
            $startHasTime = strpos($validated['start'], 'T') !== false;
            $endHasTime = strpos($validated['end'], 'T') !== false;
            $isAllDay = !$startHasTime || !$endHasTime;
        }

        // Convert format để Laravel hiểu đúng
        $startDate = $validated['start'];
        $endDate = $validated['end'];
        
        // Nếu có 'T' thì convert thành format Laravel
        if (strpos($startDate, 'T') !== false) {
            $startDate = str_replace('T', ' ', $startDate);
        }
        if (strpos($endDate, 'T') !== false) {
            $endDate = str_replace('T', ' ', $endDate);
        }

        // Kiểm tra xem đã có event tương tự chưa
        $existingEvent = Schedule::where('user_id', auth()->id())
            ->where('name', $validated['title'])
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();

        if ($existingEvent) {
            \Log::info('Event đã tồn tại, không tạo duplicate:', $existingEvent->toArray());
            return response()->json($existingEvent);
        }

        \Log::info('Saving event to database:', [
            'name' => $validated['title'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'original_start' => $validated['start'],
            'original_end' => $validated['end']
        ]);

        $schedule = Schedule::create([
            'name' => $validated['title'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'description' => $validated['description'] ?? '',
            'user_id' => auth()->id(),
            'status' => 'upcoming',
            'progress' => 0,
            'participants' => 1,
            'budget' => 0,
            'checkin_place_id' => $checkinPlaceId,
            'hotel_id' => $hotelId,
            'restaurant_id' => $restaurantId,
        ]);

        return response()->json($schedule, 201);
    }

    /**
     * Lấy tất cả events của user đã đăng nhập
     */
        public function getUserEvents()
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để xem events',
                'require_login' => true
            ], 401);
        }

        $events = [];
        
        // Lấy Schedule events (events chính)
        $schedules = Schedule::where('user_id', auth()->id())
            ->select('id', 'name as title', 'start_date', 'end_date', 'description', 'status', 'user_id', 'checkin_place_id', 'hotel_id', 'restaurant_id')
            ->with(['user:id,name', 'checkinPlace:id,name,address', 'hotel:id,name,address', 'restaurant:id,name,address'])
            ->get();
            
        foreach ($schedules as $event) {
            // Chuyển id thành string để tương thích với CalendarFull
            $event->id = (string) $event->id;
            
            // Convert format từ database về ISO format cho frontend
            $startDate = $event->start_date;
            $endDate = $event->end_date;
            
            // Xử lý start date
            if (strlen($startDate) <= 10) {
                $event->start = $startDate;
            } else {
                if (strpos($startDate, ' ') !== false) {
                    $event->start = str_replace(' ', 'T', $startDate);
                } else {
                    $event->start = $startDate;
                }
            }
            
            // Xử lý end date - thêm 1 ngày nếu là all-day event để hiển thị đúng
            if (strlen($endDate) <= 10) {
                // Nếu end_date chỉ có date (all-day event), thêm 1 ngày để hiển thị đúng
                $endDateTime = new \DateTime($endDate);
                $endDateTime->add(new \DateInterval('P1D')); // Thêm 1 ngày
                $event->end = $endDateTime->format('Y-m-d');
            } else {
                // Nếu end_date có time, kiểm tra xem có phải 00:00:00 không
                if (strpos($endDate, ' ') !== false) {
                    $dateTime = new \DateTime($endDate);
                    $timeOnly = $dateTime->format('H:i:s');
                    
                    if ($timeOnly === '00:00:00') {
                        // Nếu time là 00:00:00, coi như all-day event và thêm 1 ngày
                        $dateTime->add(new \DateInterval('P1D'));
                        $event->end = $dateTime->format('Y-m-d');
                    } else {
                        // Nếu có time khác 00:00:00, giữ nguyên
                        $event->end = str_replace(' ', 'T', $endDate);
                    }
                } else {
                    $event->end = $endDate;
                }
            }
            
            // Tự động detect allDay dựa trên format datetime
            $startHasTime = strpos($event->start, 'T') !== false;
            $endHasTime = strpos($event->end, 'T') !== false;
            $event->allDay = !$startHasTime || !$endHasTime;
            
            // Thêm user name (chỉ lấy name string)
            $event->user = $event->user ? $event->user->name : null;
            
            // Thêm thông tin địa điểm
            $event->location_info = null;
            if ($event->hotel) {
                $event->location_info = [
                    'type' => 'hotel',
                    'name' => $event->hotel->name,
                    'address' => $event->hotel->address,
                    'id' => $event->hotel_id
                ];
            } elseif ($event->restaurant) {
                $event->location_info = [
                    'type' => 'restaurant',
                    'name' => $event->restaurant->name,
                    'address' => $event->restaurant->address,
                    'id' => $event->restaurant_id
                ];
            } elseif ($event->checkinPlace) {
                $event->location_info = [
                    'type' => 'attraction',
                    'name' => $event->checkinPlace->name,
                    'address' => $event->checkinPlace->address,
                    'id' => $event->checkin_place_id
                ];
            }
            
            \Log::info('Event processed:', [
                'id' => $event->id,
                'title' => $event->title,
                'user_id' => $event->user_id,
                'user_name' => $event->user,
                'user_relationship' => $event->user ? 'loaded' : 'null'
            ]);
            
            $events[] = $event;
        }
        
        // Lấy ItineraryEvent (events con)
        $itineraryEvents = \App\Models\ItineraryEvent::whereHas('schedule', function($query) {
                $query->where('user_id', auth()->id());
            })
            ->with(['schedule:id,name', 'checkinPlace:id,name,address', 'hotel:id,name,address', 'restaurant:id,name,address'])
            ->get();
            
        foreach ($itineraryEvents as $event) {
            // Tạo event object cho calendar
            $calendarEvent = (object) [
                'id' => 'itinerary_' . $event->id,
                'title' => $event->title,
                'start' => $event->date->format('Y-m-d') . 'T' . substr($event->start_time, 11, 5),
                'end' => $event->date->format('Y-m-d') . 'T' . substr($event->end_time, 11, 5),
                'allDay' => false,
                'type' => 'itinerary',
                'schedule_id' => $event->schedule_id,
                'description' => $event->description,
                'cost' => $event->cost,
                'location' => $event->location,
                'event_type' => $event->type,
                'order_index' => $event->order_index
            ];
            
            // Thêm thông tin địa điểm
            $calendarEvent->location_info = null;
            if ($event->hotel) {
                $calendarEvent->location_info = [
                    'type' => 'hotel',
                    'name' => $event->hotel->name,
                    'address' => $event->hotel->address,
                    'id' => $event->hotel_id
                ];
            } elseif ($event->restaurant) {
                $calendarEvent->location_info = [
                    'type' => 'restaurant',
                    'name' => $event->restaurant->name,
                    'address' => $event->restaurant->address,
                    'id' => $event->restaurant_id
                ];
            } elseif ($event->checkinPlace) {
                $calendarEvent->location_info = [
                    'type' => 'attraction',
                    'name' => $event->checkinPlace->name,
                    'address' => $event->checkinPlace->address,
                    'id' => $event->checkin_place_id
                ];
            }
            
            $events[] = $calendarEvent;
        }

        return response()->json($events);
    }

    /**
     * Cập nhật event khi kéo sang ngày khác
     */
    public function updateEvent(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để cập nhật event',
                'require_login' => true
            ], 401);
        }

        $validated = $request->validate([
            'start' => 'required|string',
            'end' => 'required|string',
        ]);

        \Log::info('Update event request:', [
            'id' => $id,
            'start' => $validated['start'],
            'end' => $validated['end'],
            'start_type' => gettype($validated['start']),
            'end_type' => gettype($validated['end'])
        ]);

        // Tìm event và kiểm tra quyền sở hữu
        $schedule = Schedule::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$schedule) {
            return response()->json([
                'message' => 'Không tìm thấy event hoặc không có quyền cập nhật'
            ], 404);
        }

        // Convert format để Laravel hiểu đúng
        $startDate = $validated['start'];
        $endDate = $validated['end'];
        
        // Nếu có 'T' thì convert thành format Laravel và loại bỏ .000Z
        if (strpos($startDate, 'T') !== false) {
            $startDate = str_replace('T', ' ', $startDate);
            $startDate = preg_replace('/\.\d{3}Z$/', '', $startDate);
        }
        if (strpos($endDate, 'T') !== false) {
            $endDate = str_replace('T', ' ', $endDate);
            $endDate = preg_replace('/\.\d{3}Z$/', '', $endDate);
        }

        \Log::info('Updating database:', [
            'id' => $schedule->id,
            'old_start_date' => $schedule->start_date,
            'old_end_date' => $schedule->end_date,
            'new_start_date' => $startDate,
            'new_end_date' => $endDate,
            'startDate_type' => gettype($startDate),
            'endDate_type' => gettype($endDate)
        ]);

        try {
            // Log data trước khi update
            \Log::info('About to update event:', [
                'id' => $schedule->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_date_type' => gettype($startDate),
                'end_date_type' => gettype($endDate)
            ]);
            
            // Cập nhật event
            $schedule->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            \Log::info('Event updated successfully');

            return response()->json($schedule);
        } catch (\Exception $e) {
            \Log::error('Error updating event:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Lỗi khi cập nhật event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEventInfo(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để cập nhật event',
                'require_login' => true
            ], 401);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|string',
            'end_date' => 'required|string',
            'description' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        // Tìm event và kiểm tra quyền sở hữu
        $schedule = Schedule::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$schedule) {
            return response()->json([
                'message' => 'Không tìm thấy event hoặc không có quyền cập nhật'
            ], 404);
        }

        // Convert format để Laravel hiểu đúng
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];
        
        // Nếu có 'T' thì convert thành format Laravel và loại bỏ .000Z
        if (strpos($startDate, 'T') !== false) {
            $startDate = str_replace('T', ' ', $startDate);
            $startDate = preg_replace('/\.\d{3}Z$/', '', $startDate);
        }
        if (strpos($endDate, 'T') !== false) {
            $endDate = str_replace('T', ' ', $endDate);
            $endDate = preg_replace('/\.\d{3}Z$/', '', $endDate);
        }

        try {
            // Cập nhật event
            $schedule->update([
                'name' => $validated['title'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $validated['description'] ?? '',
            ]);

            \Log::info('Event info updated successfully');

            return response()->json($schedule);
        } catch (\Exception $e) {
            \Log::error('Error updating event info:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Lỗi khi cập nhật event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteEvent($id)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để xóa event',
                'require_login' => true
            ], 401);
        }

        try {
            $schedule = Schedule::where('id', $id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$schedule) {
                return response()->json([
                    'message' => 'Không tìm thấy event hoặc không có quyền xóa'
                ], 404);
            }

            \Log::info('Deleting event from database:', [
                'id' => $id,
                'title' => $schedule->name
            ]);

            $schedule->delete();

            return response()->json(['message' => 'Event deleted successfully']);
        } catch (\Exception $e) {
            \Log::error('Error deleting event:', $e->getMessage());
            return response()->json([
                'message' => 'Lỗi khi xóa event: ' . $e->getMessage()
            ], 500);
        }
    }

    public function shareEvent(Request $request, $id)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập để chia sẻ event',
                'require_login' => true
            ], 401);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:254',
            'message' => 'nullable|string|max:1000'
        ], [
            'email.required' => 'Email là bắt buộc',
            'email.email' => 'Email không đúng định dạng',
            'email.max' => 'Email quá dài (tối đa 254 ký tự)',
            'message.max' => 'Tin nhắn quá dài (tối đa 1000 ký tự)'
        ]);

        try {
            // Kiểm tra nếu là AI event (không có trong database)
            if (strpos($id, 'ai-') === 0) {
                return response()->json([
                    'message' => 'Không thể chia sẻ event AI. Vui lòng lưu event trước khi chia sẻ.'
                ], 400);
            }

            $schedule = Schedule::where('id', $id)
                ->where('user_id', auth()->id())
                ->with('user:id,name')
                ->first();

            if (!$schedule) {
                return response()->json([
                    'message' => 'Không tìm thấy event hoặc không có quyền chia sẻ'
                ], 404);
            }

            // Chuẩn bị dữ liệu event
            $eventData = [
                'title' => $schedule->name,
                'start' => $schedule->start_date,
                'end' => $schedule->end_date,
                'description' => $schedule->description,
                'location' => $schedule->location ?? ''
            ];

            // Chuẩn bị dữ liệu sender
            $senderData = [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email
            ];

            // Gửi email
            \Mail::to($validated['email'])->send(new \App\Mail\EventShareMail($eventData, $senderData));

            \Log::info('Event shared via email:', [
                'event_id' => $id,
                'recipient_email' => $validated['email'],
                'sender' => auth()->user()->name
            ]);

            return response()->json(['message' => 'Event đã được chia sẻ thành công']);
        } catch (\Exception $e) {
            \Log::error('Error sharing event:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Lỗi khi chia sẻ event: ' . $e->getMessage()
            ], 500);
        }
    }
}
