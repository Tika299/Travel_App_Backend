<?php

namespace App\Services;

use App\Models\CheckinPlace;
use App\Models\Restaurant;
use App\Models\Hotel;
use Illuminate\Support\Collection;

class SmartPlaceSelectionService
{
    /**
     * Tính điểm cho địa điểm dựa trên nhiều tiêu chí
     */
    public function calculatePlaceScore($place, $context)
    {
        $score = 0;
        
        // 1. Thời tiết (30% trọng số)
        $score += $this->getWeatherScore($place, $context['weather'] ?? 'sunny');
        
        // 2. Giá cả (25% trọng số)
        $score += $this->getBudgetScore($place, $context['budget'] ?? 1000000);
        
        // 3. Khoảng cách (20% trọng số)
        $score += $this->getDistanceScore($place, $context['current_location'] ?? null);
        
        // 4. Đánh giá người dùng (15% trọng số)
        $score += $this->getRatingScore($place);
        
        // 5. Thời gian phù hợp (10% trọng số)
        $score += $this->getTimeSlotScore($place, $context['time_slot'] ?? 'morning');
        
        return $score;
    }
    
    /**
     * Tính điểm thời tiết
     */
    private function getWeatherScore($place, $weather)
    {
        $name = strtolower($place->name ?? '');
        $description = strtolower($place->description ?? '');
        
        // Địa điểm ngoài trời
        $outdoorKeywords = ['công viên', 'bãi biển', 'vườn', 'phố đi bộ', 'chùa', 'nhà thờ'];
        $isOutdoor = false;
        foreach ($outdoorKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                $isOutdoor = true;
                break;
            }
        }
        
