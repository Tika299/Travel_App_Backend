<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FeaturedActivitiesController extends Controller
{
    public function getFeaturedActivities(Request $request)
    {
        try {
            $selectedDate = $request->input('date', now()->format('Y-m-d'));
            $location = $request->input('location', '');
            $budget = $request->input('budget', 0);
            
            // Debug: Kiểm tra user đăng nhập
            $userId = Auth::id();
            \Log::info('FeaturedActivities - User ID: ' . ($userId ?? 'null'));
            
            if (!$userId) {
                return response()->json([
                    'error' => 'User chưa đăng nhập. Vui lòng đăng nhập lại.',
                    'user_events' => [],
                    'smart_suggestions' => [],
                    'debug' => [
                        'auth_check' => Auth::check(),
                        'user_id' => $userId,
                        'request_headers' => $request->headers->all()
                    ]
                ], 401);
            }
            
            // Lấy tất cả events của user từ hôm nay trở đi (không chỉ ngày được chọn)
            $userEvents = Schedule::where('user_id', $userId)
                ->where('start_date', '>=', now()->format('Y-m-d'))
                ->select('id', 'name as title', 'start_date', 'end_date', 'description')
                ->orderBy('start_date', 'asc')
                ->get()
                ->map(function($event) {
                    $event->type = 'user_event';
                    $event->color = 'blue';
                    $event->location = ''; // Thêm location rỗng
                    
                    // Thêm mô tả mặc định nếu không có
                    if (empty($event->description)) {
                        $event->description = 'Hoạt động đã được lên lịch';
                    }
                    
                    return $event;
                });
            
            \Log::info('FeaturedActivities - Found ' . $userEvents->count() . ' user events');

            // Tạo gợi ý hoạt động thông minh
            $smartSuggestions = $this->generateSmartSuggestions($selectedDate, $location, $budget);

            // Kết hợp user events và smart suggestions
            $featuredActivities = [
                'date' => $selectedDate,
                'user_events' => $userEvents,
                'smart_suggestions' => $smartSuggestions,
                'total_activities' => $userEvents->count() + count($smartSuggestions)
            ];

            return response()->json($featuredActivities);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi khi lấy hoạt động nổi bật: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateSmartSuggestions($date, $location, $budget)
    {
        $suggestions = [];
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        $isWeekend = $dayOfWeek == 0 || $dayOfWeek == 6;

        // Gợi ý theo thời tiết (giả lập)
        $weather = $this->getWeatherSuggestion($date);
        if ($weather) {
            $suggestions[] = [
                'id' => 'weather_' . $date,
                'title' => $weather['title'],
                'description' => $weather['description'],
                'type' => 'weather_suggestion',
                'icon' => $weather['icon'],
                'color' => 'sky',
                'priority' => 1
            ];
        }

        // Gợi ý theo địa điểm
        if ($location) {
            $locationSuggestions = $this->getLocationSuggestions($location, $budget);
            $suggestions = array_merge($suggestions, $locationSuggestions);
        }

        // Gợi ý theo ngày trong tuần
        $daySuggestions = $this->getDaySuggestions($dayOfWeek, $isWeekend);
        $suggestions = array_merge($suggestions, $daySuggestions);

        // Gợi ý theo budget
        if ($budget > 0) {
            $budgetSuggestions = $this->getBudgetSuggestions($budget);
            $suggestions = array_merge($suggestions, $budgetSuggestions);
        }

        return $suggestions;
    }

    private function getWeatherSuggestion($date)
    {
        // Giả lập thời tiết (có thể tích hợp API thời tiết thật)
        $weatherTypes = [
            'sunny' => [
                'title' => 'Thời tiết đẹp - Hoạt động ngoài trời',
                'description' => 'Hôm nay trời đẹp, phù hợp cho các hoạt động ngoài trời như dã ngoại, chụp ảnh, tham quan.',
                'icon' => '☀️'
            ],
            'rainy' => [
                'title' => 'Trời mưa - Hoạt động trong nhà',
                'description' => 'Thời tiết mưa, gợi ý các hoạt động trong nhà như thăm bảo tàng, cafe, shopping.',
                'icon' => '🌧️'
            ],
            'cloudy' => [
                'title' => 'Trời âm u - Hoạt động linh hoạt',
                'description' => 'Thời tiết mát mẻ, phù hợp cho cả hoạt động trong nhà và ngoài trời.',
                'icon' => '☁️'
            ]
        ];

        // Giả lập thời tiết dựa trên ngày
        $day = Carbon::parse($date)->day;
        $weatherType = $day % 3 == 0 ? 'rainy' : ($day % 3 == 1 ? 'sunny' : 'cloudy');
        
        return $weatherTypes[$weatherType];
    }

    private function getLocationSuggestions($location, $budget)
    {
        $suggestions = [];
        
        // Gợi ý dựa trên địa điểm
        if (stripos($location, 'hà nội') !== false || stripos($location, 'hanoi') !== false) {
            $suggestions[] = [
                'id' => 'hanoi_1',
                'title' => 'Thăm Văn Miếu - Quốc Tử Giám',
                'description' => 'Di tích lịch sử văn hóa nổi tiếng của Hà Nội, phù hợp cho chuyến tham quan văn hóa.',
                'type' => 'location_suggestion',
                'icon' => '🏛️',
                'color' => 'purple',
                'estimated_cost' => 50000,
                'priority' => 2
            ];
            
            $suggestions[] = [
                'id' => 'hanoi_2',
                'title' => 'Khám phá Phố Cổ Hà Nội',
                'description' => 'Trải nghiệm ẩm thực và văn hóa truyền thống tại 36 phố phường.',
                'type' => 'location_suggestion',
                'icon' => '🍜',
                'color' => 'orange',
                'estimated_cost' => 200000,
                'priority' => 2
            ];
        }
        
        if (stripos($location, 'hồ chí minh') !== false || stripos($location, 'ho chi minh') !== false || stripos($location, 'saigon') !== false) {
            $suggestions[] = [
                'id' => 'hcm_1',
                'title' => 'Thăm Dinh Độc Lập',
                'description' => 'Di tích lịch sử quan trọng, nơi chứng kiến sự kiện 30/4/1975.',
                'type' => 'location_suggestion',
                'icon' => '🏛️',
                'color' => 'red',
                'estimated_cost' => 40000,
                'priority' => 2
            ];
        }

        return $suggestions;
    }

    private function getDaySuggestions($dayOfWeek, $isWeekend)
    {
        $suggestions = [];
        
        if ($isWeekend) {
            $suggestions[] = [
                'id' => 'weekend_1',
                'title' => 'Cuối tuần - Hoạt động giải trí',
                'description' => 'Cuối tuần là thời điểm lý tưởng cho các hoạt động giải trí, thư giãn.',
                'type' => 'day_suggestion',
                'icon' => '🎉',
                'color' => 'pink',
                'priority' => 3
            ];
        } else {
            $suggestions[] = [
                'id' => 'weekday_1',
                'title' => 'Ngày trong tuần - Hoạt động vừa phải',
                'description' => 'Ngày làm việc, gợi ý các hoạt động nhẹ nhàng, không quá tốn thời gian.',
                'type' => 'day_suggestion',
                'icon' => '💼',
                'color' => 'gray',
                'priority' => 3
            ];
        }

        return $suggestions;
    }

    private function getBudgetSuggestions($budget)
    {
        $suggestions = [];
        
        if ($budget >= 1000000) { // 1 triệu trở lên
            $suggestions[] = [
                'id' => 'budget_high',
                'title' => 'Ngân sách cao - Trải nghiệm cao cấp',
                'description' => 'Với ngân sách này, bạn có thể thưởng thức các trải nghiệm cao cấp, nhà hàng sang trọng.',
                'type' => 'budget_suggestion',
                'icon' => '💎',
                'color' => 'gold',
                'priority' => 4
            ];
        } elseif ($budget >= 500000) { // 500k - 1 triệu
            $suggestions[] = [
                'id' => 'budget_medium',
                'title' => 'Ngân sách trung bình - Hoạt động đa dạng',
                'description' => 'Ngân sách phù hợp cho nhiều loại hoạt động khác nhau.',
                'type' => 'budget_suggestion',
                'icon' => '💰',
                'color' => 'green',
                'priority' => 4
            ];
        } else { // Dưới 500k
            $suggestions[] = [
                'id' => 'budget_low',
                'title' => 'Tiết kiệm chi phí - Hoạt động miễn phí',
                'description' => 'Gợi ý các hoạt động miễn phí hoặc chi phí thấp như công viên, bảo tàng.',
                'type' => 'budget_suggestion',
                'icon' => '🆓',
                'color' => 'blue',
                'priority' => 4
            ];
        }

        return $suggestions;
    }
}
