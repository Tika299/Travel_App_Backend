<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotelRoom;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AmenitiesController extends Controller
{

    public function index(): JsonResponse
    {
        try {
            $amenities = Amenity::all(['id', 'name', 'react_icon']);
            return response()->json([
                'success' => true,
                'data' => $amenities
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error fetching all amenities: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server khi lấy danh sách tiện ích'
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:amenities,name',
            'react_icon' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $amenity = Amenity::create([
                'name' => $request->input('name'),
                'react_icon' => $request->input('react_icon'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo tiện ích thành công!',
                'data' => $amenity,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error creating amenity: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server khi tạo tiện ích',
            ], 500);
        }
    }

    /**
     * Lấy danh sách tiện ích theo ID phòng từ bảng Amenity
     *
     * @param int $roomId
     * @return JsonResponse
     */
    public function getByRoom($roomId)
    {
        try {
            // Sửa 'amenities' thành 'amenityList' để tải đúng mối quan hệ
            $room = HotelRoom::with('amenityList')->find($roomId);

            if (!$room) {
                Log::warning("Room not found: $roomId");
                return response()->json([
                    'success' => false,
                    'message' => 'Phòng không tồn tại'
                ], 404);
            }

            // Sửa 'amenities' thành 'amenityList'
            if (!$room->relationLoaded('amenityList')) {
                Log::warning("Amenity list relationship not loaded for room: $roomId");
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tải danh sách tiện ích'
                ], 500);
            }

            // Sửa $room->amenities thành $room->amenityList
            Log::info($room->amenityList);

            // Sửa $room->amenities thành $room->amenityList
            $amenities = $room->amenityList->map(function ($amenity) {
                Log::info($amenity);
                return [
                    'id' => $amenity->id,
                    'name' => $amenity->name,
                    'icon' => $amenity->icon ?? null,
                    'react_icon' => $amenity->react_icon ?? null,
                ];
            })->toArray();

            // ... (phần còn lại của phương thức giữ nguyên)

            return response()->json([
                'success' => true,
                'data' => $amenities
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error fetching amenities for room $roomId: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
