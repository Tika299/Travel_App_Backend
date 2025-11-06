<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesController extends Controller
{
    public function search(Request $request)
    {
        try {
            $query = $request->input('query');
            
            if (empty($query)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Query parameter is required'
                ], 400);
            }

            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            if (empty($apiKey)) {
                Log::error('Google Maps API key not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Google Maps API key not configured'
                ], 500);
            }

            $url = "https://maps.googleapis.com/maps/api/place/textsearch/json";
            $params = [
                'query' => $query,
                'key' => $apiKey,
                'language' => 'vi',
                'region' => 'vn'
            ];

            Log::info('Calling Google Places API', [
                'query' => $query,
                'url' => $url
            ]);

            $response = Http::timeout(10)->get($url, $params);
            
            if (!$response->successful()) {
                Log::error('Google Places API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch places from Google API',
                    'error' => $response->status()
                ], 500);
            }

            $data = $response->json();
            
            if ($data['status'] === 'OK') {
                $places = collect($data['results'])->take(5)->map(function ($place) {
                    return [
                        'place_id' => $place['place_id'],
                        'name' => $place['name'],
                        'formatted_address' => $place['formatted_address']
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => $places
                ]);
            } else {
                Log::error('Google Places API returned error', [
                    'status' => $data['status'],
                    'error_message' => $data['error_message'] ?? 'Unknown error'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Google Places API error: ' . $data['status'],
                    'error' => $data['error_message'] ?? 'Unknown error'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Google Places API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách tỉnh thành Việt Nam
     */
    public function getVietnameseProvinces()
    {
        try {
            $apiKey = env('GOOGLE_MAPS_API_KEY');
            
            if (empty($apiKey)) {
                Log::error('Google Maps API key not configured');
                return response()->json([
                    'success' => false,
                    'message' => 'Google Maps API key not configured'
                ], 500);
            }

            // Danh sách tỉnh thành Việt Nam
            $provinces = [
                ['name' => 'Hà Nội', 'english' => 'Hanoi'],
                ['name' => 'Hồ Chí Minh', 'english' => 'Ho Chi Minh City'],
                ['name' => 'Đà Nẵng', 'english' => 'Da Nang'],
                ['name' => 'Hải Phòng', 'english' => 'Hai Phong'],
                ['name' => 'Cần Thơ', 'english' => 'Can Tho'],
                ['name' => 'An Giang', 'english' => 'An Giang'],
                ['name' => 'Bà Rịa - Vũng Tàu', 'english' => 'Ba Ria Vung Tau'],
                ['name' => 'Bắc Giang', 'english' => 'Bac Giang'],
                ['name' => 'Bắc Kạn', 'english' => 'Bac Kan'],
                ['name' => 'Bạc Liêu', 'english' => 'Bac Lieu'],
                ['name' => 'Bắc Ninh', 'english' => 'Bac Ninh'],
                ['name' => 'Bến Tre', 'english' => 'Ben Tre'],
                ['name' => 'Bình Định', 'english' => 'Binh Dinh'],
                ['name' => 'Bình Dương', 'english' => 'Binh Duong'],
                ['name' => 'Bình Phước', 'english' => 'Binh Phuoc'],
                ['name' => 'Bình Thuận', 'english' => 'Binh Thuan'],
                ['name' => 'Cà Mau', 'english' => 'Ca Mau'],
                ['name' => 'Cao Bằng', 'english' => 'Cao Bang'],
                ['name' => 'Đắk Lắk', 'english' => 'Dak Lak'],
                ['name' => 'Đắk Nông', 'english' => 'Dak Nong'],
                ['name' => 'Điện Biên', 'english' => 'Dien Bien'],
                ['name' => 'Đồng Nai', 'english' => 'Dong Nai'],
                ['name' => 'Đồng Tháp', 'english' => 'Dong Thap'],
                ['name' => 'Gia Lai', 'english' => 'Gia Lai'],
                ['name' => 'Hà Giang', 'english' => 'Ha Giang'],
                ['name' => 'Hà Nam', 'english' => 'Ha Nam'],
                ['name' => 'Hà Tĩnh', 'english' => 'Ha Tinh'],
                ['name' => 'Hải Dương', 'english' => 'Hai Duong'],
                ['name' => 'Hậu Giang', 'english' => 'Hau Giang'],
                ['name' => 'Hòa Bình', 'english' => 'Hoa Binh'],
                ['name' => 'Hưng Yên', 'english' => 'Hung Yen'],
                ['name' => 'Khánh Hòa', 'english' => 'Khanh Hoa'],
                ['name' => 'Kiên Giang', 'english' => 'Kien Giang'],
                ['name' => 'Kon Tum', 'english' => 'Kon Tum'],
                ['name' => 'Lai Châu', 'english' => 'Lai Chau'],
                ['name' => 'Lâm Đồng', 'english' => 'Lam Dong'],
                ['name' => 'Lạng Sơn', 'english' => 'Lang Son'],
                ['name' => 'Lào Cai', 'english' => 'Lao Cai'],
                ['name' => 'Long An', 'english' => 'Long An'],
                ['name' => 'Nam Định', 'english' => 'Nam Dinh'],
                ['name' => 'Nghệ An', 'english' => 'Nghe An'],
                ['name' => 'Ninh Bình', 'english' => 'Ninh Binh'],
                ['name' => 'Ninh Thuận', 'english' => 'Ninh Thuan'],
                ['name' => 'Phú Thọ', 'english' => 'Phu Tho'],
                ['name' => 'Phú Yên', 'english' => 'Phu Yen'],
                ['name' => 'Quảng Bình', 'english' => 'Quang Binh'],
                ['name' => 'Quảng Nam', 'english' => 'Quang Nam'],
                ['name' => 'Quảng Ngãi', 'english' => 'Quang Ngai'],
                ['name' => 'Quảng Ninh', 'english' => 'Quang Ninh'],
                ['name' => 'Quảng Trị', 'english' => 'Quang Tri'],
                ['name' => 'Sóc Trăng', 'english' => 'Soc Trang'],
                ['name' => 'Sơn La', 'english' => 'Son La'],
                ['name' => 'Tây Ninh', 'english' => 'Tay Ninh'],
                ['name' => 'Thái Bình', 'english' => 'Thai Binh'],
                ['name' => 'Thái Nguyên', 'english' => 'Thai Nguyen'],
                ['name' => 'Thanh Hóa', 'english' => 'Thanh Hoa'],
                ['name' => 'Thừa Thiên Huế', 'english' => 'Thua Thien Hue'],
                ['name' => 'Tiền Giang', 'english' => 'Tien Giang'],
                ['name' => 'Trà Vinh', 'english' => 'Tra Vinh'],
                ['name' => 'Tuyên Quang', 'english' => 'Tuyen Quang'],
                ['name' => 'Vĩnh Long', 'english' => 'Vinh Long'],
                ['name' => 'Vĩnh Phúc', 'english' => 'Vinh Phuc'],
                ['name' => 'Yên Bái', 'english' => 'Yen Bai']
            ];

            return response()->json([
                'success' => true,
                'data' => $provinces
            ]);

        } catch (\Exception $e) {
            Log::error('Get Vietnamese provinces error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 