        // Địa điểm trong nhà
        $indoorKeywords = ['bảo tàng', 'trung tâm thương mại', 'cafe', 'nhà hàng', 'khách sạn', 'rạp chiếu'];
        $isIndoor = false;
        foreach ($indoorKeywords as $keyword) {
            if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                $isIndoor = true;
                break;
            }
        }
        
        switch ($weather) {
            case 'sunny':
            case 'clear':
                return $isOutdoor ? 30 : 15;
            case 'rainy':
            case 'storm':
                return $isIndoor ? 30 : 5;
            case 'cloudy':
            case 'overcast':
                return 20; // Cả hai đều OK
            default:
                return 15;
        }
    }
    
    /**
     * Tính điểm ngân sách
     */
    private function getBudgetScore($place, $totalBudget)
    {
        $price = $place->price ?? $place->cost ?? 0;
        if ($price == 0) return 25; // Miễn phí
        
        $budgetRatio = $price / $totalBudget;
        
        if ($budgetRatio <= 0.05) return 25;      // Rất rẻ
        elseif ($budgetRatio <= 0.1) return 20;   // Rẻ
        elseif ($budgetRatio <= 0.2) return 15;   // Trung bình
        elseif ($budgetRatio <= 0.3) return 10;   // Đắt
        else return 5;                            // Rất đắt
    }
    
    /**
     * Tính điểm khoảng cách (đơn giản)
     */
    private function getDistanceScore($place, $currentLocation)
    {
        // Tạm thời return điểm trung bình, sau này có thể tích hợp Google Maps API
        return 15;
    }
    
    /**
     * Tính điểm đánh giá
     */
    private function getRatingScore($place)
    {
        $rating = $place->rating ?? 3.5; // Default rating
        return ($rating / 5) * 15;
    }
    
    /**
     * Tính điểm thời gian phù hợp
     */
    private function getTimeSlotScore($place, $timeSlot)
    {
        $name = strtolower($place->name ?? '');
        $description = strtolower($place->description ?? '');
        
        switch ($timeSlot) {
            case 'breakfast':
                // Bữa sáng: nhà hàng, cafe, quán ăn
                $breakfastKeywords = ['nhà hàng', 'cafe', 'quán', 'bánh', 'phở', 'bún'];
                foreach ($breakfastKeywords as $keyword) {
                    if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                        return 10;
                    }
                }
                return 5;
                
            case 'morning':
                // Sáng: chùa, nhà thờ, công viên, bảo tàng
                $morningKeywords = ['chùa', 'nhà thờ', 'công viên', 'bảo tàng', 'văn miếu'];
                foreach ($morningKeywords as $keyword) {
                    if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                        return 10;
                    }
                }
                return 5;
                
            case 'lunch':
                // Bữa trưa: nhà hàng, quán ăn
                $lunchKeywords = ['nhà hàng', 'quán', 'phở', 'bún', 'cơm'];
                foreach ($lunchKeywords as $keyword) {
                    if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                        return 10;
                    }
                }
                return 5;
                
            case 'afternoon':
                // Chiều: bảo tàng, trung tâm thương mại, cafe
                $afternoonKeywords = ['bảo tàng', 'trung tâm', 'cafe', 'nhà hàng'];
                foreach ($afternoonKeywords as $keyword) {
                    if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                        return 10;
                    }
                }
                return 5;
                
            case 'dinner':
                // Bữa tối: nhà hàng, quán ăn
                $dinnerKeywords = ['nhà hàng', 'quán', 'phở', 'bún', 'cơm'];
                foreach ($dinnerKeywords as $keyword) {
                    if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                        return 10;
                    }
                }
                return 5;
                
            case 'evening':
                // Tối: phố đi bộ, chợ đêm, nhà hàng, cafe
                $eveningKeywords = ['phố đi bộ', 'chợ đêm', 'nhà hàng', 'cafe', 'rooftop'];
                foreach ($eveningKeywords as $keyword) {
                    if (str_contains($name, $keyword) || str_contains($description, $keyword)) {
                        return 10;
                    }
                }
                return 5;
                
            default:
                return 5;
        }
    }
    
    /**
     * Chọn địa điểm thông minh thay vì random
     */
    public function selectSmartPlace($availablePlaces, $context, $usedPlaceIds = [])
    {
        $scoredPlaces = [];
        
        foreach ($availablePlaces as $place) {
            // Loại bỏ đã sử dụng
            if (in_array($place->id, $usedPlaceIds)) {
                continue;
            }
            
            // Tính điểm thông minh
            $score = $this->calculatePlaceScore($place, $context);
            
            // Bonus cho đa dạng (tránh trùng lặp loại địa điểm)
            $diversityBonus = $this->calculateDiversityBonus($place, $usedPlaceIds, $availablePlaces);
            $score += $diversityBonus;
            
            $scoredPlaces[] = [
                'place' => $place,
                'score' => $score
            ];
        }
        
        // Sắp xếp theo điểm cao nhất
        usort($scoredPlaces, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Trả về địa điểm có điểm cao nhất
        return $scoredPlaces[0]['place'] ?? null;
    }
    
    /**
     * Tính bonus đa dạng để tránh trùng lặp
     */
    private function calculateDiversityBonus($place, $usedPlaceIds, $allPlaces)
    {
        $bonus = 0;
        
        // Lấy loại địa điểm hiện tại
        $currentType = $this->getPlaceType($place);
        
        // Đếm số địa điểm cùng loại đã sử dụng
        $sameTypeCount = 0;
        
        foreach ($allPlaces as $allPlace) {
            if (in_array($allPlace->id, $usedPlaceIds) && $this->getPlaceType($allPlace) === $currentType) {
                $sameTypeCount++;
            }
        }
        
        // Càng ít trùng lặp càng nhiều bonus
        if ($sameTypeCount == 0) {
            $bonus = 15; // Chưa có loại này
        } elseif ($sameTypeCount == 1) {
            $bonus = 5;  // Có 1 rồi
        } else {
            $bonus = -10; // Penalty cho trùng lặp nhiều
        }
        
        return $bonus;
    }
    
    /**
     * Phân loại địa điểm
     */
    public function getPlaceType($place)
    {
        $name = strtolower($place->name ?? '');
        $description = strtolower($place->description ?? '');
        
        if (str_contains($name, 'chùa') || str_contains($description, 'chùa')) {
            return 'temple';
        }
        if (str_contains($name, 'bảo tàng') || str_contains($description, 'bảo tàng')) {
            return 'museum';
        }
        if (str_contains($name, 'công viên') || str_contains($description, 'công viên')) {
            return 'park';
        }
        if (str_contains($name, 'phố đi bộ') || str_contains($description, 'phố đi bộ')) {
            return 'walking_street';
        }
        if (str_contains($name, 'chợ') || str_contains($description, 'chợ')) {
            return 'market';
        }
        if (str_contains($name, 'nhà hàng') || str_contains($description, 'nhà hàng')) {
            return 'restaurant';
        }
        if (str_contains($name, 'cafe') || str_contains($description, 'cafe')) {
            return 'cafe';
        }
        
        return 'other';
    }
    
    /**
     * Tạo context cho AI
     */
    public function createContext($destination, $budget, $travelers, $timeSlot = 'morning')
    {
        return [
            'destination' => $destination,
            'budget' => $budget,
            'travelers' => $travelers,
            'time_slot' => $timeSlot,
            'weather' => $this->getWeatherForDestination($destination), // Có thể tích hợp API thời tiết
            'current_location' => null, // Có thể lấy từ user location
            'user_style' => 'balanced' // Có thể lấy từ user preferences
        ];
    }
    
    /**
     * Lấy thời tiết cho địa điểm (mô phỏng)
     */
    private function getWeatherForDestination($destination)
    {
        // Tạm thời return ngẫu nhiên, sau này tích hợp API thời tiết thực
        $weathers = ['sunny', 'cloudy', 'rainy'];
        return $weathers[array_rand($weathers)];
    }
}
