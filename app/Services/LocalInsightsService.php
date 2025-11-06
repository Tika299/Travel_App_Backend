<?php

namespace App\Services;

use App\Models\CheckinPlace;
use App\Models\Hotel;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LocalInsightsService
{
    /**
     * Dự đoán mức độ đông đúc
     */
    public function getCrowdPrediction($destination, $date)
    {
        $dateObj = Carbon::parse($date);
        $dayOfWeek = $dateObj->dayOfWeek;
        $isWeekend = $dayOfWeek == 0 || $dayOfWeek == 6;
        $isHoliday = $this->isHoliday($date);
        $season = $this->getSeason($date);

        // Logic dự đoán đông đúc
        $crowdLevel = 'low';
        $peakHours = [];
        $recommendations = [];

        // Dựa trên ngày trong tuần
        if ($isWeekend) {
            $crowdLevel = 'high';
            $peakHours = ['09:00-11:00', '14:00-16:00', '18:00-20:00'];
            $recommendations[] = 'Cuối tuần thường đông, nên đi sớm hoặc muộn';
        }

        // Dựa trên ngày lễ
        if ($isHoliday) {
            $crowdLevel = 'very_high';
            $peakHours = ['08:00-12:00', '15:00-19:00'];
            $recommendations[] = 'Ngày lễ rất đông, nên đặt chỗ trước';
        }

        // Dựa trên mùa
        switch ($season) {
            case 'summer':
                $crowdLevel = $crowdLevel === 'low' ? 'medium' : $crowdLevel;
                $recommendations[] = 'Mùa hè nóng, nên chọn hoạt động trong nhà buổi trưa';
                break;
            case 'winter':
                $crowdLevel = $crowdLevel === 'low' ? 'medium' : $crowdLevel;
                $recommendations[] = 'Mùa đông mát mẻ, phù hợp hoạt động ngoài trời';
                break;
        }

        // Dựa trên địa điểm cụ thể
        $destinationCrowd = $this->getDestinationCrowdLevel($destination);
        if ($destinationCrowd === 'high') {
            $crowdLevel = $crowdLevel === 'low' ? 'medium' : $crowdLevel;
        }

        return [
            'crowd_level' => $crowdLevel,
            'peak_hours' => $peakHours,
            'recommendations' => $recommendations,
            'factors' => [
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'season' => $season,
                'destination_crowd' => $destinationCrowd
            ]
        ];
    }

    /**
     * Lấy thông tin địa phương
     */
    public function getLocalInsights($destination)
    {
        $insights = [
            'best_times' => $this->getBestTimes($destination),
            'local_tips' => $this->getLocalTips($destination),
            'hidden_gems' => $this->getHiddenGems($destination),
            'seasonal_highlights' => $this->getSeasonalHighlights($destination),
            'local_cuisine' => $this->getLocalCuisine($destination),
            'transportation_tips' => $this->getTransportationTips($destination)
        ];

        return $insights;
    }

    /**
     * Lấy thời gian tốt nhất
     */
    private function getBestTimes($destination)
    {
        $bestTimes = [
            'TP.HCM' => [
                'best_season' => 'Tháng 12 - Tháng 4 (mùa khô)',
                'best_hours' => '06:00-10:00, 16:00-20:00',
                'avoid_hours' => '12:00-15:00 (nắng nóng)',
                'reason' => 'Tránh mưa và nắng nóng'
            ],
            'Hà Nội' => [
                'best_season' => 'Tháng 9 - Tháng 11, Tháng 3 - Tháng 5',
                'best_hours' => '07:00-11:00, 15:00-19:00',
                'avoid_hours' => '12:00-14:00 (nắng nóng)',
                'reason' => 'Thời tiết dễ chịu, ít mưa'
            ],
            'Đà Nẵng' => [
                'best_season' => 'Tháng 2 - Tháng 5',
                'best_hours' => '06:00-10:00, 16:00-20:00',
                'avoid_hours' => '12:00-15:00 (nắng nóng)',
                'reason' => 'Mùa khô, biển đẹp'
            ],
            'Phú Quốc' => [
                'best_season' => 'Tháng 11 - Tháng 4',
                'best_hours' => '06:00-10:00, 16:00-18:00',
                'avoid_hours' => '12:00-15:00 (nắng nóng)',
                'reason' => 'Mùa khô, biển xanh'
            ]
        ];

        return $bestTimes[$destination] ?? $bestTimes['TP.HCM'];
    }

    /**
     * Lấy mẹo địa phương
     */
    private function getLocalTips($destination)
    {
        $tips = [
            'TP.HCM' => [
                'Mặc quần áo thoáng mát, mang theo ô/dù',
                'Sử dụng Grab hoặc taxi để di chuyển',
                'Ăn sáng tại các quán cà phê địa phương',
                'Mua sắm tại chợ Bến Thành vào buổi tối',
                'Thưởng thức cà phê sữa đá đặc trưng'
            ],
            'Hà Nội' => [
                'Mặc ấm vào mùa đông, thoáng mát vào mùa hè',
                'Đi bộ khám phá phố cổ',
                'Thưởng thức phở và bún chả',
                'Tham quan Hồ Hoàn Kiếm vào sáng sớm',
                'Mua sắm tại chợ Đồng Xuân'
            ],
            'Đà Nẵng' => [
                'Tắm biển vào sáng sớm hoặc chiều muộn',
                'Thưởng thức hải sản tươi sống',
                'Leo Ngũ Hành Sơn vào sáng sớm',
                'Tham quan Bán đảo Sơn Trà',
                'Ăn mì Quảng đặc trưng'
            ]
        ];

        return $tips[$destination] ?? $tips['TP.HCM'];
    }

    /**
     * Lấy địa điểm ẩn
     */
    private function getHiddenGems($destination)
    {
        $hiddenGems = [
            'TP.HCM' => [
                'Cà phê vỉa hè tại quận 3',
                'Chợ hoa Hồ Thị Kỷ',
                'Công viên Tao Đàn',
                'Phố đi bộ Bùi Viện',
                'Bảo tàng Chứng tích Chiến tranh'
            ],
            'Hà Nội' => [
                'Phố Tạ Hiện về đêm',
                'Chợ hoa Quảng An',
                'Công viên Thống Nhất',
                'Phố cổ Hà Nội',
                'Bảo tàng Dân tộc học'
            ],
            'Đà Nẵng' => [
                'Bãi biển Mỹ Khê',
                'Núi Ngũ Hành Sơn',
                'Bán đảo Sơn Trà',
                'Sông Hàn về đêm',
                'Bảo tàng Chăm'
            ]
        ];

        return $hiddenGems[$destination] ?? $hiddenGems['TP.HCM'];
    }

    /**
     * Lấy điểm nổi bật theo mùa
     */
    private function getSeasonalHighlights($destination)
    {
        $highlights = [
            'TP.HCM' => [
                'spring' => 'Lễ hội hoa xuân, chợ hoa Tết',
                'summer' => 'Cà phê sữa đá, kem dừa',
                'autumn' => 'Lễ hội trăng rằm',
                'winter' => 'Lễ hội Noel, năm mới'
            ],
            'Hà Nội' => [
                'spring' => 'Hoa đào, hoa mai',
                'summer' => 'Bia hơi, kem Tràng Tiền',
                'autumn' => 'Hoa sữa, gió heo may',
                'winter' => 'Phở nóng, chả cá'
            ]
        ];

        return $highlights[$destination] ?? $highlights['TP.HCM'];
    }

    /**
     * Lấy ẩm thực địa phương
     */
    private function getLocalCuisine($destination)
    {
        $cuisine = [
            'TP.HCM' => [
                'Phở Hòa Pasteur',
                'Cơm tấm Sài Gòn',
                'Bánh mì Huỳnh Hoa',
                'Cà phê sữa đá',
                'Bún thịt nướng'
            ],
            'Hà Nội' => [
                'Phở Thìn',
                'Bún chả Hương Liên',
                'Chả cá Lã Vọng',
                'Bánh cuốn Thanh Trì',
                'Cà phê trứng'
            ],
            'Đà Nẵng' => [
                'Mì Quảng',
                'Bánh tráng cuốn thịt heo',
                'Bún chả cá',
                'Cao lầu',
                'Bánh bèo'
            ]
        ];

        return $cuisine[$destination] ?? $cuisine['TP.HCM'];
    }

    /**
     * Lấy mẹo giao thông
     */
    private function getTransportationTips($destination)
    {
        $tips = [
            'TP.HCM' => [
                'Sử dụng Grab hoặc taxi',
                'Xe buýt công cộng rẻ tiền',
                'Xe máy thuê tại khách sạn',
                'Đi bộ trong phố cổ',
                'Tàu điện ngầm (đang xây dựng)'
            ],
            'Hà Nội' => [
                'Xe buýt công cộng',
                'Xe máy thuê',
                'Đi bộ khám phá phố cổ',
                'Xe đạp thuê',
                'Taxi truyền thống'
            ]
        ];

        return $tips[$destination] ?? $tips['TP.HCM'];
    }

    /**
     * Kiểm tra ngày lễ
     */
    private function isHoliday($date)
    {
        $holidays = [
            '01-01', // Tết Dương lịch
            '30-04', // Giải phóng miền Nam
            '01-05', // Quốc tế Lao động
            '02-09', // Quốc khánh
        ];

        $dateFormat = Carbon::parse($date)->format('d-m');
        return in_array($dateFormat, $holidays);
    }

    /**
     * Lấy mùa
     */
    private function getSeason($date)
    {
        $month = Carbon::parse($date)->month;
        
        if ($month >= 3 && $month <= 5) return 'spring';
        if ($month >= 6 && $month <= 8) return 'summer';
        if ($month >= 9 && $month <= 11) return 'autumn';
        return 'winter';
    }

    /**
     * Lấy mức độ đông đúc của địa điểm
     */
    private function getDestinationCrowdLevel($destination)
    {
        $crowdLevels = [
            'TP.HCM' => 'high',
            'Hà Nội' => 'high',
            'Đà Nẵng' => 'medium',
            'Phú Quốc' => 'medium',
            'Nha Trang' => 'medium',
            'Huế' => 'low',
            'Hội An' => 'medium',
            'Sa Pa' => 'low',
            'Đà Lạt' => 'medium'
        ];

        return $crowdLevels[$destination] ?? 'medium';
    }

    /**
     * Tạo prompt AI với thông tin địa phương
     */
    public function createLocalAwarePrompt($destination, $date, $crowdPrediction, $localInsights)
    {
        $prompt = "Tạo lịch trình du lịch cho {$destination} với thông tin địa phương:\n\n";
        
        // Thông tin đông đúc
        $prompt .= "DỰ ĐOÁN ĐÔNG ĐÚC:\n";
        $prompt .= "- Mức độ: {$crowdPrediction['crowd_level']}\n";
        $prompt .= "- Giờ cao điểm: " . implode(', ', $crowdPrediction['peak_hours']) . "\n";
        $prompt .= "- Gợi ý: " . implode(', ', $crowdPrediction['recommendations']) . "\n\n";
        
        // Thông tin địa phương
        $prompt .= "THÔNG TIN ĐỊA PHƯƠNG:\n";
        $prompt .= "- Thời gian tốt nhất: {$localInsights['best_times']['best_hours']}\n";
        $prompt .= "- Mẹo địa phương: " . implode(', ', array_slice($localInsights['local_tips'], 0, 3)) . "\n";
        $prompt .= "- Ẩm thực đặc trưng: " . implode(', ', array_slice($localInsights['local_cuisine'], 0, 3)) . "\n\n";
        
        $prompt .= "Hãy tạo lịch trình phù hợp với thông tin trên, tránh giờ cao điểm và tận dụng thời gian tốt nhất.";
        
        return $prompt;
    }
}


