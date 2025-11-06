<?php

namespace App\Http\Controllers\Api; // <-- Đã sửa namespace thành App\Http\Controllers\Api

use App\Models\Location; // Import Model Location
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Import JsonResponse để có type hinting rõ ràng
use App\Http\Controllers\Controller; // <-- Đảm bảo import Controller cơ sở nếu cần, tùy cấu trúc dự án của bạn

class LocationController extends Controller
{
    /**
     * Display a listing of the locations.
     * Trả về danh sách tất cả các địa điểm (thành phố).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Lấy tất cả các location từ cơ sở dữ liệu.
            $locations = Location::all();

            // Trả về danh sách locations dưới dạng JSON response
            return response()->json($locations);

        } catch (\Exception $e) {
            // Xử lý lỗi nếu có
            \Log::error('Error fetching locations: ' . $e->getMessage()); // Ghi log lỗi
            return response()->json([
                'message' => 'Failed to fetch locations.',
                'error' => $e->getMessage()
            ], 500); // Trả về lỗi 500 (Internal Server Error)
        }
    }
}
