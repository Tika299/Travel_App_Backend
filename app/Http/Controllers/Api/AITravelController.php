<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CheckinPlace;
use App\Models\Hotel;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\WeatherService;
use App\Services\ConversationService;
use App\Services\RAGService;
use App\Services\SmartPlaceSelectionService;

class AITravelController extends Controller
{
    protected $conversationService;
    protected $ragService;
    protected $smartPlaceService;

    public function __construct(ConversationService $conversationService, RAGService $ragService, SmartPlaceSelectionService $smartPlaceService)
    {
        $this->conversationService = $conversationService;
        $this->ragService = $ragService;
        $this->smartPlaceService = $smartPlaceService;
    }
    public function generateItinerary(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'destination' => 'required|string|max:255',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
                'budget' => 'required|numeric|min:100000',
                'travelers' => 'required|integer|min:1|max:10',
                'preferences' => 'nullable|array',
                'preferences.*' => 'string',
                'suggestWeather' => 'nullable|boolean',
                'suggestBudget' => 'nullable|boolean'
            ]);

            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $daysCount = $startDate->diffInDays($endDate) + 1;

            // Kiểm tra giới hạn 5 ngày
            if ($daysCount > 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Để tạo lịch trình hơn 5 ngày, bạn cần là thành viên VIP của IPSUM Travel. Vui lòng nâng cấp tài khoản để sử dụng tính năng này.',
                    'upgrade_required' => true,
                    'max_days' => 5,
                    'requested_days' => $daysCount
                ], 403);
            }

            // Lấy dữ liệu từ database
            $data = $this->getTravelData($validated['destination']);

            // Lấy thông tin thời tiết nếu được yêu cầu
            $weatherData = null;
            $weatherRecommendations = null;
            if ($validated['suggestWeather'] ?? false) {
                $weatherService = new WeatherService();
                
                // Chuyển đổi tên thành phố sang tiếng Anh để tránh lỗi encoding
                $cityMap = [
                    'TP. Hồ Chí Minh' => 'Ho Chi Minh City',
                    'Hồ Chí Minh' => 'Ho Chi Minh City',
                    'Sài Gòn' => 'Ho Chi Minh City',
                    'Đà Nẵng' => 'Da Nang',
                    'Hà Nội' => 'Hanoi',
                    'Nha Trang' => 'Nha Trang',
                    'Phú Quốc' => 'Phu Quoc',
                    'Huế' => 'Hue',
                    'Hội An' => 'Hoi An'
                ];
                
                $englishCityName = $cityMap[$validated['destination']] ?? $validated['destination'];
                $weatherData = $weatherService->getWeather($englishCityName);
                $weatherRecommendations = $weatherService->getWeatherRecommendations($weatherData);
                
                // Lọc địa điểm dựa trên thời tiết
                if ($weatherData['success'] && isset($data['checkin_places'])) {
                    $data['checkin_places'] = $weatherService->filterPlacesByWeather($data['checkin_places'], $weatherRecommendations);
                    $data['hotels'] = $weatherService->filterPlacesByWeather($data['hotels'], $weatherRecommendations);
                    $data['restaurants'] = $weatherService->filterPlacesByWeather($data['restaurants'], $weatherRecommendations);
                }
            }

            // Tạo prompt cho OpenAI
            $prompt = $this->createAIPrompt($validated, $data, $daysCount, $weatherData, $weatherRecommendations);

            // Gọi OpenAI API
            $itinerary = $this->callOpenAI($prompt, $validated['start_date'], $validated['end_date']);
            
            // Validate itinerary response

            // KHÔNG lưu vào database ngay, chỉ trả về dữ liệu để hiển thị popup xác nhận
            // Tính toán lại thông tin cho response
            $actualDaysCount = isset($itinerary['days']) ? count($itinerary['days']) : 1;
            $requestedDaysCount = Carbon::parse($validated['start_date'])->diffInDays($validated['end_date']) + 1;
            $actualDaysCount = min($actualDaysCount, $requestedDaysCount);
            $actualEndDate = Carbon::parse($validated['start_date'])->addDays($actualDaysCount - 1)->format('Y-m-d');

            return response()->json([
                'success' => true,
                'message' => 'Lịch trình đã được tạo thành công!',
                'data' => [
                    'summary' => [
                        'destination' => $validated['destination'],
                        'duration' => $actualDaysCount . ' ngày',
                        'budget' => number_format($validated['budget']) . ' VND',
                        'travelers' => $validated['travelers'] . ' người',
                        'actual_end_date' => $actualEndDate
                    ],
                    // Thêm dữ liệu itinerary gốc để frontend có thể hiển thị trong popup
                    'itinerary_data' => [
                        'summary' => [
                            'destination' => $validated['destination'],
                            'total_cost' => $itinerary['summary']['total_cost'] ?? 0,
                            'daily_average' => $itinerary['summary']['daily_average'] ?? 0,
                            'days_count' => $actualDaysCount,
                            'total_activities' => isset($itinerary['days']) ? array_sum(array_map(function($day) {
                                return isset($day['activities']) ? count($day['activities']) : 0;
                            }, $itinerary['days'])) : 0
                        ],
                        'days' => $itinerary['days'] ?? []
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AI Travel Planning Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo lịch trình. Vui lòng thử lại sau.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function getTravelData($destination)
    {
        // Map destination to region
        $regionMap = [
            'TP. Hồ Chí Minh' => 'Nam',
            'Hồ Chí Minh' => 'Nam',
            'Sài Gòn' => 'Nam',
            'Hà Nội' => 'Bắc',
            'Đà Nẵng' => 'Trung',
            'Huế' => 'Trung',
            'Hội An' => 'Trung',
            'Nha Trang' => 'Trung',
            'Phú Quốc' => 'Nam',
            'Đà Lạt' => 'Nam'
        ];
        
        $region = $regionMap[$destination] ?? null;
        
        // Tìm kiếm địa điểm dựa trên destination và region
        $checkinPlaces = CheckinPlace::where(function($query) use ($destination, $region) {
            $query->where('name', 'like', '%' . $destination . '%')
                  ->orWhere('address', 'like', '%' . $destination . '%');
            
            // Tự động nhận diện tỉnh thành từ destination
            if (str_contains(strtolower($destination), 'hồ chí minh') || str_contains(strtolower($destination), 'tp.hcm') || str_contains(strtolower($destination), 'sài gòn')) {
                $query->orWhere('address', 'like', '%TP.HCM%')
                      ->orWhere('address', 'like', '%Quận 1%')
                      ->orWhere('address', 'like', '%Quận 2%')
                      ->orWhere('address', 'like', '%Quận 3%')
                      ->orWhere('address', 'like', '%Quận 4%')
                      ->orWhere('address', 'like', '%Quận 5%')
                      ->orWhere('address', 'like', '%Quận 6%')
                      ->orWhere('address', 'like', '%Quận 7%')
                      ->orWhere('address', 'like', '%Quận 8%')
                      ->orWhere('address', 'like', '%Quận 9%')
                      ->orWhere('address', 'like', '%Quận 10%')
                      ->orWhere('address', 'like', '%Quận 11%')
                      ->orWhere('address', 'like', '%Quận 12%')
                      ->orWhere('address', 'like', '%Bình Thạnh%')
                      ->orWhere('address', 'like', '%Tân Bình%')
                      ->orWhere('address', 'like', '%Phú Nhuận%')
                      ->orWhere('address', 'like', '%Gò Vấp%')
                      ->orWhere('address', 'like', '%Tân Phú%')
                      ->orWhere('address', 'like', '%Bình Tân%')
                      ->orWhere('address', 'like', '%Củ Chi%')
                      ->orWhere('address', 'like', '%Hóc Môn%')
                      ->orWhere('address', 'like', '%Bình Chánh%')
                      ->orWhere('address', 'like', '%Nhà Bè%')
                      ->orWhere('address', 'like', '%Cần Giờ%');
            } elseif (str_contains(strtolower($destination), 'đà nẵng')) {
                $query->orWhere('address', 'like', '%Đà Nẵng%')
                      ->orWhere('address', 'like', '%Quận Hải Châu%')
                      ->orWhere('address', 'like', '%Quận Thanh Khê%')
                      ->orWhere('address', 'like', '%Quận Sơn Trà%')
                      ->orWhere('address', 'like', '%Quận Ngũ Hành Sơn%')
                      ->orWhere('address', 'like', '%Quận Liên Chiểu%')
                      ->orWhere('address', 'like', '%Quận Cẩm Lệ%')
                      ->orWhere('address', 'like', '%Huyện Hòa Vang%')
                      ->orWhere('address', 'like', '%Huyện Hoàng Sa%');
            } elseif (str_contains(strtolower($destination), 'hà nội')) {
                $query->orWhere('address', 'like', '%Hà Nội%')
                      ->orWhere('address', 'like', '%Quận Ba Đình%')
                      ->orWhere('address', 'like', '%Quận Hoàn Kiếm%')
                      ->orWhere('address', 'like', '%Quận Hai Bà Trưng%')
                      ->orWhere('address', 'like', '%Quận Đống Đa%')
                      ->orWhere('address', 'like', '%Quận Tây Hồ%')
                      ->orWhere('address', 'like', '%Quận Cầu Giấy%')
                      ->orWhere('address', 'like', '%Quận Thanh Xuân%')
                      ->orWhere('address', 'like', '%Quận Hoàng Mai%')
                      ->orWhere('address', 'like', '%Quận Long Biên%')
                      ->orWhere('address', 'like', '%Quận Nam Từ Liêm%')
                      ->orWhere('address', 'like', '%Quận Bắc Từ Liêm%')
                      ->orWhere('address', 'like', '%Huyện Thanh Trì%')
                      ->orWhere('address', 'like', '%Huyện Gia Lâm%')
                      ->orWhere('address', 'like', '%Huyện Đông Anh%')
                      ->orWhere('address', 'like', '%Huyện Sóc Sơn%')
                      ->orWhere('address', 'like', '%Huyện Ba Vì%')
                      ->orWhere('address', 'like', '%Huyện Phúc Thọ%')
                      ->orWhere('address', 'like', '%Huyện Thạch Thất%')
                      ->orWhere('address', 'like', '%Huyện Quốc Oai%')
                      ->orWhere('address', 'like', '%Huyện Chương Mỹ%')
                      ->orWhere('address', 'like', '%Huyện Thanh Oai%')
                      ->orWhere('address', 'like', '%Huyện Thường Tín%')
                      ->orWhere('address', 'like', '%Huyện Phú Xuyên%')
                      ->orWhere('address', 'like', '%Huyện Ứng Hòa%')
                      ->orWhere('address', 'like', '%Huyện Mỹ Đức%');
            }
        })
        ->limit(20)
        ->get();

        // Tìm hotels với logic tìm kiếm chi tiết hơn
        $hotels = Hotel::where(function($query) use ($destination) {
            $query->where('name', 'like', '%' . $destination . '%')
                  ->orWhere('address', 'like', '%' . $destination . '%');
            
            // Tự động nhận diện tỉnh thành từ destination
            if (str_contains(strtolower($destination), 'hồ chí minh') || str_contains(strtolower($destination), 'tp.hcm') || str_contains(strtolower($destination), 'sài gòn')) {
                $query->orWhere('address', 'like', '%TP.HCM%')
                      ->orWhere('address', 'like', '%Quận 1%')
                      ->orWhere('address', 'like', '%Quận 2%')
                      ->orWhere('address', 'like', '%Quận 3%')
                      ->orWhere('address', 'like', '%Quận 4%')
                      ->orWhere('address', 'like', '%Quận 5%')
                      ->orWhere('address', 'like', '%Quận 6%')
                      ->orWhere('address', 'like', '%Quận 7%')
                      ->orWhere('address', 'like', '%Quận 8%')
                      ->orWhere('address', 'like', '%Quận 9%')
                      ->orWhere('address', 'like', '%Quận 10%')
                      ->orWhere('address', 'like', '%Quận 11%')
                      ->orWhere('address', 'like', '%Quận 12%')
                      ->orWhere('address', 'like', '%Bình Thạnh%')
                      ->orWhere('address', 'like', '%Tân Bình%')
                      ->orWhere('address', 'like', '%Phú Nhuận%')
                      ->orWhere('address', 'like', '%Gò Vấp%')
                      ->orWhere('address', 'like', '%Tân Phú%')
                      ->orWhere('address', 'like', '%Bình Tân%')
                      ->orWhere('address', 'like', '%Củ Chi%')
                      ->orWhere('address', 'like', '%Hóc Môn%')
                      ->orWhere('address', 'like', '%Bình Chánh%')
                      ->orWhere('address', 'like', '%Nhà Bè%')
                      ->orWhere('address', 'like', '%Cần Giờ%');
            } elseif (str_contains(strtolower($destination), 'đà nẵng')) {
                $query->orWhere('address', 'like', '%Đà Nẵng%')
                      ->orWhere('address', 'like', '%Quận Hải Châu%')
                      ->orWhere('address', 'like', '%Quận Thanh Khê%')
                      ->orWhere('address', 'like', '%Quận Sơn Trà%')
                      ->orWhere('address', 'like', '%Quận Ngũ Hành Sơn%')
                      ->orWhere('address', 'like', '%Quận Liên Chiểu%')
                      ->orWhere('address', 'like', '%Quận Cẩm Lệ%')
                      ->orWhere('address', 'like', '%Huyện Hòa Vang%')
                      ->orWhere('address', 'like', '%Huyện Hoàng Sa%');
            } elseif (str_contains(strtolower($destination), 'hà nội')) {
                $query->orWhere('address', 'like', '%Hà Nội%')
                      ->orWhere('address', 'like', '%Quận Ba Đình%')
                      ->orWhere('address', 'like', '%Quận Hoàn Kiếm%')
                      ->orWhere('address', 'like', '%Quận Hai Bà Trưng%')
                      ->orWhere('address', 'like', '%Quận Đống Đa%')
                      ->orWhere('address', 'like', '%Quận Tây Hồ%')
                      ->orWhere('address', 'like', '%Quận Cầu Giấy%')
                      ->orWhere('address', 'like', '%Quận Thanh Xuân%')
                      ->orWhere('address', 'like', '%Quận Hoàng Mai%')
                      ->orWhere('address', 'like', '%Quận Long Biên%')
                      ->orWhere('address', 'like', '%Quận Nam Từ Liêm%')
                      ->orWhere('address', 'like', '%Quận Bắc Từ Liêm%')
                      ->orWhere('address', 'like', '%Huyện Thanh Trì%')
                      ->orWhere('address', 'like', '%Huyện Gia Lâm%')
                      ->orWhere('address', 'like', '%Huyện Đông Anh%')
                      ->orWhere('address', 'like', '%Huyện Sóc Sơn%')
                      ->orWhere('address', 'like', '%Huyện Ba Vì%')
                      ->orWhere('address', 'like', '%Huyện Phúc Thọ%')
                      ->orWhere('address', 'like', '%Huyện Thạch Thất%')
                      ->orWhere('address', 'like', '%Huyện Quốc Oai%')
                      ->orWhere('address', 'like', '%Huyện Chương Mỹ%')
                      ->orWhere('address', 'like', '%Huyện Thanh Oai%')
                      ->orWhere('address', 'like', '%Huyện Thường Tín%')
                      ->orWhere('address', 'like', '%Huyện Phú Xuyên%')
                      ->orWhere('address', 'like', '%Huyện Ứng Hòa%')
                      ->orWhere('address', 'like', '%Huyện Mỹ Đức%');
            }
        })
        ->limit(15)
        ->get();

        // Tìm restaurants với logic tìm kiếm chi tiết hơn
        $restaurants = Restaurant::where(function($query) use ($destination) {
            $query->where('name', 'like', '%' . $destination . '%')
                  ->orWhere('address', 'like', '%' . $destination . '%');
            
            // Tự động nhận diện tỉnh thành từ destination
            if (str_contains(strtolower($destination), 'hồ chí minh') || str_contains(strtolower($destination), 'tp.hcm') || str_contains(strtolower($destination), 'sài gòn')) {
                $query->orWhere('address', 'like', '%TP.HCM%')
                      ->orWhere('address', 'like', '%Quận 1%')
                      ->orWhere('address', 'like', '%Quận 2%')
                      ->orWhere('address', 'like', '%Quận 3%')
                      ->orWhere('address', 'like', '%Quận 4%')
                      ->orWhere('address', 'like', '%Quận 5%')
                      ->orWhere('address', 'like', '%Quận 6%')
                      ->orWhere('address', 'like', '%Quận 7%')
                      ->orWhere('address', 'like', '%Quận 8%')
                      ->orWhere('address', 'like', '%Quận 9%')
                      ->orWhere('address', 'like', '%Quận 10%')
                      ->orWhere('address', 'like', '%Quận 11%')
                      ->orWhere('address', 'like', '%Quận 12%')
                      ->orWhere('address', 'like', '%Bình Thạnh%')
                      ->orWhere('address', 'like', '%Tân Bình%')
                      ->orWhere('address', 'like', '%Phú Nhuận%')
                      ->orWhere('address', 'like', '%Gò Vấp%')
                      ->orWhere('address', 'like', '%Tân Phú%')
                      ->orWhere('address', 'like', '%Bình Tân%')
                      ->orWhere('address', 'like', '%Củ Chi%')
                      ->orWhere('address', 'like', '%Hóc Môn%')
                      ->orWhere('address', 'like', '%Bình Chánh%')
                      ->orWhere('address', 'like', '%Nhà Bè%')
                      ->orWhere('address', 'like', '%Cần Giờ%');
            } elseif (str_contains(strtolower($destination), 'đà nẵng')) {
                $query->orWhere('address', 'like', '%Đà Nẵng%')
                      ->orWhere('address', 'like', '%Quận Hải Châu%')
                      ->orWhere('address', 'like', '%Quận Thanh Khê%')
                      ->orWhere('address', 'like', '%Quận Sơn Trà%')
                      ->orWhere('address', 'like', '%Quận Ngũ Hành Sơn%')
                      ->orWhere('address', 'like', '%Quận Liên Chiểu%')
                      ->orWhere('address', 'like', '%Quận Cẩm Lệ%')
                      ->orWhere('address', 'like', '%Huyện Hòa Vang%')
                      ->orWhere('address', 'like', '%Huyện Hoàng Sa%');
            } elseif (str_contains(strtolower($destination), 'hà nội')) {
                $query->orWhere('address', 'like', '%Hà Nội%')
                      ->orWhere('address', 'like', '%Quận Ba Đình%')
                      ->orWhere('address', 'like', '%Quận Hoàn Kiếm%')
                      ->orWhere('address', 'like', '%Quận Hai Bà Trưng%')
                      ->orWhere('address', 'like', '%Quận Đống Đa%')
                      ->orWhere('address', 'like', '%Quận Tây Hồ%')
                      ->orWhere('address', 'like', '%Quận Cầu Giấy%')
                      ->orWhere('address', 'like', '%Quận Thanh Xuân%')
                      ->orWhere('address', 'like', '%Quận Hoàng Mai%')
                      ->orWhere('address', 'like', '%Quận Long Biên%')
                      ->orWhere('address', 'like', '%Quận Nam Từ Liêm%')
                      ->orWhere('address', 'like', '%Quận Bắc Từ Liêm%')
                      ->orWhere('address', 'like', '%Huyện Thanh Trì%')
                      ->orWhere('address', 'like', '%Huyện Gia Lâm%')
                      ->orWhere('address', 'like', '%Huyện Đông Anh%')
                      ->orWhere('address', 'like', '%Huyện Sóc Sơn%')
                      ->orWhere('address', 'like', '%Huyện Ba Vì%')
                      ->orWhere('address', 'like', '%Huyện Phúc Thọ%')
                      ->orWhere('address', 'like', '%Huyện Thạch Thất%')
                      ->orWhere('address', 'like', '%Huyện Quốc Oai%')
                      ->orWhere('address', 'like', '%Huyện Chương Mỹ%')
                      ->orWhere('address', 'like', '%Huyện Thanh Oai%')
                      ->orWhere('address', 'like', '%Huyện Thường Tín%')
                      ->orWhere('address', 'like', '%Huyện Phú Xuyên%')
                      ->orWhere('address', 'like', '%Huyện Ứng Hòa%')
                      ->orWhere('address', 'like', '%Huyện Mỹ Đức%');
            }
        })
        ->where('name', 'not like', '%Group%')
        ->where('name', 'not like', '%LLC%')
        ->where('name', 'not like', '%Inc%')
        ->where('name', 'not like', '%Ltd%')
        ->where('name', 'not like', '%PLC%')
        ->where('name', 'not like', '%Sons%')
        ->where('name', 'not like', '%and%')
        ->where('name', 'not like', '%-%')
        ->where('name', 'not like', '%[0-9]%')
        ->limit(15)
        ->get();

        return [
            'checkin_places' => $checkinPlaces,
            'hotels' => $hotels,
            'restaurants' => $restaurants
        ];
    }

    private function createAIPrompt($validated, $data, $daysCount, $weatherData = null, $weatherRecommendations = null)
    {
        $destination = $validated['destination'];
        $budget = $validated['budget'];
        $travelers = $validated['travelers'];
        $preferences = $validated['preferences'] ?? [];
        $suggestWeather = $validated['suggestWeather'] ?? false;
        $suggestBudget = $validated['suggestBudget'] ?? false;

        $prompt = "Bạn là một chuyên gia du lịch Việt Nam. Hãy tạo lịch trình du lịch chi tiết cho {$daysCount} ngày tại {$destination} với ngân sách {$budget} VND cho {$travelers} người.\n\n";

        // Thêm thông tin thời tiết nếu có
        if ($weatherData && $weatherData['success'] && $weatherRecommendations) {
            $weatherInfo = $weatherData['data'];
            $prompt .= "🌤️ THÔNG TIN THỜI TIẾT HIỆN TẠI TẠI {$destination}:\n";
            $prompt .= "- Nhiệt độ: {$weatherInfo['temperature']}°C\n";
            $prompt .= "- Mô tả: {$weatherInfo['description']}\n";
            $prompt .= "- Độ ẩm: {$weatherInfo['humidity']}%\n";
            if ($weatherInfo['rain'] > 0) $prompt .= "- Có mưa: {$weatherInfo['rain']}mm\n";
            if ($weatherInfo['snow'] > 0) $prompt .= "- Có tuyết: {$weatherInfo['snow']}mm\n";
            $prompt .= "- Gió: {$weatherInfo['wind_speed']} m/s\n\n";

            $prompt .= "📋 GỢI Ý HOẠT ĐỘNG DỰA TRÊN THỜI TIẾT:\n";
            foreach ($weatherRecommendations as $type => $rec) {
                $prompt .= "- {$rec['message']}\n";
                if (isset($rec['activities']['indoor'])) {
                    $prompt .= "  + Hoạt động trong nhà: " . implode(', ', $rec['activities']['indoor']) . "\n";
                }
                if (isset($rec['activities']['outdoor'])) {
                    $prompt .= "  + Hoạt động ngoài trời: " . implode(', ', $rec['activities']['outdoor']) . "\n";
                }
            }
            $prompt .= "\n";
        }

        // Thêm thông tin về smart suggestions
        if ($suggestWeather) {
            $prompt .= "Yêu cầu: Tạo gợi ý hoạt động phù hợp với thời tiết hiện tại tại {$destination}.\n";
        }
        
        if ($suggestBudget) {
            $prompt .= "Yêu cầu: Tối ưu hóa ngân sách, đề xuất hoạt động phù hợp với ngân sách {$budget} VND.\n";
        }
        
        if ($suggestWeather && $suggestBudget) {
            $prompt .= "Yêu cầu: Kết hợp cả hai - tạo gợi ý phù hợp với thời tiết và tối ưu ngân sách.\n";
        }
        
        if (!$suggestWeather && !$suggestBudget) {
            $prompt .= "Yêu cầu: Tạo lịch trình tổng quát không phụ thuộc vào thời tiết hoặc tối ưu ngân sách.\n";
        }
        
        $prompt .= "\n";

        // Thêm preferences
        if (!empty($preferences)) {
            $prompt .= "Sở thích: " . implode(', ', $preferences) . "\n\n";
        }

        // Thêm dữ liệu địa điểm
        if (isset($data['checkin_places']) && count($data['checkin_places']) > 0) {
            $prompt .= "Các địa điểm tham quan có sẵn:\n";
            foreach ($data['checkin_places'] as $place) {
                $price = $place->is_free ? 'Miễn phí' : number_format($place->price) . ' VND';
                $prompt .= "- {$place->name}: {$place->description} (Giá: {$price})\n";
            }
            $prompt .= "\n";
        }

        // Thêm dữ liệu khách sạn
        if (isset($data['hotels']) && count($data['hotels']) > 0) {
            $prompt .= "Các khách sạn có sẵn:\n";
            foreach ($data['hotels'] as $hotel) {
                $minPrice = $hotel->rooms->min('price_per_night') ?? 0;
                $prompt .= "- {$hotel->name}: {$hotel->description} (Từ " . number_format($minPrice) . " VND/đêm)\n";
            }
            $prompt .= "\n";
        }

        // Thêm dữ liệu nhà hàng
        if (isset($data['restaurants']) && count($data['restaurants']) > 0) {
            $prompt .= "Các nhà hàng có sẵn:\n";
            foreach ($data['restaurants'] as $restaurant) {
                $prompt .= "- {$restaurant->name}: {$restaurant->description} (Khoảng giá: {$restaurant->price_range})\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "QUY TẮC TỐI ƯU HÓA LỊCH TRÌNH:\n";
        $prompt .= "1. CHỈ chọn địa điểm từ danh sách có sẵn. Nếu không có địa điểm phù hợp, để trống.\n";
        $prompt .= "2. HOẠT ĐỘNG BAN NGÀY (06:00 – 17:30):\n";
        $prompt .= "   - Tham quan di tích lịch sử, bảo tàng, chùa, nhà thờ, công viên lớn, biển (nếu thời tiết đẹp)\n";
        $prompt .= "   - Trải nghiệm hoạt động ngoài trời: tắm biển, trekking, leo núi, tham quan làng nghề\n";
        $prompt .= "   - Ăn trưa tại quán ăn hoặc nhà hàng địa phương\n";
        $prompt .= "   - TRÁNH phố đi bộ sôi động (như Bùi Viện, Nguyễn Huệ) vào ban ngày\n";
        $prompt .= "3. HOẠT ĐỘNG BUỔI TỐI (18:00 – 23:00):\n";
        $prompt .= "   - Tham quan phố đi bộ, chợ đêm, công viên giải trí về đêm, bar/cafe view đẹp\n";
        $prompt .= "   - Ăn tối tại nhà hàng phù hợp ngân sách\n";
        $prompt .= "   - Nếu thời tiết xấu, ưu tiên hoạt động trong nhà (nhà hàng, quán cafe, khu vui chơi indoor)\n";
        $prompt .= "   - TRÁNH công viên buổi tối trừ khi có đèn và đông vui\n";
        $prompt .= "4. CÂN NHẮC THỜI TIẾT:\n";
        $prompt .= "   - Nếu mưa hoặc nắng gắt, tránh hoạt động ngoài trời\n";
        $prompt .= "   - Nếu trời mát, ưu tiên hoạt động dạo bộ, tham quan ngoài trời\n";
        $prompt .= "5. CÂN NHẮC NGÂN SÁCH:\n";
        $prompt .= "   - Chọn địa điểm, nhà hàng, khách sạn phù hợp mức chi tiêu\n";
        $prompt .= "   - TUYỆT ĐỐI tôn trọng ngân sách {$budget} VND, không được vượt quá\n";
        $prompt .= "   - Phân bổ chi phí hợp lý: Ăn uống 40%, Khách sạn 30%, Tham quan 20%, Chi phí khác 10%\n";
        $prompt .= "   - Tính toán chi phí thực tế dựa trên số người {$travelers} người\n";
        $prompt .= "6. CÂN NHẮC KHOẢNG CÁCH:\n";
        $prompt .= "   - Các hoạt động trong cùng buổi nên ở gần nhau\n";
        $prompt .= "   - Hạn chế di chuyển quá 30km giữa 2 hoạt động liên tiếp\n";
        $prompt .= "7. THỜI GIAN DI CHUYỂN:\n";
        $prompt .= "   - Chèn buffer 15-30 phút giữa các hoạt động để tránh kẹt xe\n";
        $prompt .= "   - Không sắp xếp hoạt động quá sát nhau (ví dụ: 08:30-10:30 → 11:00-12:30)\n";
        $prompt .= "8. THỨ TỰ HOẠT ĐỘNG TRONG NGÀY:\n";
        $prompt .= "   - Sáng: Hoạt động nhẹ, tham quan gần\n";
        $prompt .= "   - Trưa: Ăn trưa, nghỉ ngơi\n";
        $prompt .= "   - Chiều: Hoạt động chính ngoài trời hoặc di chuyển xa\n";
        $prompt .= "   - Tối: Ăn tối, tham quan/giải trí buổi tối\n";
        $prompt .= "9. LỊCH TRÌNH CHI TIẾT THEO NGÀY:\n";
        $prompt .= "   - Mỗi ngày chỉ ở 1 khách sạn duy nhất (không đổi khách sạn)\n";
        $prompt .= "   - KHÔNG lặp lại địa điểm trong cùng 1 ngày\n";
        $prompt .= "   - Thời gian đa dạng, không đồng bộ giữa các ngày\n";
        $prompt .= "   - Sắp xếp hoạt động gần nhau về mặt địa lý để giảm thời gian di chuyển\n";
        $prompt .= "   - Lịch trình mẫu cho 1 ngày:\n";
        $prompt .= "     * 06:00-07:30: Ăn sáng tại nhà hàng quán ăn đặc sản địa phương\n";
        $prompt .= "     * 08:00-11:00: Tham quan di tích, bảo tàng, chùa (hoạt động ban ngày)\n";
        $prompt .= "     * 11:30-12:30: Ăn trưa tại nhà hàng quán ăn đặc sản địa phương\n";
        $prompt .= "     * 13:00-14:00: Nghỉ ngơi, di chuyển\n";
        $prompt .= "     * 14:00-17:00: Tham quan công viên, chợ, hoạt động ngoài trời\n";
        $prompt .= "     * 17:30-18:30: Di chuyển về khách sạn, nghỉ ngơi\n";
        $prompt .= "     * 19:00-20:00: Ăn tối tại nhà hàng phù hợp\n";
        $prompt .= "     * 20:30-22:30: Hoạt động buổi tối (phố đi bộ, chợ đêm, cafe rooftop)\n";
        $prompt .= "10. BẮT BUỘC sử dụng chính xác tên địa điểm, khách sạn, nhà hàng từ danh sách có sẵn. KHÔNG được tự tạo tên mới.\n";
        $prompt .= "11. CƠ CHẾ CHẤM ĐIỂM ĐỊA ĐIỂM (để chọn tối ưu):\n";
        $prompt .= "    - Phù hợp thời tiết: +3 điểm nếu đúng loại hoạt động, -2 điểm nếu ngược lại\n";
        $prompt .= "    - Khoảng cách: +2 điểm nếu <=5km, +1 điểm nếu <=10km, 0 điểm nếu >10km\n";
        $prompt .= "    - Ngân sách: +2 điểm nếu trong ngân sách, -1 điểm nếu vượt\n";
        $prompt .= "    - Thời gian phù hợp: +2 điểm nếu hoạt động ban ngày vào ban ngày, +2 điểm nếu hoạt động buổi tối vào buổi tối\n";
        $prompt .= "    - Đánh giá chung: Chọn các địa điểm có tổng điểm cao nhất để đưa vào lịch trình\n";
        $prompt .= "14. Trả về kết quả dưới dạng JSON với cấu trúc:\n";
        $prompt .= "{\n";
        $prompt .= "  \"summary\": {\"total_cost\": number, \"daily_average\": number},\n";
        $prompt .= "  \"days\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"day\": number,\n";
        $prompt .= "      \"date\": \"YYYY-MM-DD\",\n";
        $prompt .= "      \"activities\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"time\": \"HH:MM\",\n";
        $prompt .= "          \"type\": \"attraction|hotel|restaurant\",\n";
        $prompt .= "          \"name\": \"string\",\n";
        $prompt .= "          \"description\": \"string\",\n";
        $prompt .= "          \"location\": \"string (địa chỉ chi tiết)\",\n";
        $prompt .= "          \"cost\": number,\n";
        $prompt .= "          \"duration\": \"string\"\n";
        $prompt .= "        }\n";
        $prompt .= "      ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    private function callOpenAI($prompt, $startDate = null, $endDate = null, $isQuestion = false)
    {
        $apiKey = config('services.openai.api_key');
        
        if (!$apiKey) {
            // Fallback: Tạo lịch trình mẫu nếu không có API key
            if ($isQuestion) {
                return ['answer' => 'Tôi không thể trả lời câu hỏi này ngay bây giờ. Bạn có muốn tôi giúp tạo lịch trình du lịch không?'];
            }
            return $this->generateSampleItinerary($prompt, $startDate, $endDate);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một trợ lý du lịch thông minh tại Việt Nam.

Nhiệm vụ:
1. Hiểu và ghi nhớ ngữ cảnh từ các tin nhắn trước đó trong cùng hội thoại.
2. Khi người dùng hỏi, xác định:
   - Địa điểm (ví dụ: Đà Nẵng, Hà Nội, Phú Quốc...)
   - Mục đích du lịch (tham quan, nghỉ dưỡng, công tác...)
   - Ngày khởi hành và thời gian ở lại.
3. Nếu thông tin chưa đầy đủ, hãy hỏi lại để làm rõ trước khi trả lời.
4. Trả lời bao gồm:
   - Gợi ý lịch trình chi tiết (theo ngày).
   - Danh sách địa điểm nổi bật kèm mô tả ngắn.
   - Ước tính chi phí: vé, khách sạn, ăn uống, phương tiện, tổng chi phí.
5. Không bịa giá nếu không có dữ liệu. Nếu không biết giá chính xác, hãy nói "Giá ước tính khoảng ..." hoặc "Cần kiểm tra thêm".
6. Luôn giữ văn phong thân thiện, dễ hiểu, trả lời bằng tiếng Việt.
7. Ưu tiên trả lời dạng danh sách hoặc bảng để dễ đọc.
8. Sử dụng dữ liệu thật từ database khi có thể.
9. KHÔNG BAO GIỜ trả lời câu hỏi về toán học, khoa học, công nghệ, chính trị, hoặc các chủ đề khác không liên quan đến du lịch.

QUAN TRỌNG VỀ ĐỊA ĐIỂM:
- Khi người dùng hỏi về một địa điểm cụ thể (như Đà Nẵng, Nha Trang, Sapa...), bạn PHẢI trả lời về địa điểm đó
- KHÔNG BAO GIỜ nói rằng bạn chỉ tập trung vào một địa điểm khác
- KHÔNG BAO GIỜ từ chối câu hỏi về bất kỳ địa điểm nào ở Việt Nam
- Luôn trả lời hữu ích về địa điểm được hỏi

Yêu cầu quan trọng:
- Trả lời bằng tiếng Việt có dấu đầy đủ và chính xác
- Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ
- Viết hoa đúng quy tắc tiếng Việt
- Sử dụng từ ngữ tự nhiên, thân thiện
- KHÔNG BAO GIỜ từ chối câu hỏi về du lịch, thời tiết, địa điểm
- Luôn cố gắng trả lời hữu ích với thông tin có sẵn
- FORMAT: Xuống hàng hợp lý, tên địa điểm in hoa, TUYỆT ĐỐI KHÔNG số thứ tự (1. 2. 3.)
- Không sử dụng HTML tags'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                
                // Fix encoding issues - đảm bảo UTF-8
                $content = mb_convert_encoding($content, 'UTF-8', 'AUTO');
                $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content); // Loại bỏ control characters
                
                // Clean content để tránh lỗi encoding
                $content = $this->cleanJsonContent($content);
                
                // Thử decode với nhiều method khác nhau
                $decoded = null;
                $jsonError = null;
                
                // Method 1: Decode bình thường
                $decoded = json_decode($content, true);
                $jsonError = json_last_error();
                
                // Method 2: Nếu lỗi, thử với flags
                if ($jsonError !== JSON_ERROR_NONE) {
                    $decoded = json_decode($content, true, 512, JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                    $jsonError = json_last_error();
                }
                
                // Method 3: Nếu vẫn lỗi, thử extract JSON từ text
                if ($jsonError !== JSON_ERROR_NONE) {
                    // Tìm JSON trong content
                    if (preg_match('/\{.*\}/s', $content, $matches)) {
                        $jsonContent = $matches[0];
                        $decoded = json_decode($jsonContent, true, 512, JSON_INVALID_UTF8_IGNORE);
                        $jsonError = json_last_error();
                    }
                }
                
                // Process OpenAI response
                
                // Kiểm tra nếu JSON decode thất bại
                if ($jsonError !== JSON_ERROR_NONE || $decoded === null) {
                    Log::error('JSON decode failed:', [
                        'content' => $content,
                        'error' => json_last_error_msg()
                    ]);
                    
                    if ($isQuestion) {
                        // Trả về text trực tiếp cho câu hỏi
                        return ['answer' => $content];
                    }
                    
                    Log::error('JSON decode failed, using sample data');
                    return $this->generateSampleItinerary($prompt, $startDate, $endDate);
                }
                
                if ($isQuestion) {
                    // Trả về text trực tiếp cho câu hỏi với headers UTF-8
                    return response()->json(['answer' => $content], 200, [
                        'Content-Type' => 'application/json; charset=UTF-8'
                    ]);
                }
                
                return $decoded;
            } else {
                Log::error('OpenAI API Error: ' . $response->body());
                if ($isQuestion) {
                    return ['answer' => 'Tôi không thể trả lời câu hỏi này ngay bây giờ. Bạn có muốn tôi giúp tạo lịch trình du lịch không?'];
                }
                return $this->generateSampleItinerary($prompt, $startDate, $endDate);
            }
        } catch (\Exception $e) {
            Log::error('OpenAI API Exception: ' . $e->getMessage());
            if ($isQuestion) {
                return ['answer' => 'Tôi không thể trả lời câu hỏi này ngay bây giờ. Bạn có muốn tôi giúp tạo lịch trình du lịch không?'];
            }
            return $this->generateSampleItinerary($prompt, $startDate, $endDate);
        }
    }

    private function generateSampleItinerary($prompt, $startDate = null, $endDate = null)
    {
        // Tạo lịch trình mẫu khi không có OpenAI API
        // Tính số ngày từ start_date và end_date
        if ($startDate && $endDate) {
            $daysCount = Carbon::parse($startDate)->diffInDays($endDate) + 1;
        } else {
            // Parse số ngày từ prompt nếu không có ngày cụ thể
            preg_match('/(\d+)\s*ngày/', $prompt, $matches);
            $daysCount = isset($matches[1]) ? (int)$matches[1] : 3;
        }
        
        // Lấy dữ liệu thực từ database theo destination
        $destination = 'Việt Nam';
        if (preg_match('/(?:đến|tại|ở)\s+([^,\n]+)/', $prompt, $matches)) {
            $destination = trim($matches[1]);
        }
        
        // Lọc theo destination - sử dụng nhiều từ khóa
        $destinationKeywords = [];
        if (stripos($destination, 'hồ chí minh') !== false || stripos($destination, 'sài gòn') !== false) {
            $destinationKeywords = ['Hồ Chí Minh', 'TP.HCM', 'TPHCM', 'Quận 1', 'Quận 3', 'Quận 5', 'Quận 7', 'Quận 10', 'Bình Thạnh', 'Tân Bình'];
        } elseif (stripos($destination, 'đà nẵng') !== false) {
            $destinationKeywords = ['Đà Nẵng', 'Hòa Vang', 'Sơn Trà', 'Ngũ Hành Sơn'];
        } elseif (stripos($destination, 'hà nội') !== false) {
            $destinationKeywords = ['Hà Nội', 'Ba Đình', 'Hoàn Kiếm', 'Đống Đa', 'Hai Bà Trưng'];
        } else {
            $destinationKeywords = [$destination];
        }
        
        // Tạo query với OR conditions - lấy nhiều hơn để đảm bảo đủ cho tất cả ngày
        $hotels = \App\Models\Hotel::where(function($query) use ($destinationKeywords) {
            foreach ($destinationKeywords as $keyword) {
                $query->orWhere('address', 'LIKE', '%' . $keyword . '%');
            }
        })->take(50)->get();
        
        $restaurants = \App\Models\Restaurant::where(function($query) use ($destinationKeywords) {
            foreach ($destinationKeywords as $keyword) {
                $query->orWhere('address', 'LIKE', '%' . $keyword . '%');
            }
        })->take(50)->get();
        
        $attractions = \App\Models\CheckinPlace::where(function($query) use ($destinationKeywords) {
            foreach ($destinationKeywords as $keyword) {
                $query->orWhere('address', 'LIKE', '%' . $keyword . '%');
            }
        })->take(50)->get();
        
        // Nếu không tìm thấy, lấy random từ toàn bộ database
        if ($hotels->count() === 0) {
            $hotels = \App\Models\Hotel::take(50)->get();
        }
        if ($restaurants->count() === 0) {
            $restaurants = \App\Models\Restaurant::take(50)->get();
        }
        if ($attractions->count() === 0) {
            $attractions = \App\Models\CheckinPlace::take(50)->get();
        }
        
        // Parse destination từ prompt
        $destination = 'Việt Nam';
        if (preg_match('/(?:đến|tại|ở)\s+([^,\n]+)/', $prompt, $matches)) {
            $destination = trim($matches[1]);
        }
        
        // Tính toán ngân sách thực tế từ prompt
        $budget = 5000000; // Default
        if (preg_match('/(\d+)\s*(triệu|tr|nghìn|k|đồng|vnd)/i', $prompt, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);
            
            if (in_array($unit, ['triệu', 'tr'])) {
                $budget = $amount * 1000000;
            } elseif (in_array($unit, ['nghìn', 'k'])) {
                $budget = $amount * 1000;
            } elseif (in_array($unit, ['đồng', 'vnd'])) {
                $budget = $amount;
            }
        }
        
        // Trích xuất số người từ prompt
        $travelers = 2; // Default
        if (preg_match('/(\d+)\s*người/', $prompt, $matches)) {
            $travelers = (int)$matches[1];
        }
        
        // Phân bổ ngân sách hợp lý
        $foodBudget = $budget * 0.4; // 40% cho ăn uống
        $hotelBudget = $budget * 0.3; // 30% cho khách sạn
        $attractionBudget = $budget * 0.2; // 20% cho tham quan
        $otherBudget = $budget * 0.1; // 10% cho chi phí khác
        
        // Tính toán chi phí theo số người
        $foodBudgetPerPerson = $foodBudget / $travelers;
        $attractionBudgetPerPerson = $attractionBudget / $travelers;
        $otherBudgetPerPerson = $otherBudget / $travelers;
        
        $itinerary = [
            'summary' => [
                'destination' => $destination,
                'total_cost' => $budget,
                'daily_average' => round($budget / $daysCount),
                'days_count' => $daysCount,
                'total_activities' => $daysCount * 3 // Ước tính 3 hoạt động/ngày
            ],
            'days' => []
        ];

        // Theo dõi địa điểm đã sử dụng để tránh lặp lại giữa các ngày
        $usedRestaurantIds = [];
        $usedAttractionIds = [];
        $usedPlaceNames = []; // Theo dõi tên địa điểm để tránh trùng

        // Đảm bảo số lượng event đều đặn mỗi ngày
        $eventsPerDay = 8; // Cố định 8 hoạt động/ngày: 3 bữa ăn + 4 tham quan + 1 buổi tối

        for ($dayIndex = 0; $dayIndex < $daysCount; $dayIndex++) {
            $dayActivities = [];
            
            // 1. Thêm ăn sáng (06:00-07:30)
            if ($restaurants->count() > 0) {
                $availableRestaurants = $restaurants->whereNotIn('id', $usedRestaurantIds);
                if ($availableRestaurants->count() > 0) {
                    // Sử dụng smart selection cho bữa sáng
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'breakfast');
                    $breakfast = $this->smartPlaceService->selectSmartPlace($availableRestaurants, $context, $usedRestaurantIds);
                    
                    if (!$breakfast) {
                        $breakfast = $availableRestaurants->first();
                    }
                    $breakfastTimes = ['06:00', '06:30', '07:00'];
                    $dayActivities[] = [
                        'time' => $breakfastTimes[$dayIndex % 3],
                        'type' => 'restaurant',
                        'name' => mb_convert_encoding($breakfast->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($breakfast->description ?? 'Ăn sáng', 'UTF-8', 'UTF-8'),
                        'cost' => round($foodBudgetPerPerson / ($daysCount * 3)),
                        'duration' => '1.5 giờ',
                        'restaurant_id' => $breakfast->id,
                        'location' => mb_convert_encoding($breakfast->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedRestaurantIds[] = $breakfast->id;
                    $usedPlaceNames[] = strtolower($breakfast->name);
                }
            }
            
            // 2. Thêm hoạt động buổi sáng (08:00-10:00) - Di tích, bảo tàng, chùa
            if ($attractions->count() > 0) {
                $availableAttractions = $attractions->whereNotIn('id', $usedAttractionIds);
                if ($availableAttractions->count() > 0) {
                    // Ưu tiên địa điểm ban ngày và tránh trùng tên, lọc chặt chẽ theo thành phố
                    $daytimePlaces = $availableAttractions->filter(function($place) use ($usedPlaceNames, $destination) {
                        $name = strtolower($place->name);
                        $description = strtolower($place->description ?? '');
                        $address = strtolower($place->address ?? '');
                        
                        // Lọc chặt chẽ theo thành phố
                        $destination = strtolower($destination);
                        $isCorrectCity = true;
                        
                        // Kiểm tra địa điểm không thuộc thành phố khác
                        if (str_contains($destination, 'hà nội')) {
                            $isCorrectCity = !str_contains($name, 'suối tiên') && 
                                           !str_contains($name, 'bùi viện') && 
                                           !str_contains($name, 'bến thành') &&
                                           !str_contains($address, 'tp.hcm') &&
                                           !str_contains($address, 'hồ chí minh');
                        } elseif (str_contains($destination, 'hồ chí minh') || str_contains($destination, 'tp.hcm')) {
                            $isCorrectCity = !str_contains($name, 'hoàn kiếm') && 
                                           !str_contains($name, 'văn miếu') && 
                                           !str_contains($name, 'hà nội') &&
                                           !str_contains($address, 'hà nội');
                        }
                        
                        return $isCorrectCity && 
                               (str_contains($name, 'bảo tàng') || 
                               str_contains($name, 'chùa') || 
                               str_contains($name, 'di tích') ||
                               str_contains($name, 'nhà thờ') ||
                               str_contains($name, 'công viên') ||
                               str_contains($description, 'bảo tàng') ||
                               str_contains($description, 'chùa') ||
                               str_contains($description, 'di tích')) &&
                               !in_array($name, $usedPlaceNames);
                    });
                    
                    // Sử dụng smart selection thay vì random
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'morning');
                    $morningActivity = $this->smartPlaceService->selectSmartPlace($daytimePlaces, $context, $usedAttractionIds);
                    
                    if (!$morningActivity) {
                        $morningActivity = $availableAttractions->first();
                    }
                    $morningTimes = ['08:00', '08:30', '09:00'];
                    $dayActivities[] = [
                        'time' => $morningTimes[$dayIndex % 3],
                        'type' => 'attraction',
                        'name' => mb_convert_encoding($morningActivity->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($morningActivity->description ?? 'Tham quan buổi sáng', 'UTF-8', 'UTF-8'),
                        'cost' => $morningActivity->is_free ? 0 : ($morningActivity->price ?? round($attractionBudgetPerPerson / ($daysCount * 4))),
                        'duration' => '2 giờ',
                        'checkin_place_id' => $morningActivity->id,
                        'location' => mb_convert_encoding($morningActivity->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedAttractionIds[] = $morningActivity->id;
                    $usedPlaceNames[] = strtolower($morningActivity->name);
                }
            }
            
            // 3. Thêm hoạt động buổi sáng thứ 2 (10:30-12:00) - Tiếp tục tham quan ban ngày
            if ($attractions->count() > 1) {
                $availableAttractions = $attractions->whereNotIn('id', $usedAttractionIds);
                if ($availableAttractions->count() > 0) {
                    // Tránh trùng tên địa điểm
                    $uniquePlaces = $availableAttractions->filter(function($place) use ($usedPlaceNames) {
                        return !in_array(strtolower($place->name), $usedPlaceNames);
                    });
                    
                    // Sử dụng smart selection cho hoạt động sáng thứ 2
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'morning');
                    $morningActivity2 = $this->smartPlaceService->selectSmartPlace($uniquePlaces, $context, $usedAttractionIds);
                    
                    if (!$morningActivity2) {
                        $morningActivity2 = $availableAttractions->first();
                    }
                    $morning2Times = ['10:30', '11:00', '11:30'];
                    $dayActivities[] = [
                        'time' => $morning2Times[$dayIndex % 3],
                        'type' => 'attraction',
                        'name' => mb_convert_encoding($morningActivity2->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($morningActivity2->description ?? 'Tham quan buổi sáng', 'UTF-8', 'UTF-8'),
                        'cost' => $morningActivity2->is_free ? 0 : ($morningActivity2->price ?? round($attractionBudgetPerPerson / ($daysCount * 4))),
                        'duration' => '1.5 giờ',
                        'checkin_place_id' => $morningActivity2->id,
                        'location' => mb_convert_encoding($morningActivity2->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedAttractionIds[] = $morningActivity2->id;
                    $usedPlaceNames[] = strtolower($morningActivity2->name);
                }
            }
            
            // 4. Thêm ăn trưa (12:30-13:30)
            if ($restaurants->count() > 1) {
                $availableRestaurants = $restaurants->whereNotIn('id', $usedRestaurantIds);
                if ($availableRestaurants->count() > 0) {
                    // Sử dụng smart selection cho bữa trưa
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'lunch');
                    $lunch = $this->smartPlaceService->selectSmartPlace($availableRestaurants, $context, $usedRestaurantIds);
                    
                    if (!$lunch) {
                        $lunch = $availableRestaurants->first();
                    }
                    $lunchTimes = ['12:00', '12:30', '13:00'];
                    $dayActivities[] = [
                        'time' => $lunchTimes[$dayIndex % 3],
                        'type' => 'restaurant',
                        'name' => mb_convert_encoding($lunch->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($lunch->description ?? 'Ăn trưa', 'UTF-8', 'UTF-8'),
                        'cost' => round($foodBudgetPerPerson / ($daysCount * 3)),
                        'duration' => '1 giờ',
                        'restaurant_id' => $lunch->id,
                        'location' => mb_convert_encoding($lunch->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedRestaurantIds[] = $lunch->id;
                    $usedPlaceNames[] = strtolower($lunch->name);
                }
            }
            

            
            // 5. Thêm hoạt động buổi chiều (14:00-16:00) - Công viên, chợ, hoạt động ngoài trời
            if ($attractions->count() > 2) {
                $availableAttractions = $attractions->whereNotIn('id', $usedAttractionIds);
                if ($availableAttractions->count() > 0) {
                    // Ưu tiên địa điểm chiều và tránh trùng tên, lọc theo thành phố
                    $afternoonPlaces = $availableAttractions->filter(function($place) use ($usedPlaceNames, $destination) {
                        $name = strtolower($place->name);
                        $description = strtolower($place->description ?? '');
                        $address = strtolower($place->address ?? '');
                        
                        // Lọc chặt chẽ theo thành phố
                        $destination = strtolower($destination);
                        $isCorrectCity = true;
                        
                        // Kiểm tra địa điểm không thuộc thành phố khác
                        if (str_contains($destination, 'hà nội')) {
                            $isCorrectCity = !str_contains($name, 'suối tiên') && 
                                           !str_contains($name, 'bùi viện') && 
                                           !str_contains($name, 'bến thành') &&
                                           !str_contains($address, 'tp.hcm') &&
                                           !str_contains($address, 'hồ chí minh');
                        } elseif (str_contains($destination, 'hồ chí minh') || str_contains($destination, 'tp.hcm')) {
                            $isCorrectCity = !str_contains($name, 'hoàn kiếm') && 
                                           !str_contains($name, 'văn miếu') && 
                                           !str_contains($name, 'hà nội') &&
                                           !str_contains($address, 'hà nội');
                        }
                        
                        return $isCorrectCity && 
                               (str_contains($name, 'công viên') || 
                               str_contains($name, 'chợ') || 
                               str_contains($name, 'biển') ||
                               str_contains($name, 'vườn') ||
                               str_contains($description, 'công viên') ||
                               str_contains($description, 'chợ') ||
                               str_contains($description, 'biển')) &&
                               !in_array($name, $usedPlaceNames);
                    });
                    
                    // Sử dụng smart selection cho hoạt động chiều
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'afternoon');
                    $afternoonActivity = $this->smartPlaceService->selectSmartPlace($afternoonPlaces, $context, $usedAttractionIds);
                    
                    if (!$afternoonActivity) {
                        $afternoonActivity = $availableAttractions->first();
                    }
                    $afternoonTimes = ['14:00', '14:30', '15:00'];
                    $dayActivities[] = [
                        'time' => $afternoonTimes[$dayIndex % 3],
                        'type' => 'attraction',
                        'name' => mb_convert_encoding($afternoonActivity->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($afternoonActivity->description ?? 'Tham quan buổi chiều', 'UTF-8', 'UTF-8'),
                        'cost' => $afternoonActivity->is_free ? 0 : ($afternoonActivity->price ?? round($attractionBudgetPerPerson / ($daysCount * 4))),
                        'duration' => '2 giờ',
                        'checkin_place_id' => $afternoonActivity->id,
                        'location' => mb_convert_encoding($afternoonActivity->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedAttractionIds[] = $afternoonActivity->id;
                    $usedPlaceNames[] = strtolower($afternoonActivity->name);
                }
            }
            
            // 6. Thêm hoạt động buổi chiều thứ 2 (16:30-18:00) - Tiếp tục hoạt động ngoài trời
            if ($attractions->count() > 3) {
                $availableAttractions = $attractions->whereNotIn('id', $usedAttractionIds);
                if ($availableAttractions->count() > 0) {
                    // Tránh trùng tên địa điểm
                    $uniquePlaces = $availableAttractions->filter(function($place) use ($usedPlaceNames) {
                        return !in_array(strtolower($place->name), $usedPlaceNames);
                    });
                    
                    // Sử dụng smart selection cho hoạt động chiều thứ 2
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'afternoon');
                    $afternoonActivity2 = $this->smartPlaceService->selectSmartPlace($uniquePlaces, $context, $usedAttractionIds);
                    
                    if (!$afternoonActivity2) {
                        $afternoonActivity2 = $availableAttractions->first();
                    }
                    $afternoon2Times = ['16:30', '17:00', '17:30'];
                    $dayActivities[] = [
                        'time' => $afternoon2Times[$dayIndex % 3],
                        'type' => 'attraction',
                        'name' => mb_convert_encoding($afternoonActivity2->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($afternoonActivity2->description ?? 'Tham quan buổi chiều', 'UTF-8', 'UTF-8'),
                        'cost' => $afternoonActivity2->is_free ? 0 : ($afternoonActivity2->price ?? round($attractionBudgetPerPerson / ($daysCount * 4))),
                        'duration' => '1.5 giờ',
                        'checkin_place_id' => $afternoonActivity2->id,
                        'location' => mb_convert_encoding($afternoonActivity2->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedAttractionIds[] = $afternoonActivity2->id;
                    $usedPlaceNames[] = strtolower($afternoonActivity2->name);
                }
            }
            
            // 7. Thêm ăn tối (19:00-20:00)
            if ($restaurants->count() > 2) {
                $availableRestaurants = $restaurants->whereNotIn('id', $usedRestaurantIds);
                if ($availableRestaurants->count() > 0) {
                    // Sử dụng smart selection cho bữa tối
                    $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'dinner');
                    $dinner = $this->smartPlaceService->selectSmartPlace($availableRestaurants, $context, $usedRestaurantIds);
                    
                    if (!$dinner) {
                        $dinner = $availableRestaurants->first();
                    }
                    $dinnerTimes = ['19:00', '19:30', '20:00'];
                    $dayActivities[] = [
                        'time' => $dinnerTimes[$dayIndex % 3],
                        'type' => 'restaurant',
                        'name' => mb_convert_encoding($dinner->name, 'UTF-8', 'UTF-8'),
                        'description' => mb_convert_encoding($dinner->description ?? 'Ăn tối', 'UTF-8', 'UTF-8'),
                        'cost' => round($foodBudgetPerPerson / ($daysCount * 3)),
                        'duration' => '1 giờ',
                        'restaurant_id' => $dinner->id,
                        'location' => mb_convert_encoding($dinner->address ?? '', 'UTF-8', 'UTF-8')
                    ];
                    $usedRestaurantIds[] = $dinner->id;
                    $usedPlaceNames[] = strtolower($dinner->name);
                }
            }
            
            // Thêm hoạt động buổi tối (20:30-22:30) - Phố đi bộ, chợ đêm, cafe rooftop
            $eveningTimes = ['20:30', '21:00', '21:30'];
            
            // Tìm địa điểm buổi tối phù hợp (phố đi bộ, chợ đêm, cafe) - Lọc theo thành phố
            $eveningPlaces = $attractions->filter(function($place) use ($usedPlaceNames, $destination) {
                $name = strtolower($place->name);
                $description = strtolower($place->description ?? '');
                $address = strtolower($place->address ?? '');
                
                // Lọc chặt chẽ theo thành phố
                $destination = strtolower($destination);
                $isCorrectCity = true;
                
                // Kiểm tra địa điểm không thuộc thành phố khác
                if (str_contains($destination, 'hà nội')) {
                    $isCorrectCity = !str_contains($name, 'suối tiên') && 
                                   !str_contains($name, 'bùi viện') && 
                                   !str_contains($name, 'bến thành') &&
                                   !str_contains($address, 'tp.hcm') &&
                                   !str_contains($address, 'hồ chí minh');
                } elseif (str_contains($destination, 'hồ chí minh') || str_contains($destination, 'tp.hcm')) {
                    $isCorrectCity = !str_contains($name, 'hoàn kiếm') && 
                                   !str_contains($name, 'văn miếu') && 
                                   !str_contains($name, 'hà nội') &&
                                   !str_contains($address, 'hà nội');
                }
                
                return $isCorrectCity && 
                       (str_contains($name, 'phố đi bộ') || 
                       str_contains($name, 'chợ đêm') || 
                       str_contains($name, 'cafe') ||
                       str_contains($name, 'rooftop') ||
                       str_contains($description, 'phố đi bộ') ||
                       str_contains($description, 'chợ đêm') ||
                       str_contains($description, 'cafe')) &&
                       !in_array($name, $usedPlaceNames);
            });
            
            if ($eveningPlaces->count() > 0) {
                // Sử dụng smart selection cho hoạt động buổi tối
                $context = $this->smartPlaceService->createContext($destination, $budget, $travelers, 'evening');
                $eveningPlace = $this->smartPlaceService->selectSmartPlace($eveningPlaces, $context, $usedAttractionIds);
                
                if (!$eveningPlace) {
                    $eveningPlace = $eveningPlaces->first();
                }
                $dayActivities[] = [
                    'time' => $eveningTimes[$dayIndex % 3],
                    'type' => 'attraction',
                    'name' => mb_convert_encoding($eveningPlace->name, 'UTF-8', 'UTF-8'),
                    'description' => mb_convert_encoding($eveningPlace->description ?? 'Hoạt động buổi tối', 'UTF-8', 'UTF-8'),
                    'cost' => $eveningPlace->is_free ? 0 : ($eveningPlace->price ?? round($otherBudgetPerPerson / $daysCount)),
                    'duration' => '2 giờ',
                    'checkin_place_id' => $eveningPlace->id,
                    'location' => mb_convert_encoding($eveningPlace->address ?? '', 'UTF-8', 'UTF-8')
                ];
            } else {
                // Fallback nếu không tìm thấy địa điểm buổi tối phù hợp - Tùy theo thành phố
                $fallbackActivity = $this->getFallbackEveningActivity($destination, $dayIndex);
                $dayActivities[] = [
                    'time' => $eveningTimes[$dayIndex % 3],
                    'type' => 'activity',
                    'name' => $fallbackActivity['name'],
                    'description' => $fallbackActivity['description'],
                    'cost' => round($otherBudgetPerPerson / $daysCount),
                    'duration' => '2 giờ',
                    'location' => $fallbackActivity['location']
                ];
                $usedPlaceNames[] = strtolower($fallbackActivity['name']);
            }
            
            $itinerary['days'][] = [
                'day' => $dayIndex + 1,
                'date' => $startDate ? Carbon::parse($startDate)->addDays($dayIndex)->format('Y-m-d') : Carbon::now()->addDays($dayIndex)->format('Y-m-d'),
                'activities' => $dayActivities
            ];
        }

        return $itinerary;
    }

    private function saveItinerary($validated, $itinerary)
    {
        // Lưu lịch trình vào database
        $userId = Auth::id();
        
        // Tính toán end_date thực tế dựa trên số ngày AI trả về
        $actualDaysCount = isset($itinerary['days']) ? count($itinerary['days']) : 1;
        
        // Đảm bảo không vượt quá số ngày được yêu cầu
        $requestedDaysCount = Carbon::parse($validated['start_date'])->diffInDays($validated['end_date']) + 1;
        $actualDaysCount = min($actualDaysCount, $requestedDaysCount);
        
        $actualEndDate = Carbon::parse($validated['start_date'])->addDays($actualDaysCount - 1)->format('Y-m-d');
        
        // Tạo bản ghi lịch trình chính (Event chính)
        $schedule = \App\Models\Schedule::create([
            'user_id' => $userId,
            'name' => 'Du lịch ' . $validated['destination'],
            'description' => 'Lịch trình được tạo bởi AI dựa trên dữ liệu thực tế',
            'start_date' => $validated['start_date'],
            'end_date' => $actualEndDate, // Sử dụng end_date thực tế
            'budget' => $validated['budget'],
            'travelers' => $validated['travelers'],
            'itinerary_data' => json_encode($itinerary),
            'checkin_place_id' => null, // AI itineraries don't need specific checkin place
            'participants' => $validated['travelers'], // Use travelers as participants
            'status' => 'planning',
            'progress' => 0
        ]);

        // Validate itinerary structure
        
        // Kiểm tra nếu itinerary là null hoặc không phải array
        if (is_null($itinerary) || !is_array($itinerary)) {
            Log::error('Invalid itinerary data:', ['itinerary' => $itinerary]);
            throw new \Exception('Invalid itinerary data received from AI');
        }
        
        // Tạo các event con từ dữ liệu AI
        if (isset($itinerary['days']) && is_array($itinerary['days'])) {
            foreach ($itinerary['days'] as $dayIndex => $day) {
                // Chỉ tạo event cho những ngày trong phạm vi hợp lệ
                if ($dayIndex >= $actualDaysCount) {
                    break;
                }
                $currentDate = Carbon::parse($validated['start_date'])->addDays($dayIndex);
                
                if (isset($day['activities']) && is_array($day['activities'])) {
                    foreach ($day['activities'] as $activityIndex => $activity) {
                        // Parse thời gian
                        $startTime = null;
                        $endTime = null;
                        $duration = null;
                        
                        if (isset($activity['time'])) {
                            $startTime = Carbon::parse($activity['time']);
                        }
                        
                        if (isset($activity['duration'])) {
                            // Parse duration từ string (ví dụ: "2 giờ", "30 phút")
                            $durationStr = $activity['duration'];
                            if (preg_match('/(\d+)\s*giờ/', $durationStr, $matches)) {
                                $duration = (int)$matches[1] * 60; // Chuyển thành phút
                            } elseif (preg_match('/(\d+)\s*phút/', $durationStr, $matches)) {
                                $duration = (int)$matches[1];
                            }
                            
                            // Tính end time
                            if ($startTime && $duration) {
                                $endTime = $startTime->copy()->addMinutes($duration);
                            }
                        }
                        
                        // Xác định loại event
                        $type = $this->determineEventType($activity['type'] ?? 'activity');
                        
                        // Tìm foreign key dựa trên tên và loại
                        $checkinPlaceId = null;
                        $hotelId = null;
                        $restaurantId = null;
                        
                        if ($type === 'activity' || $type === 'attraction') {
                            // Tìm trong checkin_places với logic tìm kiếm cải thiện
                            $searchName = $activity['name'];
                            $checkinPlace = \App\Models\CheckinPlace::where(function($query) use ($searchName) {
                                $query->where('name', 'like', '%' . $searchName . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Nhà Thờ ', 'Bảo tàng ', 'Chợ ', 'Phố đi bộ '], '', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Đức Bà Sài Gòn', 'Đức Bà'], 'Đức Bà', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Chứng tích Chiến tranh'], 'Chứng tích Chiến tranh', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Bến Nhà Rồng'], 'Nhà Rồng', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Dinh Độc Lập'], 'Độc Lập', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Landmark 81'], 'Landmark', $searchName) . '%');
                            })->first();
                            if ($checkinPlace) {
                                $checkinPlaceId = $checkinPlace->id;
                            }
                        } elseif ($type === 'hotel') {
                            // Tìm trong hotels với logic tìm kiếm cải thiện
                            $searchName = $activity['name'];
                            $hotel = \App\Models\Hotel::where(function($query) use ($searchName) {
                                $query->where('name', 'like', '%' . $searchName . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Khách sạn '], '', $searchName) . '%');
                            })->first();
                            if ($hotel) {
                                $hotelId = $hotel->id;
                            }
                        } elseif ($type === 'restaurant') {
                            // Tìm trong restaurants với logic tìm kiếm cải thiện
                            $searchName = $activity['name'];
                            $restaurant = \App\Models\Restaurant::where(function($query) use ($searchName) {
                                $query->where('name', 'like', '%' . $searchName . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Nhà hàng '], '', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['Quán Ăn Ngon'], 'Ngon', $searchName) . '%')
                                      ->orWhere('name', 'like', '%' . str_replace(['The Deck Saigon'], 'The Deck', $searchName) . '%');
                            })->first();
                            if ($restaurant) {
                                $restaurantId = $restaurant->id;
                            }
                        }
                        
                        // Tạo event con
                        \App\Models\ItineraryEvent::create([
                            'schedule_id' => $schedule->id,
                            'checkin_place_id' => $checkinPlaceId,
                            'hotel_id' => $hotelId,
                            'restaurant_id' => $restaurantId,
                            'title' => $activity['name'] ?? 'Hoạt động ' . ($activityIndex + 1),
                            'description' => $activity['description'] ?? '',
                            'type' => $type,
                            'date' => $currentDate->format('Y-m-d'),
                            'start_time' => $startTime ? $startTime->format('H:i:s') : null,
                            'end_time' => $endTime ? $endTime->format('H:i:s') : null,
                            'duration' => $duration,
                            'cost' => $activity['cost'] ?? 0,
                            'location' => $activity['location'] ?? null,
                            'metadata' => [
                                'original_type' => $activity['type'] ?? 'activity',
                                'day' => $dayIndex + 1,
                                'matched_place_id' => $checkinPlaceId,
                                'matched_hotel_id' => $hotelId,
                                'matched_restaurant_id' => $restaurantId
                            ],
                            'order_index' => $activityIndex
                        ]);
                    }
                }
            }
        }

        return $schedule;
    }

    /**
     * Clean JSON content để tránh lỗi encoding
     */
    private function cleanJsonContent($content)
    {
        // Đảm bảo content là UTF-8
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'AUTO');
        }
        
        // Loại bỏ BOM nếu có
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        
        // Loại bỏ control characters
        $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
        
        // Sửa các ký tự tiếng Việt bị lỗi phổ biến
        $vietnameseFixes = [
            // Các ký tự Đ bị lỗi
            '?à' => 'Đà', '?á' => 'Đá', '?ả' => 'Đả', '?ã' => 'Đã', '?ạ' => 'Đạ',
            '?è' => 'Đè', '?é' => 'Đé', '?ẻ' => 'Đẻ', '?ẽ' => 'Đẽ', '?ẹ' => 'Đẹ',
            '?ì' => 'Đì', '?í' => 'Đí', '?ỉ' => 'Đỉ', '?ĩ' => 'Đĩ', '?ị' => 'Đị',
            '?ò' => 'Đò', '?ó' => 'Đó', '?ỏ' => 'Đỏ', '?õ' => 'Đõ', '?ọ' => 'Đọ',
            '?ù' => 'Đù', '?ú' => 'Đú', '?ủ' => 'Đủ', '?ũ' => 'Đũ', '?ụ' => 'Đụ',
            '?ỳ' => 'Đỳ', '?ý' => 'Đý', '?ỷ' => 'Đỷ', '?ỹ' => 'Đỹ', '?ỵ' => 'Đỵ',
            '?ầ' => 'Đầ', '?ấ' => 'Đấ', '?ẩ' => 'Đẩ', '?ẫ' => 'Đẫ', '?ậ' => 'Đậ',
            '?ề' => 'Đề', '?ế' => 'Đế', '?ể' => 'Để', '?ễ' => 'Đễ', '?ệ' => 'Đệ',
            '?ồ' => 'Đồ', '?ố' => 'Đố', '?ổ' => 'Đổ', '?ỗ' => 'Đỗ', '?ộ' => 'Độ',
            '?ờ' => 'Đờ', '?ớ' => 'Đớ', '?ở' => 'Đở', '?ỡ' => 'Đỡ', '?ợ' => 'Đợ',
            
            // Các từ cụ thể bị lỗi
            'V?i' => 'Với', 'tri?u' => 'triệu', '?Đng' => 'Đồng', 'mĐt' => 'một',
            'lĐch' => 'lịch', 'thú v?' => 'thú vị', 'thành ph?' => 'thành phố',
            'H?i An' => 'Hội An', '?ây' => 'Đây', '?iĐm' => 'Điểm', 'vĐn' => 'văn',
            'n?i' => 'nổi', 'ViĐt Nam' => 'Việt Nam', '??a' => 'Địa',
            'cơ h?i' => 'cơ hội', 'c? kính' => 'cổ kính', '?Đn' => 'Đền',
            'thiên ?ưĐng' => 'thiên đường', '?ặc sản' => 'đặc sản', 'nhi?u' => 'nhiều',
            'Th?i gian' => 'Thời gian', 'Th?i tiết' => 'Thời tiết', '?ông' => 'đông',
            '?ưĐng' => 'đường', '?ặc biệt' => 'đặc biệt', '?ẹp' => 'đẹp',
            '?ất' => 'đất', '?ể' => 'để', '?ang' => 'đang', '?ó' => 'đó',
            '?ã' => 'đã', '?ủ' => 'đủ', '?ến' => 'đến', 'Hà NĐi' => 'Hà Nội',
            'nưĐc' => 'nước', 'ThĐm' => 'Thăm', 'QuĐc' => 'Quốc', 'H? Gươm' => 'Hồ Gươm'
        ];
        
        foreach ($vietnameseFixes as $wrong => $correct) {
            $content = str_replace($wrong, $correct, $content);
        }
        
        // Cải thiện format text - xuống hàng đẹp mắt
        $content = $this->formatTextForDisplay($content);
        
        return $content;
    }

    /**
     * Format text để hiển thị đẹp mắt với xuống hàng hợp lý
     */
    private function formatTextForDisplay($text)
    {
        // Chuẩn hóa line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Tách thành các đoạn
        $paragraphs = explode("\n", $text);
        $formattedParagraphs = [];
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }
            
            // Format các danh sách số
            if (preg_match('/^\d+\./', $paragraph)) {
                // Giữ nguyên format danh sách số
                $formattedParagraphs[] = $paragraph;
            }
            // Format các danh sách dấu gạch
            elseif (preg_match('/^[-•*]\s/', $paragraph)) {
                // Giữ nguyên format danh sách dấu gạch
                $formattedParagraphs[] = $paragraph;
            }
            // Format tiêu đề (chữ in hoa hoặc có dấu :)
            elseif (preg_match('/^[A-ZÀÁẢÃẠĂẰẮẲẴẶÂẦẤẨẪẬÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰỲÝỶỸỴĐ][^:]*:$/', $paragraph)) {
                // Tiêu đề - thêm khoảng trắng trước
                if (!empty($formattedParagraphs)) {
                    $formattedParagraphs[] = '';
                }
                $formattedParagraphs[] = $paragraph;
            }
            // Format các câu dài (trên 100 ký tự)
            elseif (strlen($paragraph) > 100) {
                // Tách câu dài thành các câu ngắn hơn
                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                foreach ($sentences as $sentence) {
                    $sentence = trim($sentence);
                    if (!empty($sentence)) {
                        $formattedParagraphs[] = $sentence;
                    }
                }
            }
            else {
                // Đoạn văn bình thường
                $formattedParagraphs[] = $paragraph;
            }
        }
        
        // Kết hợp lại với line breaks đẹp
        $formattedText = implode("\n", $formattedParagraphs);
        
        // Thêm khoảng trắng giữa các đoạn chính
        $formattedText = preg_replace('/\n{3,}/', "\n\n", $formattedText);
        
        // Đảm bảo không có khoảng trắng thừa
        $formattedText = preg_replace('/[ \t]+/', ' ', $formattedText);
        $formattedText = trim($formattedText);
        
        return $formattedText;
    }

    /**
     * Xác định loại event từ dữ liệu AI
     */
    private function determineEventType($originalType)
    {
        return match(strtolower($originalType)) {
            'hotel', 'accommodation' => 'hotel',
            'restaurant', 'food', 'dining' => 'restaurant',
            'transport', 'transportation', 'travel' => 'activity', // Chuyển transport thành activity
            'shopping', 'market' => 'activity', // Chuyển shopping thành activity
            'culture', 'museum', 'temple', 'historical' => 'activity', // Chuyển culture thành activity
            'nature', 'park', 'garden' => 'activity', // Chuyển nature thành activity
            'entertainment', 'show', 'performance' => 'activity', // Chuyển entertainment thành activity
            default => 'activity'
        };
    }

    public function getUpgradeInfo()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'vip_benefits' => [
                    'Lịch trình không giới hạn ngày',
                    'Gợi ý AI nâng cao',
                    'Ưu tiên hỗ trợ 24/7',
                    'Truy cập các tính năng premium',
                    'Giảm giá đặc biệt cho dịch vụ du lịch'
                ],
                'pricing' => [
                    'monthly' => 199000,
                    'yearly' => 1990000
                ],
                'contact' => 'support@ipsumtravel.com'
            ]
        ]);
    }

    /**
     * Lấy chi tiết lịch trình với các event con
     */
    public function getItineraryDetail($scheduleId)
    {
        try {
            $schedule = \App\Models\Schedule::with(['itineraryEvents' => function($query) {
                $query->with(['checkinPlace', 'hotel', 'restaurant'])->ordered();
            }])->findOrFail($scheduleId);

            // Kiểm tra quyền truy cập
            if ($schedule->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập lịch trình này'
                ], 403);
            }

            // Nhóm events theo ngày
            $eventsByDate = [];
            foreach ($schedule->itineraryEvents as $event) {
                $date = $event->date->format('Y-m-d');
                if (!isset($eventsByDate[$date])) {
                    $eventsByDate[$date] = [];
                }
                $eventsByDate[$date][] = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'type' => $event->type,
                    'icon' => $event->icon,
                    'start_time' => $event->start_time,
                    'end_time' => $event->end_time,
                    'time_display' => $event->time_display,
                    'duration' => $event->duration,
                    'cost' => $event->cost,
                    'cost_display' => $event->cost_display,
                    'location' => $event->location,
                    'metadata' => $event->metadata,
                    'order_index' => $event->order_index,
                    // Thêm thông tin foreign key để biết dữ liệu lấy từ đâu
                    'checkin_place_id' => $event->checkin_place_id,
                    'hotel_id' => $event->hotel_id,
                    'restaurant_id' => $event->restaurant_id,
                    'checkin_place' => $event->checkinPlace ? [
                        'id' => $event->checkinPlace->id,
                        'name' => $event->checkinPlace->name,
                        'address' => $event->checkinPlace->address,
                        'description' => $event->checkinPlace->description
                    ] : null,
                    'hotel' => $event->hotel ? [
                        'id' => $event->hotel->id,
                        'name' => $event->hotel->name,
                        'address' => $event->hotel->address,
                        'description' => $event->hotel->description
                    ] : null,
                    'restaurant' => $event->restaurant ? [
                        'id' => $event->restaurant->id,
                        'name' => $event->restaurant->name,
                        'address' => $event->restaurant->address,
                        'description' => $event->restaurant->description,
                        'rating' => $event->restaurant->rating,
                        'price_range' => $event->restaurant->price_range
                    ] : null
                ];
            }

            // Sắp xếp theo ngày
            ksort($eventsByDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'schedule' => [
                        'id' => $schedule->id,
                        'name' => $schedule->name,
                        'description' => $schedule->description,
                        'start_date' => $schedule->start_date,
                        'end_date' => $schedule->end_date,
                        'duration' => $schedule->duration,
                        'budget' => $schedule->budget,
                        'travelers' => $schedule->travelers,
                        'total_cost' => $schedule->total_cost,
                        'status' => $schedule->status,
                        'progress' => $schedule->progress
                    ],
                    'events_by_date' => $eventsByDate,
                    'summary' => [
                        'total_events' => $schedule->itineraryEvents->count(),
                        'total_days' => count($eventsByDate),
                        'average_cost_per_day' => count($eventsByDate) > 0 ? round($schedule->total_cost / count($eventsByDate)) : 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get Itinerary Detail Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy chi tiết lịch trình'
            ], 500);
        }
    }

    /**
     * Cập nhật event con
     */
    public function updateItineraryEvent(Request $request, $eventId)
    {
        try {
            $event = \App\Models\ItineraryEvent::with('schedule')->findOrFail($eventId);
            
            // Kiểm tra quyền truy cập
            if ($event->schedule->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền chỉnh sửa event này'
                ], 403);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|nullable|string',
                'start_time' => 'sometimes|nullable|date_format:H:i',
                'end_time' => 'sometimes|nullable|date_format:H:i',
                'duration' => 'sometimes|nullable|integer|min:1',
                'cost' => 'sometimes|numeric|min:0',
                'location' => 'sometimes|nullable|string|max:255',
                'order_index' => 'sometimes|integer|min:0'
            ]);

            $event->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Event đã được cập nhật thành công',
                'data' => $event
            ]);

        } catch (\Exception $e) {
            Log::error('Update Itinerary Event Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật event'
            ], 500);
        }
    }

    /**
     * Xóa event con
     */
    public function deleteItineraryEvent($eventId)
    {
        try {
            $event = \App\Models\ItineraryEvent::with('schedule')->findOrFail($eventId);
            
            // Kiểm tra quyền truy cập
            if ($event->schedule->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền xóa event này'
                ], 403);
            }

            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event đã được xóa thành công'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete Itinerary Event Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa event'
            ], 500);
        }
    }

    /**
     * Lưu lịch trình từ AI vào database
     */
    public function saveItineraryFromAI(Request $request)
    {
        try {
            $itineraryData = $request->all();
            
            // Validate received data
            
            // Validate dữ liệu
            if (!isset($itineraryData['summary']) || !isset($itineraryData['days'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu lịch trình không hợp lệ'
                ], 400);
            }

            // Tạo schedule chính
            $schedule = \App\Models\Schedule::create([
                'user_id' => Auth::id(),
                'name' => 'Du lịch ' . ($itineraryData['summary']['destination'] ?? 'Việt Nam'),
                'start_date' => $itineraryData['summary']['start_date'] ?? now(),
                'end_date' => $itineraryData['summary']['end_date'] ?? now()->addDays(1),
                'budget' => $itineraryData['summary']['total_cost'] ?? 0,
                'participants' => 2, // Giá trị mặc định
                'travelers' => 2, // Giá trị mặc định
                'status' => 'planning', // Giá trị hợp lệ cho enum
                'progress' => 0
            ]);

            // Tạo các event con
            $totalEvents = 0;
            $startDate = \Carbon\Carbon::parse($schedule->start_date);
            
            foreach ($itineraryData['days'] as $dayIndex => $day) {
                if (isset($day['activities'])) {
                    // Tính date cho ngày hiện tại
                    $currentDate = $startDate->copy()->addDays($dayIndex);
                    
                    foreach ($day['activities'] as $activity) {
                        // Lấy ID từ dữ liệu AI hoặc tìm từ database
                        $hotelId = $activity['hotel_id'] ?? null;
                        $restaurantId = $activity['restaurant_id'] ?? null;
                        $checkinPlaceId = $activity['checkin_place_id'] ?? null;
                        
                        $activityType = $this->determineEventType($activity['type'] ?? 'attraction');
                        $activityName = $activity['name'] ?? '';
                        
                        // Nếu không có ID từ AI, tìm từ database theo destination
                        $destination = $itineraryData['summary']['destination'] ?? 'Việt Nam';
                        
                        // Tạo destination keywords
                        $destinationKeywords = [];
                        if (stripos($destination, 'hồ chí minh') !== false || stripos($destination, 'sài gòn') !== false) {
                            $destinationKeywords = ['Hồ Chí Minh', 'TP.HCM', 'TPHCM', 'Quận 1', 'Quận 3', 'Quận 5', 'Quận 7', 'Quận 10', 'Bình Thạnh', 'Tân Bình'];
                        } elseif (stripos($destination, 'đà nẵng') !== false) {
                            $destinationKeywords = ['Đà Nẵng', 'Hòa Vang', 'Sơn Trà', 'Ngũ Hành Sơn'];
                        } elseif (stripos($destination, 'hà nội') !== false) {
                            $destinationKeywords = ['Hà Nội', 'Ba Đình', 'Hoàn Kiếm', 'Đống Đa', 'Hai Bà Trưng'];
                        } else {
                            $destinationKeywords = [$destination];
                        }
                        
                        if (!$hotelId && $activityType === 'hotel') {
                            $hotel = \App\Models\Hotel::where('name', 'LIKE', '%' . $activityName . '%')
                                ->where(function($query) use ($destinationKeywords) {
                                    foreach ($destinationKeywords as $keyword) {
                                        $query->orWhere('address', 'LIKE', '%' . $keyword . '%');
                                    }
                                })
                                ->first();
                            if ($hotel) {
                                $hotelId = $hotel->id;
                            }
                        }
                        if (!$restaurantId && $activityType === 'restaurant') {
                            $restaurant = \App\Models\Restaurant::where('name', 'LIKE', '%' . $activityName . '%')
                                ->where(function($query) use ($destinationKeywords) {
                                    foreach ($destinationKeywords as $keyword) {
                                        $query->orWhere('address', 'LIKE', '%' . $keyword . '%');
                                    }
                                })
                                ->first();
                            if ($restaurant) {
                                $restaurantId = $restaurant->id;
                            }
                        }
                        if (!$checkinPlaceId && $activityType === 'activity') {
                            $checkinPlace = \App\Models\CheckinPlace::where('name', 'LIKE', '%' . $activityName . '%')
                                ->where(function($query) use ($destinationKeywords) {
                                    foreach ($destinationKeywords as $keyword) {
                                        $query->orWhere('address', 'LIKE', '%' . $keyword . '%');
                                    }
                                })
                                ->first();
                            if ($checkinPlace) {
                                $checkinPlaceId = $checkinPlace->id;
                            }
                        }
                        
                        \App\Models\ItineraryEvent::create([
                            'schedule_id' => $schedule->id,
                            'checkin_place_id' => $checkinPlaceId,
                            'hotel_id' => $hotelId,
                            'restaurant_id' => $restaurantId,
                            'title' => mb_convert_encoding($activity['name'] ?? 'Hoạt động', 'UTF-8', 'UTF-8'),
                            'description' => mb_convert_encoding($activity['description'] ?? '', 'UTF-8', 'UTF-8'),
                            'start_time' => $activity['time'] ?? '09:00',
                            'end_time' => $this->calculateEndTime($activity['time'] ?? '09:00', $activity['duration'] ?? '1 giờ'),
                            'duration' => $this->parseDuration($activity['duration'] ?? '1 giờ'),
                            'cost' => $activity['cost'] ?? 0,
                            'location' => mb_convert_encoding($activity['location'] ?? '', 'UTF-8', 'UTF-8'),
                            'type' => $activityType,
                            'order_index' => $totalEvents++,
                            'date' => $currentDate->format('Y-m-d')
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Lịch trình đã được lưu thành công',
                'data' => [
                    'schedule_id' => $schedule->id,
                    'total_events' => $totalEvents
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Save Itinerary Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lưu lịch trình'
            ], 500);
        }
    }

    /**
     * Tính thời gian kết thúc dựa trên thời gian bắt đầu và thời lượng
     */
    private function calculateEndTime($startTime, $duration)
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
        $hours = $this->parseDuration($duration);
        return $start->addHours($hours)->format('H:i');
    }

    /**
     * Parse thời lượng từ string sang số giờ
     */
    private function parseDuration($duration)
    {
        if (is_numeric($duration)) {
            return (int)$duration;
        }
        
        // Parse các format như "1 giờ", "2 giờ", "1.5 giờ"
        if (preg_match('/(\d+(?:\.\d+)?)\s*giờ/', $duration, $matches)) {
            return (float)$matches[1];
        }
        
        // Parse các format như "1h", "2h", "1.5h"
        if (preg_match('/(\d+(?:\.\d+)?)\s*h/', $duration, $matches)) {
            return (float)$matches[1];
        }
        
        return 1; // Default 1 giờ
    }

    /**
     * Test OpenAI API
     */
    public function testOpenAI(Request $request)
    {
        $apiKey = config('services.openai.api_key');
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI API key chưa được cấu hình',
                'api_key_exists' => false
            ]);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một chuyên gia du lịch Việt Nam, viết tiếng Việt mạch lạc, tự nhiên, không dịch kiểu máy. Sử dụng văn phong thân thiện, giống như người hướng dẫn viên du lịch Việt Nam.'
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Xin chào! Hãy trả lời ngắn gọn bằng tiếng Việt.'
                    ]
                ],
                'max_tokens' => 100,
                'temperature' => 0.7
            ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                return response()->json([
                    'success' => true,
                    'message' => 'OpenAI API hoạt động bình thường',
                    'response' => $content,
                    'api_key_exists' => true
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI API lỗi: ' . $response->body(),
                    'api_key_exists' => true,
                    'status_code' => $response->status()
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OpenAI API Exception: ' . $e->getMessage(),
                'api_key_exists' => true
            ]);
        }
    }

    /**
     * Chat với AI Travel Assistant
     */
    public function chat(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
                'conversation_history' => 'nullable|array',
                'context' => 'nullable|array',
                'conversation_id' => 'nullable|string'
            ]);

            $message = $validated['message'];
            $conversationHistory = $validated['conversation_history'] ?? [];
            $context = $validated['context'] ?? [];
            $conversationId = $validated['conversation_id'] ?? null;

            // Lưu tin nhắn của user vào database
            try {
                $this->conversationService->saveMessage($conversationId, 'user', $message);

                // Lấy conversation history từ database nếu có conversation_id
                if ($conversationId) {
                    $dbConversationHistory = $this->conversationService->getConversationHistoryForAI($conversationId, 10);
                    // Kết hợp với conversation history từ frontend
                    $conversationHistory = array_merge($dbConversationHistory, $conversationHistory);
                }
            } catch (\Exception $e) {
                Log::error('ConversationService Error: ' . $e->getMessage());
                // Tiếp tục xử lý mà không lưu conversation nếu có lỗi
            }

            // Phân tích message để hiểu ý định người dùng với context
            $intent = $this->analyzeUserIntentWithContext($message, $conversationHistory, $context);
            
                               // Xử lý theo intent
                   switch ($intent['type']) {
                       case 'ai_identity':
                           return $this->handleAiIdentityIntent($message, $conversationHistory, $context, $conversationId);
                       
                       case 'create_itinerary':
                           return $this->handleCreateItineraryIntent($message, $conversationHistory, $context, $conversationId);
                       
                       case 'location_question':
                           return $this->handleLocationQuestionIntent($message, $conversationHistory, $context, $conversationId);
                       
                       case 'general_travel_advice':
                           return $this->handleGeneralTravelAdviceIntent($message, $conversationHistory, $context, $conversationId);
                       
                       case 'modify_itinerary':
                           return $this->handleModifyIntent($message, $conversationHistory, $context, $conversationId);
                       
                       case 'rag_query':
                           return $this->handleRAGQuery($message, $conversationHistory, $context, $conversationId);
                       
                       case 'contextual_response':
                           return $this->handleContextualResponse($message, $conversationHistory, $context, $intent['context'], $conversationId);
                       
                       case 'non_travel':
                           return $this->handleNonTravelIntent($message, $conversationHistory, $context, $conversationId);
                       
                       default:
                           return $this->handleGeneralIntent($message, $conversationHistory, $context, $conversationId);
                   }

        } catch (\Exception $e) {
            Log::error('AI Chat Error: ' . $e->getMessage());
            Log::error('AI Chat Error Stack: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xử lý tin nhắn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Phân tích ý định người dùng với context
     */
    private function analyzeUserIntentWithContext($message, $conversationHistory, $context)
    {
        $message = strtolower($message);
        
        // Kiểm tra context từ conversation history (chỉ khi có từ khóa context)
        if (!empty($conversationHistory)) {
            $contextKeywords = ['đó', 'ấy', 'gợi ý', 'trước', 'địa điểm', 'này', 'đây'];
            $hasContextKeyword = collect($contextKeywords)->contains(function($keyword) use ($message) {
                return str_contains($message, $keyword);
            });
            
            if ($hasContextKeyword) {
                $contextIntent = $this->analyzeContextFromHistory($message, $conversationHistory, $context);
                if ($contextIntent['confidence'] > 0.7) {
                    return $contextIntent;
                }
            }
        }
        
        // Kiểm tra câu hỏi không liên quan đến du lịch
        $nonTravelKeywords = [
            'giải toán', 'phương trình', 'tính toán', 'toán học', 'số học',
            'khoa học', 'công nghệ', 'lập trình', 'code', 'programming',
            'chính trị', 'tin tức', 'thời sự', 'kinh tế', 'tài chính',
            'y tế', 'sức khỏe', 'bệnh', 'thuốc', 'bác sĩ',
            'giáo dục', 'học tập', 'thi cử', 'bài tập', 'sách vở'
        ];
        
        // Loại trừ thời tiết khỏi non-travel keywords
        if (str_contains($message, 'thời tiết') || str_contains($message, 'weather')) {
            $nonTravelKeywords = array_filter($nonTravelKeywords, function($keyword) {
                return !str_contains($keyword, 'thời tiết') && !str_contains($keyword, 'weather');
            });
        }
        
        $hasNonTravelIntent = collect($nonTravelKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });
        
        if ($hasNonTravelIntent) {
            return ['type' => 'non_travel', 'confidence' => 0.95];
        }
        
        // Kiểm tra câu hỏi về AI
        $aiIdentityKeywords = ['ai là ai', 'bạn là ai', 'tên gì', 'ai tạo ra', 'nhóm nào', 'fit tdc'];
        $hasAiIdentityIntent = collect($aiIdentityKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });

        if ($hasAiIdentityIntent) {
            return ['type' => 'ai_identity', 'confidence' => 0.95];
        }

        // Từ khóa tạo lịch trình (loại trừ gợi ý địa điểm)
        $itineraryKeywords = ['tạo', 'lập', 'lên kế hoạch', 'đi', 'du lịch', 'lịch trình'];
        $hasItineraryIntent = collect($itineraryKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });

        if ($hasItineraryIntent) {
            return ['type' => 'create_itinerary', 'confidence' => 0.9];
        }

        // Kiểm tra câu hỏi phức tạp cần RAG
        $ragKeywords = ['lịch trình', 'itinerary', 'kế hoạch', 'plan', 'tư vấn', 'advice', 'gợi ý', 'suggest', 'chi phí', 'cost', 'giá', 'price', 'ngân sách', 'budget', 'thời tiết', 'weather', 'thoi tiet'];
        $hasRAGIntent = collect($ragKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });

        // Từ khóa hỏi đáp về địa điểm - cải thiện logic
        $locationQuestionKeywords = ['ở đâu', 'bao nhiêu', 'khi nào', 'tại sao', 'như thế nào', 'có gì', 'đẹp', 'ngon', 'được ko', 'được không', 'thì sao', 'như thế nào'];
        $hasLocationQuestionIntent = collect($locationQuestionKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });

        // Kiểm tra xem có tên địa điểm trong message không
        $destinations = [
            'TP.HCM', 'Hồ Chí Minh', 'Ho Chi Minh', 'Sài Gòn', 'Saigon', 'Hà Nội', 'Hanoi', 'Đà Nẵng', 'Da Nang', 'Huế', 'Hue', 'Hội An', 'Hoi An',
            'Nha Trang', 'Phú Quốc', 'Phu Quoc', 'Đà Lạt', 'Da Lat', 'Sa Pa', 'Sapa', 'Hạ Long', 'Ha Long', 'Cần Thơ', 'Can Tho',
            'Núi Bà', 'Nui Ba', 'Núi Bà Đen', 'Nui Ba Den', 'Núi Bà Rá', 'Nui Ba Ra', 'Núi Bà Đen Tây Ninh', 'Tây Ninh', 'Tay Ninh',
            'Vũng Tàu', 'Vung Tau', 'Bà Rịa', 'Ba Ria', 'Bà Rịa Vũng Tàu', 'Ba Ria Vung Tau', 'Mũi Né', 'Mui Ne', 'Phan Thiết', 'Phan Thiet'
        ];
        
        $hasDestination = collect($destinations)->contains(function($dest) use ($message) {
            return str_contains(strtolower($message), strtolower($dest));
        });

        // Ưu tiên RAG cho câu hỏi phức tạp hoặc câu hỏi về thời tiết
        if ($hasRAGIntent || (str_contains($message, 'thời tiết') && $hasDestination)) {
            Log::info('RAG Intent detected: ' . $message);
            return ['type' => 'rag_query', 'confidence' => 0.95];
        }
        


        if ($hasLocationQuestionIntent || $hasDestination) {
            return ['type' => 'location_question', 'confidence' => 0.9];
        }

        // Từ khóa hỏi về ngân sách và gợi ý chung
        $budgetKeywords = ['triệu', 'nghìn', 'đồng', 'vnd', 'tiền', 'chi phí', 'giá', 'rẻ', 'đắt'];
        $generalTravelKeywords = ['du lịch', 'đi đâu', 'nơi nào', 'địa điểm', 'điểm đến', 'khám phá', 'thăm quan'];
        
        $hasBudgetKeyword = collect($budgetKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });
        
        $hasGeneralTravelKeyword = collect($generalTravelKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });
        
        // Nếu có ngân sách và từ khóa du lịch chung
        if ($hasBudgetKeyword && $hasGeneralTravelKeyword) {
            return ['type' => 'general_travel_advice', 'confidence' => 0.85];
        }

        // Từ khóa chỉnh sửa
        $modifyKeywords = ['thay đổi', 'sửa', 'chỉnh', 'điều chỉnh', 'thêm', 'bớt'];
        $hasModifyIntent = collect($modifyKeywords)->contains(function($keyword) use ($message) {
            return str_contains($message, $keyword);
        });

        if ($hasModifyIntent) {
            return ['type' => 'modify_itinerary', 'confidence' => 0.7];
        }

        // Phân tích context từ conversation history
        $contextIntent = $this->analyzeContextFromHistory($message, $conversationHistory, $context);
        
        // Nếu có context rõ ràng, ưu tiên context
        if ($contextIntent['confidence'] > 0.5) {
            return $contextIntent;
        }
        
        return ['type' => 'general', 'confidence' => 0.5];
    }

    /**
     * Phân tích context từ conversation history
     */
    private function analyzeContextFromHistory($message, $conversationHistory, $context)
    {
        $message = strtolower($message);
        
        // Nếu message quá ngắn hoặc không có context, trả về general
        if (strlen($message) < 3 || empty($conversationHistory)) {
            return ['type' => 'general', 'confidence' => 0.3];
        }
        
        // Tìm context từ conversation history
        $lastMessages = array_slice($conversationHistory, -3); // Lấy 3 tin nhắn cuối
        $contextInfo = $this->extractContextFromMessages($lastMessages);
        
        // Kiểm tra các từ khóa context
        $contextKeywords = [
            'đó' => 0.8,
            'ấy' => 0.8,
            'kia' => 0.8,
            'này' => 0.8,
            'đây' => 0.8,
            'thế' => 0.7,
            'vậy' => 0.7,
            'như vậy' => 0.9,
            'như thế' => 0.9,
            'gợi ý' => 0.8, // Tăng score cho "gợi ý"
            'sao' => 0.6,
            'thì sao' => 0.8,
            'còn' => 0.7,
            'nữa' => 0.7,
            'khác' => 0.6,
            'thêm' => 0.7,
            'nữa không' => 0.8,
            'được không' => 0.7,
            'có không' => 0.7,
            'trước' => 0.8, // Thêm từ khóa "trước"
            'trước đi' => 0.9 // Thêm từ khóa "trước đi"
        ];
        
        $contextScore = 0;
        foreach ($contextKeywords as $keyword => $score) {
            if (str_contains($message, $keyword)) {
                $contextScore += $score;
            }
        }
        
        // ƯU TIÊN CAO NHẤT: Nếu có destination trong context và message có từ khóa gợi ý
        if ($contextInfo['destination'] && (str_contains($message, 'gợi ý') || str_contains($message, 'trước') || str_contains($message, 'địa điểm'))) {
            return [
                'type' => 'contextual_response',
                'confidence' => 0.98, // Tăng confidence lên cao nhất
                'context' => $contextInfo
            ];
        }
        
        // ƯU TIÊN THỨ 2: Nếu có destination trong context, ưu tiên contextual response
        if ($contextInfo['destination'] && !empty($contextInfo['destination'])) {
            return [
                'type' => 'contextual_response',
                'confidence' => 0.9,
                'context' => $contextInfo
            ];
        }
        
        // Nếu có context score cao và có thông tin context
        if ($contextScore > 0.5 && !empty($contextInfo)) {
            return [
                'type' => 'contextual_response',
                'confidence' => min($contextScore, 0.95),
                'context' => $contextInfo
            ];
        }
        
        // Kiểm tra nếu message liên quan đến thông tin đã thảo luận
        if ($this->isRelatedToPreviousContext($message, $contextInfo)) {
            return [
                'type' => 'contextual_response',
                'confidence' => 0.8,
                'context' => $contextInfo
            ];
        }
        
        return ['type' => 'general', 'confidence' => 0.3];
    }
    
    /**
     * Trích xuất context từ các tin nhắn trước
     */
    private function extractContextFromMessages($messages)
    {
        $context = [
            'destination' => null,
            'budget' => null,
            'duration' => null,
            'preferences' => [],
            'last_topic' => null,
            'conversation_flow' => []
        ];
        
        foreach ($messages as $msg) {
            $content = strtolower($msg['content'] ?? '');
            $role = $msg['type'] ?? 'user';
            
            // Lưu flow cuộc hội thoại
            $context['conversation_flow'][] = [
                'role' => $role,
                'content' => $content,
                'timestamp' => $msg['timestamp'] ?? null
            ];
            
            // Trích xuất địa điểm với priority cao hơn
            $destinations = [
                'TP.HCM', 'Hồ Chí Minh', 'Sài Gòn', 'Hà Nội', 'Đà Nẵng', 'Huế', 'Hội An',
                'Nha Trang', 'Phú Quốc', 'Đà Lạt', 'Sa Pa', 'Hạ Long', 'Cần Thơ',
                'Vũng Tàu', 'Bà Rịa', 'Mũi Né', 'Phan Thiết'
            ];
            
            foreach ($destinations as $dest) {
                if (str_contains($content, strtolower($dest))) {
                    $context['destination'] = $dest;
                    break;
                }
            }
            
            // Trích xuất ngân sách
            if (preg_match('/(\d+)\s*(triệu|nghìn|đồng|vnd)/', $content, $matches)) {
                $context['budget'] = $matches[1] . ' ' . $matches[2];
            }
            
            // Trích xuất thời gian
            if (preg_match('/(\d+)\s*(ngày|đêm)/', $content, $matches)) {
                $context['duration'] = $matches[1] . ' ' . $matches[2];
            }
            
            // Trích xuất sở thích
            $preferences = ['biển', 'núi', 'ẩm thực', 'văn hóa', 'shopping', 'khám phá', 'nghỉ dưỡng'];
            foreach ($preferences as $pref) {
                if (str_contains($content, $pref)) {
                    $context['preferences'][] = $pref;
                }
            }
            
            // Lưu chủ đề cuối
            if (str_contains($content, 'lịch trình')) {
                $context['last_topic'] = 'itinerary';
            } elseif (str_contains($content, 'địa điểm')) {
                $context['last_topic'] = 'location';
            } elseif (str_contains($content, 'khách sạn')) {
                $context['last_topic'] = 'hotel';
            } elseif (str_contains($content, 'nhà hàng')) {
                $context['last_topic'] = 'restaurant';
            }
        }
        
        return $context;
    }
    
    /**
     * Kiểm tra xem message có liên quan đến context trước không
     */
    private function isRelatedToPreviousContext($message, $contextInfo)
    {
        if (empty($contextInfo)) {
            return false;
        }
        
        $message = strtolower($message);
        
        // Kiểm tra các từ khóa liên quan
        $relatedKeywords = [
            'destination' => ['đó', 'ấy', 'kia', 'này', 'đây', 'thế', 'vậy'],
            'budget' => ['tiền', 'chi phí', 'giá', 'rẻ', 'đắt', 'triệu', 'nghìn'],
            'duration' => ['ngày', 'đêm', 'thời gian', 'bao lâu'],
            'preferences' => ['thích', 'muốn', 'sở thích', 'ưa']
        ];
        
        foreach ($relatedKeywords as $contextType => $keywords) {
            if (!empty($contextInfo[$contextType])) {
                foreach ($keywords as $keyword) {
                    if (str_contains($message, $keyword)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Xử lý response có context
     */
    private function handleContextualResponse($message, $conversationHistory, $context, $contextInfo, $conversationId = null)
    {
        $contextPrompt = $this->buildContextPrompt($message, $contextInfo, $conversationHistory);
        
        try {
            $response = $this->callOpenAI($contextPrompt, null, null, true);
            $answer = $response['answer'] ?? 'Tôi hiểu bạn đang hỏi về thông tin trước đó. Hãy để tôi giúp bạn!';
            
            // Fix encoding
            $answer = mb_convert_encoding($answer, 'UTF-8', 'UTF-8');
            $answer = $this->cleanJsonContent($answer);
            
            // Lưu tin nhắn của AI vào database
            try {
                $this->conversationService->saveMessage($conversationId, 'ai', $answer);
            } catch (\Exception $e) {
                Log::error('ConversationService Error (AI): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'response' => $answer,
                'conversation_id' => $conversationId,
                'suggestions' => [
                    'Tạo lịch trình chi tiết',
                    'Hỏi thêm thông tin',
                    'Xem địa điểm khác'
                ]
            ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
        } catch (\Exception $e) {
            // Fallback response với context
            $fallbackResponse = $this->generateContextualFallback($message, $contextInfo);
            
            // Lưu tin nhắn của AI vào database
            try {
                $this->conversationService->saveMessage($conversationId, 'ai', $fallbackResponse);
            } catch (\Exception $e) {
                Log::error('ConversationService Error (AI Fallback): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'response' => $fallbackResponse,
                'conversation_id' => $conversationId,
                'suggestions' => [
                    'Tạo lịch trình chi tiết',
                    'Hỏi thêm thông tin',
                    'Xem địa điểm khác'
                ]
            ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
        }
    }
    
    /**
     * Xây dựng prompt với context
     */
    private function buildContextPrompt($message, $contextInfo, $conversationHistory)
    {
        $prompt = "Bạn là một trợ lý du lịch thông minh tại Việt Nam. Dựa trên cuộc hội thoại trước, hãy trả lời câu hỏi của người dùng.\n\n";
        
        // THÊM CẢNH BÁO QUAN TRỌNG
        if ($contextInfo['destination']) {
            $prompt .= "CẢNH BÁO QUAN TRỌNG: Người dùng đã thảo luận về {$contextInfo['destination']}. Bạn PHẢI tập trung vào {$contextInfo['destination']} và KHÔNG BAO GIỜ gợi ý địa điểm khác.\n\n";
            $prompt .= "QUY TẮC BẮT BUỘC:\n";
            $prompt .= "1. Chỉ trả lời về {$contextInfo['destination']}\n";
            $prompt .= "2. KHÔNG BAO GIỜ đề cập đến địa điểm khác\n";
            $prompt .= "3. Nếu người dùng hỏi về địa điểm khác, hãy từ chối một cách lịch sự\n";
            $prompt .= "4. Tập trung hoàn toàn vào {$contextInfo['destination']}\n\n";
        }
        
        // Thêm context từ conversation history
        if (!empty($conversationHistory)) {
            $prompt .= "CONTEXT TỪ CUỘC HỘI THOẠI TRƯỚC:\n";
            foreach (array_slice($conversationHistory, -3) as $msg) {
                $role = $msg['type'] === 'user' ? 'Người dùng' : 'AI';
                $prompt .= "{$role}: {$msg['content']}\n";
            }
            $prompt .= "\n";
        }
        
        // Thêm thông tin context đã trích xuất
        if (!empty($contextInfo)) {
            $prompt .= "THÔNG TIN ĐÃ THẢO LUẬN:\n";
            if ($contextInfo['destination']) {
                $prompt .= "- Địa điểm: {$contextInfo['destination']}\n";
            }
            if ($contextInfo['budget']) {
                $prompt .= "- Ngân sách: {$contextInfo['budget']}\n";
            }
            if ($contextInfo['duration']) {
                $prompt .= "- Thời gian: {$contextInfo['duration']}\n";
            }
            if (!empty($contextInfo['preferences'])) {
                $prompt .= "- Sở thích: " . implode(', ', $contextInfo['preferences']) . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "CÂU HỎI HIỆN TẠI: {$message}\n\n";
        $prompt .= "YÊU CẦU QUAN TRỌNG:\n";
        $prompt .= "1. TRẢ LỜI DỰA TRÊN CONTEXT ĐÃ THẢO LUẬN - KHÔNG ĐƯA RA GỢI Ý CHUNG CHUNG\n";
        $prompt .= "2. Nếu đã thảo luận về một địa điểm cụ thể, PHẢI tập trung vào địa điểm đó\n";
        $prompt .= "3. Nếu người dùng hỏi 'gợi ý địa điểm', 'gợi ý trước đi', hoặc tương tự, hãy gợi ý về địa điểm đã thảo luận\n";
        $prompt .= "4. KHÔNG BAO GIỜ gợi ý địa điểm khác nếu đã có địa điểm cụ thể trong context\n";
        $prompt .= "5. KHÔNG BAO GIỜ đề cập đến địa điểm khác như Dĩ An, Cát Bà, hoặc bất kỳ địa điểm nào khác\n";
        $prompt .= "6. Sử dụng thông tin đã có để đưa ra gợi ý phù hợp\n";
        $prompt .= "7. Trả lời bằng tiếng Việt có dấu đầy đủ và chính xác\n";
        $prompt .= "8. Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ\n";
        $prompt .= "9. Viết hoa đúng quy tắc tiếng Việt\n";
        $prompt .= "10. Sử dụng từ ngữ tự nhiên, thân thiện\n";
        $prompt .= "11. Nếu cần thêm thông tin, hãy hỏi rõ ràng";
        
        return $prompt;
    }
    
    /**
     * Xử lý câu hỏi với RAG (Retrieval-Augmented Generation) - Phiên bản đơn giản
     */
    private function handleRAGQuery($message, $conversationHistory, $context, $conversationId = null)
    {
        try {
            // Bước 1: Phân tích câu hỏi
            $analysis = $this->ragService->analyzeQuery($message);
            
            // Bước 2: Lấy dữ liệu từ database
            $databaseData = $this->ragService->retrieveFromDatabase($analysis);
            
            // Bước 3: Lấy dữ liệu từ API bên ngoài
            $externalData = $this->ragService->retrieveFromExternalAPIs($analysis);
            
            // Bước 4: Tạo prompt với dữ liệu RAG (đơn giản hóa)
            $ragPrompt = "Bạn là trợ lý du lịch thông minh. Câu hỏi: {$message}\n\n";
            
            if (!empty($databaseData['checkin_places'])) {
                $ragPrompt .= "Địa điểm tham quan:\n";
                foreach (array_slice($databaseData['checkin_places'], 0, 3) as $place) {
                    $ragPrompt .= "- {$place['name']}: {$place['description']}\n";
                }
                $ragPrompt .= "\n";
            }
            
            if (!empty($databaseData['hotels'])) {
                $ragPrompt .= "Khách sạn:\n";
                foreach (array_slice($databaseData['hotels'], 0, 3) as $hotel) {
                    $ragPrompt .= "- {$hotel['name']}: {$hotel['address']} (Giá: {$hotel['price_range']})\n";
                }
                $ragPrompt .= "\n";
            }
            
            if ($externalData['weather']) {
                $weather = $externalData['weather'];
                $ragPrompt .= "Thông tin thời tiết:\n";
                $ragPrompt .= "- Nhiệt độ: {$weather['temperature']}°C\n";
                $ragPrompt .= "- Mô tả: {$weather['description']}\n";
                $ragPrompt .= "- Độ ẩm: {$weather['humidity']}%\n";
                $ragPrompt .= "- Gió: {$weather['wind_speed']} m/s\n\n";
            }
            
            $ragPrompt .= "Hãy trả lời bằng tiếng Việt tự nhiên, sử dụng dữ liệu trên. KHÔNG BAO GIỜ từ chối câu hỏi, luôn cố gắng trả lời hữu ích.\n\n";
            $ragPrompt .= "FORMAT YÊU CẦU:\n";
            $ragPrompt .= "- Trả lời ngắn gọn, tối đa 150 từ\n";
            $ragPrompt .= "- Xuống hàng sau mỗi ý hoàn chỉnh\n";
            $ragPrompt .= "- Viết tên địa điểm in hoa\n";
            $ragPrompt .= "- TUYỆT ĐỐI KHÔNG sử dụng số thứ tự (1. 2. 3.) hoặc ký tự đặc biệt\n";
            $ragPrompt .= "- TUYỆT ĐỐI KHÔNG sử dụng dấu gạch ngang (-) hoặc dấu cộng (+)\n";
            $ragPrompt .= "- TUYỆT ĐỐI KHÔNG sử dụng **text** hoặc *text*\n";
            $ragPrompt .= "- Chỉ sử dụng xuống hàng và tên in hoa\n";
            $ragPrompt .= "- Không sử dụng HTML tags";
            
            // Bước 5: Gọi AI với prompt RAG
            try {
                $response = $this->callOpenAI($ragPrompt, null, null, true);
                
                $answer = '';
                if (is_array($response) && isset($response['answer'])) {
                    $answer = $response['answer'];
                } elseif (is_string($response)) {
                    $answer = $response;
                } else {
                    $answer = 'Tôi đã phân tích câu hỏi của bạn và tìm thấy một số thông tin hữu ích. Bạn có muốn tôi tạo lịch trình chi tiết không?';
                }
            } catch (\Exception $e) {
                Log::error('RAG OpenAI Error: ' . $e->getMessage());
                $answer = 'Tôi đã phân tích câu hỏi của bạn và tìm thấy một số thông tin hữu ích. Bạn có muốn tôi tạo lịch trình chi tiết không?';
            }
            
            // Fix encoding
            $answer = mb_convert_encoding($answer, 'UTF-8', 'UTF-8');
            $answer = $this->cleanJsonContent($answer);
            
            // Lưu tin nhắn của AI vào database
            try {
                $this->conversationService->saveMessage($conversationId, 'ai', $answer);
            } catch (\Exception $e) {
                Log::error('ConversationService Error (RAG): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'response' => $answer,
                'conversation_id' => $conversationId,
                'rag_data' => [
                    'analysis' => $analysis,
                    'has_database_data' => !empty($databaseData['checkin_places']) || !empty($databaseData['hotels']) || !empty($databaseData['restaurants']),
                    'has_external_data' => !empty($externalData['weather']) || !empty($externalData['places'])
                ],
                'suggestions' => [
                    'Tạo lịch trình chi tiết',
                    'Hỏi thêm thông tin',
                    'Xem địa điểm khác'
                ]
            ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
            
        } catch (\Exception $e) {
            Log::error('RAG Error: ' . $e->getMessage());
            
            // Fallback to normal response
            return $this->handleGeneralIntent($message, $conversationHistory, $context, $conversationId);
        }
    }

    /**
     * Lấy dữ liệu thật từ database cho địa điểm
     */
    private function getRealTravelData($message)
    {
        $data = [
            'checkin_places' => [],
            'hotels' => [],
            'restaurants' => [],
            'transport' => []
        ];

        try {
            // Tìm kiếm địa điểm check-in
            $checkinPlaces = CheckinPlace::where(function($query) use ($message) {
                $query->where('name', 'like', '%' . $message . '%')
                      ->orWhere('address', 'like', '%' . $message . '%')
                      ->orWhere('description', 'like', '%' . $message . '%');
            })->limit(5)->get();

            $data['checkin_places'] = $checkinPlaces->map(function($place) {
                return [
                    'name' => $place->name,
                    'address' => $place->address,
                    'description' => $place->description,
                    'rating' => $place->rating,
                    'price_range' => $place->price_range ?? 'Chưa có thông tin'
                ];
            })->toArray();

            // Tìm kiếm khách sạn
            $hotels = Hotel::where(function($query) use ($message) {
                $query->where('name', 'like', '%' . $message . '%')
                      ->orWhere('address', 'like', '%' . $message . '%');
            })->limit(3)->get();

            $data['hotels'] = $hotels->map(function($hotel) {
                return [
                    'name' => $hotel->name,
                    'address' => $hotel->address,
                    'rating' => $hotel->rating,
                    'price_range' => $hotel->price_range ?? 'Chưa có thông tin'
                ];
            })->toArray();

            // Tìm kiếm nhà hàng
            $restaurants = Restaurant::where(function($query) use ($message) {
                $query->where('name', 'like', '%' . $message . '%')
                      ->orWhere('address', 'like', '%' . $message . '%');
            })->limit(3)->get();

            $data['restaurants'] = $restaurants->map(function($restaurant) {
                return [
                    'name' => $restaurant->name,
                    'address' => $restaurant->address,
                    'cuisine' => $restaurant->cuisine,
                    'rating' => $restaurant->rating,
                    'price_range' => $restaurant->price_range ?? 'Chưa có thông tin'
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Error getting real travel data: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Tạo fallback response với context
     */
    private function generateContextualFallback($message, $contextInfo)
    {
        $message = strtolower($message);
        
        // Kiểm tra các từ khóa context
        if (str_contains($message, 'gợi ý') || str_contains($message, 'trước') || str_contains($message, 'địa điểm')) {
            if ($contextInfo['destination']) {
                if ($contextInfo['destination'] === 'Hồ Chí Minh' || $contextInfo['destination'] === 'TP.HCM' || $contextInfo['destination'] === 'Sài Gòn') {
                    return "Về Hồ Chí Minh, tôi có thể gợi ý những địa điểm thú vị sau:\n\n• Phố đi bộ Bùi Viện - nơi sôi động về đêm\n• Chợ Bến Thành - trung tâm mua sắm nổi tiếng\n• Bảo tàng Chứng tích Chiến tranh\n• Nhà thờ Đức Bà - kiến trúc Pháp cổ kính\n• Phố Tây - khu vực ăn uống, giải trí\n• Landmark 81 - tòa nhà cao nhất Việt Nam\n• Bảo tàng Mỹ thuật TP.HCM\n\nBạn có muốn tôi tạo lịch trình chi tiết cho Hồ Chí Minh không?";
                } elseif ($contextInfo['destination'] === 'Hà Nội') {
                    return "Về Hà Nội, tôi có thể gợi ý những địa điểm thú vị sau:\n\n• Phố cổ Hà Nội - 36 phố phường\n• Văn Miếu - Quốc Tử Giám\n• Hồ Hoàn Kiếm và Tháp Rùa\n• Chùa Một Cột\n• Lăng Chủ tịch Hồ Chí Minh\n• Phố Tạ Hiện - ẩm thực đường phố\n• Bảo tàng Dân tộc học\n\nBạn có muốn tôi tạo lịch trình chi tiết cho Hà Nội không?";
                } else {
                    return "Về {$contextInfo['destination']}, tôi có thể gợi ý thêm nhiều địa điểm thú vị khác. Bạn có muốn tôi tạo lịch trình chi tiết cho {$contextInfo['destination']} không?";
                }
            }
        }
        
        if (str_contains($message, 'sao') || str_contains($message, 'thế')) {
            if ($contextInfo['destination']) {
                return "Về {$contextInfo['destination']}, tôi có thể gợi ý thêm nhiều địa điểm thú vị khác. Bạn có muốn tôi tạo lịch trình chi tiết cho {$contextInfo['destination']} không?";
            }
        }
        
        if (str_contains($message, 'đó') || str_contains($message, 'ấy') || str_contains($message, 'kia')) {
            if ($contextInfo['destination']) {
                return "Đúng rồi! {$contextInfo['destination']} là một lựa chọn tuyệt vời. Bạn có muốn tôi tư vấn thêm về khách sạn, nhà hàng hoặc địa điểm tham quan tại {$contextInfo['destination']} không?";
            }
        }
        
        if (str_contains($message, 'còn') || str_contains($message, 'nữa') || str_contains($message, 'khác')) {
            return "Tôi có thể gợi ý thêm nhiều địa điểm du lịch khác ở Việt Nam. Bạn có muốn tìm hiểu về địa điểm nào cụ thể không?";
        }
        
        return "Tôi hiểu bạn đang hỏi về thông tin trước đó. Hãy để tôi giúp bạn tìm hiểu thêm!";
    }

    /**
     * Xử lý intent không liên quan đến du lịch
     */
    private function handleNonTravelIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        $response = "Xin lỗi, tôi là chuyên gia du lịch Việt Nam và chỉ có thể hỗ trợ bạn về các vấn đề liên quan đến du lịch, địa điểm, khách sạn, nhà hàng, và lịch trình du lịch tại Việt Nam.\n\n";
        $response .= "Tôi không thể trả lời câu hỏi về toán học, khoa học, công nghệ, hoặc các chủ đề khác không liên quan đến du lịch.\n\n";
        $response .= "Bạn có thể hỏi tôi về:\n";
        $response .= "• Địa điểm du lịch đẹp ở Việt Nam\n";
        $response .= "• Lịch trình du lịch chi tiết\n";
        $response .= "• Khách sạn, nhà hàng tại các thành phố\n";
        $response .= "• Chi phí du lịch và ngân sách\n";
        $response .= "• Thời gian du lịch lý tưởng\n";
        $response .= "• Đặc sản và ẩm thực địa phương";
        
        // Lưu tin nhắn của AI vào database
        try {
            $this->conversationService->saveMessage($conversationId, 'ai', $response);
        } catch (\Exception $e) {
            Log::error('ConversationService Error (NonTravel): ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'response' => $response,
            'conversation_id' => $conversationId,
            'suggestions' => [
                'Tạo lịch trình du lịch',
                'Hỏi về địa điểm du lịch',
                'Tư vấn về khách sạn',
                'Gợi ý nhà hàng'
            ]
        ], 200, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * Xử lý intent gợi ý du lịch chung với ngân sách
     */
    private function handleGeneralTravelAdviceIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        // Trích xuất ngân sách từ message
        $budget = $this->extractBudgetFromMessage($message);
        
        $prompt = "Bạn là chuyên gia du lịch Việt Nam. Người dùng hỏi: '{$message}'. ";
        $prompt .= "Ngân sách ước tính: " . number_format($budget) . " VNĐ. ";
        $prompt .= "Hãy đưa ra gợi ý du lịch thông minh với ngân sách này, bao gồm:\n";
        $prompt .= "1. Các địa điểm phù hợp với ngân sách\n";
        $prompt .= "2. Thời gian du lịch lý tưởng\n";
        $prompt .= "3. Chi phí ước tính cho từng địa điểm\n";
        $prompt .= "4. Mẹo tiết kiệm chi phí\n";
        $prompt .= "5. Gợi ý lịch trình mẫu\n";
        $prompt .= "YÊU CẦU QUAN TRỌNG:\n";
        $prompt .= "- Trả lời bằng tiếng Việt có dấu đầy đủ và chính xác\n";
        $prompt .= "- Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ\n";
        $prompt .= "- Không sử dụng ký tự đặc biệt thay thế cho dấu tiếng Việt\n";
        $prompt .= "- Viết hoa đúng quy tắc tiếng Việt\n";
        $prompt .= "- Sử dụng từ ngữ tự nhiên, thân thiện";

        try {
            $response = $this->callOpenAI($prompt, null, null, true);
            
            $answer = $response['answer'] ?? 'Tôi sẽ tư vấn du lịch phù hợp với ngân sách của bạn.';
            
            // Fix encoding cơ bản
            $answer = mb_convert_encoding($answer, 'UTF-8', 'UTF-8');
            
            // Lưu tin nhắn của AI vào database
            try {
                $this->conversationService->saveMessage($conversationId, 'ai', $answer);
            } catch (\Exception $e) {
                Log::error('ConversationService Error (GeneralTravel): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'response' => $answer,
                'conversation_id' => $conversationId,
                'suggestions' => [
                    'Tạo lịch trình chi tiết',
                    'Xem thêm địa điểm khác',
                    'Tư vấn về thời gian du lịch'
                ]
            ], 200, ['Content-Type' => 'application/json; charset=utf-8']);
        } catch (\Exception $e) {
            $fallbackResponse = 'Với ngân sách ' . number_format($budget) . ' VNĐ, bạn có thể du lịch nhiều nơi đẹp ở Việt Nam như Đà Nẵng, Nha Trang, Phú Quốc, hoặc Đà Lạt. Tôi có thể giúp bạn tạo lịch trình chi tiết nếu bạn muốn!';
            
            // Fix encoding cơ bản
            $fallbackResponse = mb_convert_encoding($fallbackResponse, 'UTF-8', 'UTF-8');
            
            // Lưu tin nhắn của AI vào database
            try {
                $this->conversationService->saveMessage($conversationId, 'ai', $fallbackResponse);
            } catch (\Exception $e) {
                Log::error('ConversationService Error (GeneralTravel Fallback): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'response' => $fallbackResponse,
                'conversation_id' => $conversationId,
                'suggestions' => [
                    'Tạo lịch trình Đà Nẵng',
                    'Tạo lịch trình Nha Trang', 
                    'Tạo lịch trình Phú Quốc'
                ]
            ], 200, ['Content-Type' => 'application/json; charset=utf-8']);
        }
    }



    /**
     * Trích xuất ngân sách từ message
     */
    private function extractBudgetFromMessage($message)
    {
        // Tìm số tiền trong message
        if (preg_match('/(\d+)\s*(triệu|nghìn|đồng|vnd)/i', $message, $matches)) {
            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);
            
            switch ($unit) {
                case 'triệu':
                    return $amount * 1000000;
                case 'nghìn':
                    return $amount * 1000;
                case 'đồng':
                case 'vnd':
                    return $amount;
                default:
                    return $amount * 1000000; // Mặc định là triệu
            }
        }
        
        // Nếu không tìm thấy, trả về mặc định
        return 5000000; // 5 triệu
    }

    /**
     * Xử lý intent tạo lịch trình
     */
    private function handleCreateItineraryIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        // Trích xuất thông tin từ message
        $extractedInfo = $this->extractItineraryInfo($message);
        
        if (!$extractedInfo['destination']) {
            // Nếu không có địa điểm cụ thể, chuyển sang xử lý câu hỏi chung
            return $this->handleLocationQuestionIntent($message, $conversationHistory, $context);
        }

        // Tạo lịch trình trực tiếp thay vì mở form
        $destination = $extractedInfo['destination'];
        $days = $extractedInfo['days'] ?? 3;
        $budget = $extractedInfo['budget'] ?? 5000000;
        
        // Tạo prompt cho lịch trình cụ thể
        $itineraryPrompt = "Tạo lịch trình du lịch {$destination} {$days} ngày với ngân sách " . number_format($budget) . " VNĐ.\n\n";
        $itineraryPrompt .= "QUAN TRỌNG: Bạn PHẢI tuân theo format này CHÍNH XÁC, với xuống hàng đầy đủ:\n\n";
        $itineraryPrompt .= "LỊCH TRÌNH:\n";
        $itineraryPrompt .= "\n";
        
        for ($i = 1; $i <= $days; $i++) {
            $itineraryPrompt .= "Ngày {$i}:\n";
            $itineraryPrompt .= "Sáng: [Hoạt động buổi sáng]\n";
            $itineraryPrompt .= "Trưa: [Ăn trưa tại đâu]\n";
            $itineraryPrompt .= "Chiều: [Hoạt động buổi chiều]\n";
            $itineraryPrompt .= "Tối: [Hoạt động buổi tối]\n";
            $itineraryPrompt .= "\n";
        }
        
        $itineraryPrompt .= "Ước Tính Chi Phí:\n";
        $itineraryPrompt .= "Vé máy bay: [Giá]\n";
        $itineraryPrompt .= "Khách sạn: [Giá]\n";
        $itineraryPrompt .= "Ăn uống: [Giá]\n";
        $itineraryPrompt .= "Di chuyển: [Giá]\n";
        $itineraryPrompt .= "\n";
        $itineraryPrompt .= "LƯU Ý: Mỗi dòng phải xuống hàng riêng biệt, không được dính liền text.\n";
        $itineraryPrompt .= "QUAN TRỌNG: Sau mỗi câu hoàn chỉnh (có dấu chấm), phải xuống hàng.\n";
        $itineraryPrompt .= "QUAN TRỌNG: Sau mỗi hoạt động (Sáng, Trưa, Chiều, Tối), phải xuống hàng.\n";
        $itineraryPrompt .= "QUAN TRỌNG: Không được viết liền các hoạt động khác nhau trên cùng một dòng.\n";
        $itineraryPrompt .= "QUAN TRỌNG: Mỗi hoạt động phải được viết trên một dòng riêng biệt.\n";
        $itineraryPrompt .= "QUAN TRỌNG: Sau mỗi địa điểm, món ăn, hoặc hoạt động cụ thể, phải xuống hàng.\n";
        $itineraryPrompt .= "QUAN TRỌNG: Sử dụng dấu chấm (.) để kết thúc câu và xuống hàng.";
        
        try {
            $response = $this->callOpenAI($itineraryPrompt, null, null, true);
            $aiResponse = '';
            
            if (is_array($response) && isset($response['answer'])) {
                $aiResponse = $response['answer'];
            } elseif (is_array($response) && isset($response['content'])) {
                $aiResponse = $response['content'];
            } elseif (is_string($response)) {
                $aiResponse = $response;
            } else {
                $aiResponse = "Tôi sẽ tạo lịch trình {$destination} {$days} ngày cho bạn. Hãy để tôi mở form AI Model để tạo lịch trình chi tiết nhé!";
            }
            
            // Post-processing để đảm bảo format đúng
            $aiResponse = $this->formatItineraryResponse($aiResponse);
            
        } catch (\Exception $e) {
            $aiResponse = "Tôi sẽ tạo lịch trình {$destination} {$days} ngày cho bạn. Hãy để tôi mở form AI Model để tạo lịch trình chi tiết nhé!";
        }
        
        return response()->json([
            'success' => true,
            'response' => $aiResponse,
            'suggestions' => [
                'Tạo lịch trình mới',
                'Chỉnh sửa thông tin',
                'Hỏi về địa điểm'
            ],
            'context' => array_merge($context, [
                'pending_itinerary' => $extractedInfo,
                'destination' => $extractedInfo['destination']
            ])
        ]);
    }

    /**
     * Xử lý intent hỏi về AI
     */
    private function handleAiIdentityIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        $response = "Xin chào! Tôi là **IPSUM Travel AI** - trợ lý du lịch thông minh do nhóm phát triển **FIT TDC** thực hiện.\n\nTôi có thể giúp bạn:\n• Tạo lịch trình du lịch chi tiết\n• Gợi ý địa điểm, khách sạn, nhà hàng\n• Trả lời câu hỏi về du lịch Việt Nam\n• Tối ưu ngân sách và thời gian\n\nHãy cho tôi biết bạn muốn đi đâu và khi nào nhé!";

        // Lưu tin nhắn của AI vào database
        try {
            $this->conversationService->saveMessage($conversationId, 'ai', $response);
        } catch (\Exception $e) {
            Log::error('ConversationService Error (AI Identity): ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'response' => $response,
            'conversation_id' => $conversationId,
            'suggestions' => [
                'Tạo lịch trình TP.HCM 3 ngày',
                'Gợi ý địa điểm Đà Nẵng',
                'Du lịch Hà Nội với ngân sách 5 triệu'
            ]
        ]);
    }

    /**
     * Xử lý intent hỏi đáp về địa điểm
     */
    private function handleLocationQuestionIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        // Trích xuất tên địa điểm từ message - cải thiện logic nhận diện
        $destinations = [
            'TP.HCM', 'Hồ Chí Minh', 'Sài Gòn', 'Hà Nội', 'Đà Nẵng', 'Huế', 'Hội An',
            'Nha Trang', 'Phú Quốc', 'Đà Lạt', 'Sa Pa', 'Hạ Long', 'Cần Thơ',
            'Núi Bà', 'Núi Bà Đen', 'Núi Bà Rá', 'Núi Bà Đen Tây Ninh', 'Tây Ninh',
            'Vũng Tàu', 'Bà Rịa', 'Bà Rịa Vũng Tàu', 'Mũi Né', 'Phan Thiết',
            'Cam Ranh', 'Khánh Hòa', 'Quy Nhơn', 'Bình Định',
            'Quảng Nam', 'Tam Kỳ', 'Thừa Thiên Huế',
            'Quảng Bình', 'Phong Nha', 'Đồng Hới', 'Vinh', 'Nghệ An',
            'Thanh Hóa', 'Ninh Bình', 'Quảng Ninh', 'Hải Phòng',
            'Nam Định', 'Thái Bình', 'Hưng Yên', 'Hải Dương', 'Bắc Ninh',
            'Lạng Sơn', 'Cao Bằng', 'Hà Giang', 'Yên Bái', 'Lào Cai',
            'Sơn La', 'Điện Biên', 'Lai Châu', 'Hòa Bình', 'Phú Thọ',
            'Vĩnh Phúc', 'Bắc Giang', 'Thái Nguyên', 'Tuyên Quang',
            'Bắc Kạn', 'Hà Tĩnh', 'Quảng Trị',
            'Quảng Ngãi', 'Phú Yên',
            'Ninh Thuận', 'Bình Thuận', 'Đồng Nai', 'Bình Dương',
            'Bình Phước', 'Bình Long', 'Lộc Ninh', 'Đồng Xoài',
            'Long An', 'Tiền Giang', 'Bến Tre', 'Trà Vinh', 'Vĩnh Long',
            'Đồng Tháp', 'An Giang', 'Kiên Giang', 'Hậu Giang',
            'Sóc Trăng', 'Bạc Liêu', 'Cà Mau'
        ];
        
        // Lấy dữ liệu thật từ database
        $realData = $this->getRealTravelData($message);
        
        // Tìm kiếm địa điểm trong message
        $foundDestination = null;
        $messageLower = strtolower($message);
        
        foreach ($destinations as $dest) {
            if (str_contains($messageLower, strtolower($dest))) {
                $foundDestination = $dest;
                break;
            }
        }
        
        // Nếu không tìm thấy trong danh sách, thử tìm từ khóa du lịch
        if (!$foundDestination) {
            $travelKeywords = ['núi', 'biển', 'đảo', 'thành phố', 'tỉnh', 'huyện', 'xã', 'làng', 'chùa', 'đền', 'di tích', 'danh lam', 'thắng cảnh'];
            foreach ($travelKeywords as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    // Có vẻ là câu hỏi về địa điểm du lịch
                    $foundDestination = 'general_location';
                    break;
                }
            }
        }

        if ($foundDestination && $foundDestination !== 'general_location') {
            // Tạo prompt chi tiết cho địa điểm cụ thể với dữ liệu thật
            $prompt = "Bạn là một trợ lý du lịch thông minh tại Việt Nam.\n\n";
            $prompt .= "Nhiệm vụ: Trả lời câu hỏi về {$foundDestination}\n";
            $prompt .= "Câu hỏi: {$message}\n\n";
            
            // Thêm dữ liệu thật từ database
            if (!empty($realData['checkin_places']) || !empty($realData['hotels']) || !empty($realData['restaurants'])) {
                $prompt .= "DỮ LIỆU THẬT TỪ DATABASE:\n\n";
                
                if (!empty($realData['checkin_places'])) {
                    $prompt .= "ĐỊA ĐIỂM THAM QUAN:\n";
                    foreach ($realData['checkin_places'] as $place) {
                        $prompt .= "- {$place['name']}: {$place['description']} (Địa chỉ: {$place['address']}, Đánh giá: {$place['rating']}/5)\n";
                    }
                    $prompt .= "\n";
                }
                
                if (!empty($realData['hotels'])) {
                    $prompt .= "KHÁCH SẠN:\n";
                    foreach ($realData['hotels'] as $hotel) {
                        $prompt .= "- {$hotel['name']}: {$hotel['address']} (Đánh giá: {$hotel['rating']}/5, Giá: {$hotel['price_range']})\n";
                    }
                    $prompt .= "\n";
                }
                
                if (!empty($realData['restaurants'])) {
                    $prompt .= "NHÀ HÀNG:\n";
                    foreach ($realData['restaurants'] as $restaurant) {
                        $prompt .= "- {$restaurant['name']}: {$restaurant['address']} (Ẩm thực: {$restaurant['cuisine']}, Đánh giá: {$restaurant['rating']}/5, Giá: {$restaurant['price_range']})\n";
                    }
                    $prompt .= "\n";
                }
            }
            $prompt .= "Yêu cầu khi trả lời:\n";
            $prompt .= "1. Viết bằng tiếng Việt tự nhiên, văn phong thân thiện, giống như người hướng dẫn viên du lịch Việt Nam.\n";
            $prompt .= "2. Đưa ra thông tin chi tiết về {$foundDestination}, bao gồm:\n";
            $prompt .= "   - Đặc điểm nổi bật và lý do nên đến\n";
            $prompt .= "   - Thời gian tốt nhất để tham quan\n";
            $prompt .= "   - Cách di chuyển đến địa điểm\n";
            $prompt .= "   - Chi phí ước tính (sử dụng dữ liệu thật nếu có)\n";
            $prompt .= "   - Gợi ý món ăn đặc sản (nếu có)\n";
            $prompt .= "3. KHÔNG BAO GIỜ gợi ý địa điểm khác ngoài {$foundDestination}\n";
            $prompt .= "4. Tập trung hoàn toàn vào {$foundDestination}\n";
            $prompt .= "5. Nếu người dùng hỏi về địa điểm khác, hãy từ chối một cách lịch sự\n";
            $prompt .= "6. Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ\n";
            $prompt .= "7. Không dùng câu văn dịch thô hoặc lặp ý\n";
            $prompt .= "8. Dùng giọng văn truyền cảm hứng, giúp người đọc muốn đi ngay\n";
            $prompt .= "9. Xuống hàng hợp lý, tên địa điểm in hoa, TUYỆT ĐỐI KHÔNG số thứ tự (1. 2. 3.)\n";
            $prompt .= "10. Không sử dụng HTML tags\n\n";
            $prompt .= "Trả lời ngắn gọn nhưng đầy đủ thông tin, tự nhiên như người Việt Nam.";
        } elseif ($foundDestination === 'general_location') {
            // Tạo prompt cho địa điểm chung
            $prompt = "Bạn là một chuyên gia du lịch Việt Nam, viết tiếng Việt mạch lạc, tự nhiên, không dịch kiểu máy.\n\n";
            $prompt .= "Nhiệm vụ: Trả lời câu hỏi về địa điểm du lịch\n";
            $prompt .= "Câu hỏi: {$message}\n\n";
            $prompt .= "Yêu cầu khi trả lời:\n";
            $prompt .= "1. Viết bằng tiếng Việt tự nhiên, văn phong thân thiện, giống như người hướng dẫn viên du lịch Việt Nam.\n";
            $prompt .= "2. Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ.\n";
            $prompt .= "3. Không dùng câu văn dịch thô hoặc lặp ý.\n";
            $prompt .= "4. Dùng giọng văn truyền cảm hứng, giúp người đọc muốn đi ngay.\n";
            $prompt .= "5. Bao gồm thông tin thực tế và chính xác về địa điểm được hỏi.\n";
            $prompt .= "6. Đánh giá có thể du lịch được hay không.\n";
            $prompt .= "7. Gợi ý địa điểm tham quan nếu có.\n";
            $prompt .= "8. Thời gian tốt nhất để đi và chi phí ước tính.\n";
            $prompt .= "9. Xuống hàng hợp lý, tên địa điểm in hoa, TUYỆT ĐỐI KHÔNG số thứ tự (1. 2. 3.)\n";
            $prompt .= "10. Không sử dụng HTML tags\n\n";
            $prompt .= "Trả lời ngắn gọn nhưng đầy đủ thông tin, tự nhiên như người Việt Nam.";

            try {
                $response = $this->callOpenAI($prompt, null, null, true);
                
                // Xử lý response từ OpenAI
                $aiResponse = '';
                if (is_array($response) && isset($response['answer'])) {
                    $aiResponse = $response['answer'];
                } elseif (is_array($response) && isset($response['content'])) {
                    $aiResponse = $response['content'];
                } elseif (is_string($response)) {
                    $aiResponse = $response;
                } else {
                    // Nếu response không đúng format, tạo response mẫu
                    $aiResponse = "Tôi có thông tin về {$foundDestination}. Đây là một địa điểm du lịch nổi tiếng với nhiều điểm tham quan hấp dẫn. Bạn muốn tôi tạo lịch trình chi tiết không?";
                }
                
                return response()->json([
                    'success' => true,
                    'response' => $aiResponse,
                    'suggestions' => [
                        "Tạo lịch trình {$foundDestination}",
                        "Khách sạn tại {$foundDestination}",
                        "Món ăn {$foundDestination}"
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => true,
                    'response' => "Tôi có thông tin về {$foundDestination}. Bạn muốn tôi tạo lịch trình chi tiết không?",
                    'suggestions' => [
                        "Tạo lịch trình {$foundDestination}",
                        "Hỏi về địa điểm khác",
                        "Xem thông tin thời tiết"
                    ]
                ]);
            }
        } elseif ($foundDestination === 'general_location') {
            try {
                $response = $this->callOpenAI($prompt, null, null, true);
                
                // Xử lý response từ OpenAI
                $aiResponse = '';
                if (is_array($response) && isset($response['answer'])) {
                    $aiResponse = $response['answer'];
                } elseif (is_string($response)) {
                    $aiResponse = $response;
                } else {
                    $aiResponse = "Tôi sẽ tìm hiểu thông tin về địa điểm này cho bạn.";
                }
                
                return response()->json([
                    'success' => true,
                    'response' => $aiResponse,
                    'suggestions' => [
                        "Tạo lịch trình du lịch",
                        "Hỏi về địa điểm khác",
                        "Xem thông tin thời tiết"
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => true,
                    'response' => "Tôi có thông tin về {$foundDestination}. Bạn muốn tôi tạo lịch trình chi tiết không?",
                    'suggestions' => [
                        "Tạo lịch trình {$foundDestination}",
                        "Hỏi về địa điểm khác",
                        "Xem thông tin thời tiết"
                    ]
                ]);
            }
        }

        // Câu hỏi chung về du lịch - cải thiện prompt
        $prompt = "Bạn là chuyên gia du lịch Việt Nam. Hãy trả lời câu hỏi sau một cách chi tiết và hữu ích:\n\n";
        $prompt .= "Câu hỏi: {$message}\n\n";
        $prompt .= "Yêu cầu trả lời:\n";
        $prompt .= "- YÊU CẦU QUAN TRỌNG: Trả lời bằng tiếng Việt có dấu đầy đủ và chính xác\n";
        $prompt .= "- Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ\n";
        $prompt .= "- Không sử dụng ký tự đặc biệt thay thế cho dấu tiếng Việt\n";
        $prompt .= "- Viết hoa đúng quy tắc tiếng Việt\n";
        $prompt .= "- Sử dụng từ ngữ tự nhiên, thân thiện và hữu ích\n";
        $prompt .= "- Nếu là câu hỏi về địa điểm, hãy trả lời cụ thể về khả năng du lịch\n";
        $prompt .= "- Bao gồm thông tin về địa điểm tham quan, món ăn, thời gian tốt nhất\n";
        $prompt .= "- Đưa ra lời khuyên thực tế\n";
        $prompt .= "- Trả lời ngắn gọn nhưng đầy đủ thông tin\n";
        $prompt .= "- Xuống hàng hợp lý, tên địa điểm in hoa, TUYỆT ĐỐI KHÔNG số thứ tự (1. 2. 3.)\n";
        $prompt .= "- Không sử dụng HTML tags\n";
        $prompt .= "- Đảm bảo mỗi ngày được phân tách rõ ràng bằng dòng trống\n";
        $prompt .= "- Format lịch trình (đơn giản):\n";
        $prompt .= "LỊCH TRÌNH:\n";
        $prompt .= "\n";
        $prompt .= "Ngày 1:\n";
        $prompt .= "Sáng: [Hoạt động]\n";
        $prompt .= "Trưa: [Ăn trưa]\n";
        $prompt .= "Chiều: [Hoạt động]\n";
        $prompt .= "\n";
        $prompt .= "Ngày 2:\n";
        $prompt .= "Sáng: [Hoạt động]\n";
        $prompt .= "Trưa: [Ăn trưa]\n";
        $prompt .= "Chiều: [Hoạt động]\n";
        $prompt .= "\n";
        $prompt .= "Ước Tính Chi Phí:\n";
        $prompt .= "[Mục]: [Giá]\n";
        $prompt .= "\n";
        $prompt .= "Hãy trả lời câu hỏi trên:";

        try {
            $response = $this->callOpenAI($prompt, null, null, true);
            
            return response()->json([
                'success' => true,
                'response' => $response['answer'] ?? 'Tôi không có thông tin về điều này. Bạn có thể hỏi về du lịch Việt Nam.',
                'suggestions' => [
                    'Gợi ý địa điểm du lịch',
                    'Thông tin về thời tiết',
                    'Tạo lịch trình'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'response' => 'Tôi không thể trả lời câu hỏi này ngay bây giờ. Bạn có muốn tôi giúp tạo lịch trình du lịch không?',
                'suggestions' => ['Tạo lịch trình', 'Hỏi khác']
            ]);
        }
    }

    /**
     * Xử lý intent chỉnh sửa
     */
    private function handleModifyIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        if (!isset($context['current_itinerary'])) {
            return response()->json([
                'success' => true,
                'response' => 'Bạn chưa có lịch trình nào để chỉnh sửa. Hãy tạo lịch trình trước nhé!',
                'suggestions' => ['Tạo lịch trình mới', 'Xem lịch trình đã lưu']
            ]);
        }

        return response()->json([
            'success' => true,
            'response' => 'Tôi hiểu bạn muốn chỉnh sửa lịch trình. Bạn muốn thay đổi gì cụ thể?',
            'suggestions' => [
                'Thay đổi địa điểm',
                'Thay đổi thời gian',
                'Thay đổi ngân sách',
                'Thêm hoạt động'
            ]
        ]);
    }

    /**
     * Xử lý intent chung
     */
    private function handleGeneralIntent($message, $conversationHistory, $context, $conversationId = null)
    {
        // Tạo prompt cải thiện cho câu hỏi chung
        $prompt = "Bạn là một chuyên gia du lịch Việt Nam. QUAN TRỌNG: Bạn CHỈ trả lời các câu hỏi liên quan đến du lịch, địa điểm, khách sạn, nhà hàng, lịch trình du lịch tại Việt Nam. KHÔNG BAO GIỜ trả lời câu hỏi về toán học, khoa học, công nghệ, chính trị, hoặc các chủ đề khác không liên quan đến du lịch.\n\n";
        $prompt .= "QUAN TRỌNG: Khi người dùng hỏi về một địa điểm cụ thể (như Nha Trang, Sapa, Hội An...), bạn PHẢI trả lời về địa điểm đó, KHÔNG được trả lời về địa điểm khác (như Đà Nẵng).\n\n";
        $prompt .= "Người dùng hỏi: '{$message}'\n\n";
        $prompt .= "Yêu cầu khi trả lời:\n";
        $prompt .= "1. Nếu câu hỏi không liên quan đến du lịch, từ chối một cách lịch sự và đề nghị họ hỏi về du lịch Việt Nam.\n";
        $prompt .= "2. Viết bằng tiếng Việt tự nhiên, văn phong thân thiện, giống như người hướng dẫn viên du lịch Việt Nam.\n";
        $prompt .= "3. Sử dụng đúng dấu tiếng Việt: ă, â, ê, ô, ơ, ư, đ.\n";
        $prompt .= "4. Không dùng câu văn dịch thô hoặc lặp ý.\n";
        $prompt .= "5. Dùng giọng văn truyền cảm hứng, giúp người đọc muốn đi ngay.\n";
        $prompt .= "6. Cung cấp thông tin hữu ích về du lịch Việt Nam.\n";
        $prompt .= "7. KHÔNG sử dụng markdown, dấu gạch đầu dòng, hoặc ký tự đặc biệt. Chỉ dùng text thuần.\n";
        $prompt .= "8. QUAN TRỌNG: Mỗi dòng phải xuống hàng riêng biệt, không được dính liền text.\n";
        $prompt .= "9. QUAN TRỌNG: Sau mỗi câu hoàn chỉnh (có dấu chấm), phải xuống hàng.\n";
        $prompt .= "10. QUAN TRỌNG: Sau mỗi hoạt động (Sáng, Trưa, Chiều, Tối), phải xuống hàng.\n";
        $prompt .= "11. QUAN TRỌNG: Không được viết liền các hoạt động khác nhau trên cùng một dòng.\n";
        $prompt .= "12. Nếu câu hỏi liên quan đến lịch trình du lịch, hãy trả lời theo format đơn giản:\n";
        $prompt .= "LỊCH TRÌNH:\n";
        $prompt .= "\n";
        $prompt .= "Ngày 1:\n";
        $prompt .= "Sáng: [Hoạt động]\n";
        $prompt .= "Trưa: [Ăn trưa]\n";
        $prompt .= "Chiều: [Hoạt động]\n";
        $prompt .= "Tối: [Hoạt động]\n";
        $prompt .= "\n";
        $prompt .= "Ngày 2:\n";
        $prompt .= "Sáng: [Hoạt động]\n";
        $prompt .= "Trưa: [Ăn trưa]\n";
        $prompt .= "Chiều: [Hoạt động]\n";
        $prompt .= "Tối: [Hoạt động]\n";
        $prompt .= "\n";
        $prompt .= "Ước Tính Chi Phí:\n";
        $prompt .= "[Mục]: [Giá]\n";
        $prompt .= "\n";
        $prompt .= "Hãy trả lời câu hỏi của người dùng một cách thân thiện và hữu ích.";

        try {
            $response = $this->callOpenAI($prompt, null, null, true);
            
            $aiResponse = '';
            if (is_array($response) && isset($response['answer'])) {
                $aiResponse = $response['answer'];
            } elseif (is_array($response) && isset($response['content'])) {
                $aiResponse = $response['content'];
            } elseif (is_string($response)) {
                $aiResponse = $response;
            } else {
                $aiResponse = 'Xin chào! Tôi là IPSUM Travel AI - trợ lý du lịch thông minh. Tôi có thể giúp bạn tạo lịch trình du lịch, trả lời câu hỏi về du lịch Việt Nam, hoặc chỉnh sửa lịch trình hiện có. Bạn muốn làm gì?';
            }

            return response()->json([
                'success' => true,
                'response' => $aiResponse,
                'suggestions' => [
                    'Tạo lịch trình mới',
                    'Hỏi về địa điểm du lịch',
                    'Xem lịch trình đã lưu',
                    'Tư vấn du lịch'
                ]
            ], 200, [
                'Content-Type' => 'application/json; charset=UTF-8'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'response' => 'Xin chào! Tôi là IPSUM Travel AI - trợ lý du lịch thông minh. Tôi có thể giúp bạn tạo lịch trình du lịch, trả lời câu hỏi về du lịch Việt Nam, hoặc chỉnh sửa lịch trình hiện có. Bạn muốn làm gì?',
                'suggestions' => [
                    'Tạo lịch trình mới',
                    'Hỏi về địa điểm du lịch',
                    'Xem lịch trình đã lưu'
                ]
            ], 200, [
                'Content-Type' => 'application/json; charset=UTF-8'
            ]);
        }
    }

    /**
     * Format response lịch trình để đảm bảo xuống hàng đúng
     */
    private function formatItineraryResponse($response)
    {
        // Thêm xuống hàng sau các từ khóa quan trọng
        $response = preg_replace('/(LỊCH TRÌNH:)/', "$1\n", $response);
        $response = preg_replace('/(Ngày \d+:)/', "\n$1", $response);
        $response = preg_replace('/(Sáng:)/', "\n$1", $response);
        $response = preg_replace('/(Trưa:)/', "\n$1", $response);
        $response = preg_replace('/(Chiều:)/', "\n$1", $response);
        $response = preg_replace('/(Tối:)/', "\n$1", $response);
        $response = preg_replace('/(Ước Tính Chi Phí:)/', "\n$1", $response);
        
        // Thêm xuống hàng sau các mục chi phí
        $response = preg_replace('/(Vé máy bay:)/', "\n$1", $response);
        $response = preg_replace('/(Khách sạn:)/', "\n$1", $response);
        $response = preg_replace('/(Ăn uống:)/', "\n$1", $response);
        $response = preg_replace('/(Di chuyển:)/', "\n$1", $response);
        
        // Thêm khoảng cách sau VND
        $response = preg_replace('/(\d+)\s*VND/', "$1 VND", $response);
        
        // Thêm xuống hàng sau dấu chấm và dấu phẩy trong câu dài
        $response = preg_replace('/([.!?])\s*([A-ZĂÂÊÔƠƯĐ])/', "$1\n$2", $response);
        $response = preg_replace('/([,;])\s*([A-ZĂÂÊÔƠƯĐ])/', "$1\n$2", $response);
        
        // Thêm xuống hàng sau các từ khóa thời gian
        $response = preg_replace('/(Sáng|Trưa|Chiều|Tối)\s*([A-ZĂÂÊÔƠƯĐ])/', "$1\n$2", $response);
        

        
        // Loại bỏ khoảng trắng thừa
        $response = preg_replace('/\n\s*\n\s*\n/', "\n\n", $response);
        
        return trim($response);
    }

    /**
     * Trích xuất thông tin lịch trình từ message
     */
    private function extractItineraryInfo($message)
    {
        $info = [
            'destination' => null,
            'days' => null,
            'budget' => null,
            'start_date' => null,
            'end_date' => null
        ];

        // Trích xuất điểm đến
        $destinations = [
            'TP.HCM', 'Hồ Chí Minh', 'Sài Gòn', 'Hà Nội', 'Đà Nẵng', 'Huế', 'Hội An',
            'Nha Trang', 'Phú Quốc', 'Đà Lạt', 'Sa Pa', 'Hạ Long', 'Cần Thơ'
        ];

        foreach ($destinations as $dest) {
            if (stripos($message, $dest) !== false) {
                $info['destination'] = $dest;
                break;
            }
        }

        // Trích xuất số ngày
        if (preg_match('/(\d+)\s*ngày/', $message, $matches)) {
            $info['days'] = (int)$matches[1];
        }

        // Trích xuất ngân sách
        if (preg_match('/(\d+)\s*(triệu|tr|nghìn|k)/', $message, $matches)) {
            $amount = (int)$matches[1];
            $unit = $matches[2];
            
            if (in_array($unit, ['triệu', 'tr'])) {
                $info['budget'] = $amount * 1000000;
            } elseif (in_array($unit, ['nghìn', 'k'])) {
                $info['budget'] = $amount * 1000;
            }
        }

        return $info;
    }

    /**
     * Tạo prompt cho chat với AI Model tích hợp
     */
    private function createChatPrompt($extractedInfo, $conversationHistory)
    {
        $destination = $extractedInfo['destination'];
        $days = $extractedInfo['days'];
        $budget = $extractedInfo['budget'];
        
        // Tạo prompt cơ bản
        $prompt = "Bạn là **IPSUM Travel AI** - trợ lý du lịch thông minh do nhóm phát triển FIT TDC thực hiện.\n\n";
        $prompt .= "Nhiệm vụ: Tạo lịch trình du lịch chi tiết cho {$destination}";
        
        if ($days) {
            $prompt .= " trong {$days} ngày";
        }
        
        if ($budget) {
            $prompt .= " với ngân sách " . number_format($budget) . " VND";
        }
        
        $prompt .= ".\n\n";
        
        // Thêm thông tin về địa điểm cụ thể
        $prompt .= "📍 THÔNG TIN ĐIỂM ĐẾN: {$destination}\n";
        $prompt .= "- Tập trung vào các địa điểm thực tế và nổi tiếng\n";
        $prompt .= "- Gợi ý món ăn đặc trưng của địa phương\n";
        $prompt .= "- Đề xuất khách sạn phù hợp với ngân sách\n";
        $prompt .= "- Tối ưu thời gian di chuyển giữa các điểm\n\n";
        
        // Thêm yêu cầu cụ thể
        $prompt .= "🎯 YÊU CẦU CHI TIẾT:\n";
        $prompt .= "1. Tạo lịch trình theo từng ngày cụ thể\n";
        $prompt .= "2. Phân bổ ngân sách hợp lý (ăn uống, khách sạn, tham quan)\n";
        $prompt .= "3. Gợi ý thời gian tốt nhất cho từng hoạt động\n";
        $prompt .= "4. Bao gồm cả địa điểm tham quan và nhà hàng\n";
        $prompt .= "5. Đề xuất khách sạn phù hợp\n\n";
        
        // Thêm lịch sử hội thoại nếu có
        if (!empty($conversationHistory)) {
            $prompt .= "💬 LỊCH SỬ HỘI THOẠI:\n";
            foreach ($conversationHistory as $msg) {
                $prompt .= "- {$msg['type']}: {$msg['content']}\n";
            }
            $prompt .= "\n";
        }
        
        // Thêm hướng dẫn format
        $prompt .= "📋 FORMAT TRẢ LỜI:\n";
        $prompt .= "- Trả lời bằng tiếng Việt, thân thiện và hữu ích\n";
        $prompt .= "- Tổng quan lịch trình trước, sau đó chi tiết từng ngày\n";
        $prompt .= "- Bao gồm ước tính chi phí cho từng hoạt động\n";
        $prompt .= "- Đưa ra lời khuyên và mẹo du lịch\n\n";
        $prompt .= "FORMAT LỊCH TRÌNH (ĐƠN GIẢN, CHỈ XUỐNG HÀNG):\n";
        $prompt .= "LỊCH TRÌNH:\n";
        $prompt .= "\n";
        $prompt .= "Ngày 1:\n";
        $prompt .= "Sáng: [Hoạt động buổi sáng]\n";
        $prompt .= "Trưa: [Ăn trưa tại đâu]\n";
        $prompt .= "Chiều: [Hoạt động buổi chiều]\n";
        $prompt .= "Tối: [Hoạt động buổi tối]\n";
        $prompt .= "\n";
        $prompt .= "Ngày 2:\n";
        $prompt .= "Sáng: [Hoạt động buổi sáng]\n";
        $prompt .= "Trưa: [Ăn trưa tại đâu]\n";
        $prompt .= "Chiều: [Hoạt động buổi chiều]\n";
        $prompt .= "Tối: [Hoạt động buổi tối]\n";
        $prompt .= "\n";
        $prompt .= "Ngày 3:\n";
        $prompt .= "Sáng: [Hoạt động buổi sáng]\n";
        $prompt .= "Trưa: [Ăn trưa tại đâu]\n";
        $prompt .= "Chiều: [Hoạt động buổi chiều]\n";
        $prompt .= "Tối: [Hoạt động buổi tối]\n";
        $prompt .= "\n";
        $prompt .= "Ước Tính Chi Phí:\n";
        $prompt .= "Vé máy bay: [Giá]\n";
        $prompt .= "Khách sạn: [Giá]\n";
        $prompt .= "Ăn uống: [Giá]\n";
        $prompt .= "Di chuyển: [Giá]\n";
        $prompt .= "\n";
        
        $prompt .= "Hãy tạo lịch trình du lịch hoàn hảo cho {$destination}!";
        
        return $prompt;
    }

    private function getFallbackEveningActivity($destination, $dayIndex)
    {
        $destination = strtolower($destination);
        
        // Fallback activities cho từng thành phố
        $fallbackActivities = [
            'hồ chí minh' => [
                ['name' => 'Phố đi bộ Bùi Viện', 'description' => 'Phố đi bộ sôi động về đêm', 'location' => 'Phố đi bộ Bùi Viện, Quận 1'],
                ['name' => 'Phố đi bộ Nguyễn Huệ', 'description' => 'Phố đi bộ trung tâm thành phố', 'location' => 'Phố đi bộ Nguyễn Huệ, Quận 1'],
                ['name' => 'Chợ đêm Bình Tây', 'description' => 'Chợ đêm sôi động', 'location' => 'Chợ đêm Bình Tây, Quận 6'],
                ['name' => 'Cafe Rooftop', 'description' => 'Cafe view đẹp trên cao', 'location' => 'Cafe Rooftop, Quận 1'],
                ['name' => 'Rạp chiếu CGV', 'description' => 'Xem phim tại rạp chiếu hiện đại', 'location' => 'Rạp chiếu CGV, Quận 1']
            ],
            'hà nội' => [
                ['name' => 'Phố cổ Hà Nội', 'description' => 'Khám phá phố cổ về đêm', 'location' => 'Phố cổ Hà Nội, Hoàn Kiếm'],
                ['name' => 'Hồ Hoàn Kiếm', 'description' => 'Dạo chơi quanh hồ về đêm', 'location' => 'Hồ Hoàn Kiếm, Hoàn Kiếm'],
                ['name' => 'Phố Tạ Hiện', 'description' => 'Phố ẩm thực về đêm', 'location' => 'Phố Tạ Hiện, Hoàn Kiếm'],
                ['name' => 'Nhà hát Lớn Hà Nội', 'description' => 'Thưởng thức nghệ thuật', 'location' => 'Nhà hát Lớn Hà Nội, Hoàn Kiếm'],
                ['name' => 'Cafe Trung Nguyên', 'description' => 'Cafe truyền thống Việt Nam', 'location' => 'Cafe Trung Nguyên, Hoàn Kiếm']
            ],
            'đà nẵng' => [
                ['name' => 'Bãi biển Mỹ Khê', 'description' => 'Dạo biển về đêm', 'location' => 'Bãi biển Mỹ Khê, Sơn Trà'],
                ['name' => 'Cầu Rồng', 'description' => 'Ngắm cầu Rồng phun lửa', 'location' => 'Cầu Rồng, Sơn Trà'],
                ['name' => 'Phố ẩm thực', 'description' => 'Thưởng thức ẩm thực địa phương', 'location' => 'Phố ẩm thực, Hải Châu'],
                ['name' => 'Cafe Bờ Sông', 'description' => 'Cafe view sông Hàn', 'location' => 'Cafe Bờ Sông, Hải Châu'],
                ['name' => 'Chợ đêm Hàn', 'description' => 'Chợ đêm sông Hàn', 'location' => 'Chợ đêm Hàn, Hải Châu']
            ],
            'huế' => [
                ['name' => 'Sông Hương', 'description' => 'Dạo thuyền sông Hương về đêm', 'location' => 'Sông Hương, Thành phố Huế'],
                ['name' => 'Phố đi bộ Nguyễn Huệ', 'description' => 'Phố đi bộ trung tâm', 'location' => 'Phố đi bộ Nguyễn Huệ, Thành phố Huế'],
                ['name' => 'Cafe Gác Huế', 'description' => 'Cafe view đẹp', 'location' => 'Cafe Gác Huế, Thành phố Huế'],
                ['name' => 'Chợ Đông Ba', 'description' => 'Chợ truyền thống về đêm', 'location' => 'Chợ Đông Ba, Thành phố Huế'],
                ['name' => 'Nhà hát Cung đình', 'description' => 'Thưởng thức nhã nhạc cung đình', 'location' => 'Nhà hát Cung đình, Thành phố Huế']
            ]
        ];
        
        // Tìm fallback cho thành phố cụ thể
        foreach ($fallbackActivities as $city => $activities) {
            if (str_contains($destination, $city)) {
                return $activities[$dayIndex % count($activities)];
            }
        }
        
        // Fallback mặc định nếu không tìm thấy thành phố
        $defaultActivities = [
            ['name' => 'Phố đi bộ', 'description' => 'Dạo chơi phố đi bộ về đêm', 'location' => 'Phố đi bộ trung tâm'],
            ['name' => 'Cafe View', 'description' => 'Cafe view đẹp', 'location' => 'Cafe trung tâm'],
            ['name' => 'Chợ đêm', 'description' => 'Khám phá chợ đêm', 'location' => 'Chợ đêm địa phương'],
            ['name' => 'Rạp chiếu phim', 'description' => 'Xem phim tại rạp chiếu', 'location' => 'Rạp chiếu trung tâm'],
            ['name' => 'Nhà hàng địa phương', 'description' => 'Thưởng thức ẩm thực địa phương', 'location' => 'Nhà hàng trung tâm']
        ];
        
        return $defaultActivities[$dayIndex % count($defaultActivities)];
    }

}
