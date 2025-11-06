<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\HotelRoom;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\AmenityResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HotelRoomImport;

class HotelRoomController extends Controller
{

    /**
     * Import phòng khách sạn từ file Excel (sheet Hotel_room).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function importHotelRooms(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ. Vui lòng chọn file Excel (.xlsx hoặc .xls) dưới 2MB.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();
            Excel::import(new HotelRoomImport, $request->file('file'));
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Import dữ liệu phòng khách sạn thành công!',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi import phòng khách sạn: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import phòng: Vui lòng kiểm tra dữ liệu trong sheet Hotel_room (hotel_id phải tồn tại trong bảng hotels, không có dòng trống, hình ảnh hợp lệ). Chi tiết lỗi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Đồng bộ hóa (thêm/xóa) các tiện ích cho một phòng khách sạn.
     *
     * @param Request $request
     * @param int $roomId
     * @return JsonResponse
     */
    public function syncAmenities(Request $request, $roomId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amenity_ids' => 'required|array',
            'amenity_ids.*' => 'integer|exists:amenities,id' // Đảm bảo mỗi id đều tồn tại
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room = HotelRoom::find($roomId);
            if (!$room) {
                return response()->json(['success' => false, 'message' => 'Phòng không tồn tại'], 404);
            }

            // Thay amenities() bằng amenityList() để khớp với model của bạn
            $room->amenityList()->sync($request->input('amenity_ids'));

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tiện ích cho phòng thành công'
            ]);
        } catch (\Exception $e) {
            Log::error("Error syncing amenities for room $roomId: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server'
            ], 500);
        }
    }
    // Lấy tất cả tiện ích của một phòng khách sạn
    // Sử dụng mối quan hệ đã định nghĩa trong model HotelRoom
    /**
     * Lấy tất cả tiện ích của một phòng khách sạn
     * @param int $roomId
     * @return JsonResponse
     */
    public function getAllRoomAmenities($roomId): JsonResponse
    {
        $room = HotelRoom::with('amenityList')->find($roomId);

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Phòng không tồn tại'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => AmenityResource::collection($room->amenityList),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // 1. Cập nhật validation để chấp nhận một MẢNG ảnh
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required|exists:hotels,id',
            'room_type' => 'required|string|max:255',
            'price_per_night' => 'required|string',
            'description' => 'nullable|string',
            'room_area' => 'nullable|string',
            'bed_type' => 'nullable|string|max:255',
            'max_occupancy' => 'nullable|integer|min:1',
            'images' => 'nullable|array', // Phải là một mảng
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Kiểm tra từng file trong mảng
            'amenity_ids' => 'nullable|json',
            // ... các rule khác
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->except(['images', 'amenity_ids']);

            // 2. "Biến tấu" logic xử lý file để lặp qua nhiều ảnh
            if ($request->hasFile('images')) {
                $imagePaths = []; // Khởi tạo một mảng rỗng để chứa các đường dẫn

                foreach ($request->file('images') as $imageFile) {
                    // Tạo tên file duy nhất cho mỗi ảnh
                    $imageName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();

                    // Di chuyển từng file vào thư mục public
                    $imageFile->move(public_path('storage/uploads/hotel_rooms'), $imageName);

                    // Thêm đường dẫn của file vừa xử lý vào mảng
                    $imagePaths[] = 'storage/uploads/hotel_rooms/' . $imageName;
                }

                // 3. Gán mảng các đường dẫn vào dữ liệu.
                // Do đã có $casts trong Model, Laravel sẽ tự động mã hóa mảng này thành JSON.
                $data['images'] = $imagePaths;
            }

            // Tạo phòng với dữ liệu đã chuẩn bị
            $room = HotelRoom::create($data);

            // Xử lý tiện ích (giữ nguyên)
            if ($request->has('amenity_ids')) {
                $amenityIds = json_decode($request->input('amenity_ids'), true);
                if (is_array($amenityIds) && !empty($amenityIds)) {
                    $room->amenityList()->sync($amenityIds);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Tạo phòng với nhiều ảnh thành công!',
                'data' => $room->load('amenityList'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Lỗi tạo phòng: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server',
            ], 500);
        }
    }

    /**
     * Lấy thông tin chi tiết của một phòng khách sạn.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $room = HotelRoom::with('amenityList')->find($id);

        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Phòng không tồn tại'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $room,
        ]);
    }

    /**
     * Cập nhật thông tin phòng khách sạn.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hotel_id' => 'required|exists:hotels,id',
            'room_type' => 'required|string|max:255',
            'price_per_night' => 'required|string',
            'description' => 'nullable|string',
            'room_area' => 'nullable|string',
            'bed_type' => 'nullable|string|max:255',
            'max_occupancy' => 'nullable|integer|min:1',
            'images' => 'nullable|array', // Chấp nhận mảng ảnh mới
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Kiểm tra từng file
            'amenity_ids' => 'nullable|json',
            'images_to_remove' => 'nullable|json', // Mảng chứa đường dẫn các ảnh cũ cần xóa
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $room = HotelRoom::findOrFail($id);
            $data = $request->except(['images', 'amenity_ids', 'images_to_remove', '_method']);

            $currentImages = $room->images ?? [];

            // 1. Xóa các ảnh cũ được yêu cầu
            if ($request->has('images_to_remove')) {
                $imagesToRemove = json_decode($request->input('images_to_remove'), true);
                foreach ($imagesToRemove as $imagePath) {
                    // Xóa file vật lý
                    if (Storage::disk('public')->exists(str_replace('storage/', '', $imagePath))) {
                        Storage::disk('public')->delete(str_replace('storage/', '', $imagePath));
                    }
                }
                // Cập nhật lại mảng ảnh hiện tại
                $currentImages = array_diff($currentImages, $imagesToRemove);
            }

            // 2. Thêm các ảnh mới (nếu có)
            if ($request->hasFile('images')) {
                $newImagePaths = [];
                foreach ($request->file('images') as $imageFile) {
                    $imageName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
                    $imageFile->move(public_path('storage/uploads/hotel_rooms'), $imageName);
                    $newImagePaths[] = 'storage/uploads/hotel_rooms/' . $imageName;
                }
                // Gộp ảnh cũ còn lại và ảnh mới
                $data['images'] = array_merge(array_values($currentImages), $newImagePaths);
            } else {
                // Nếu không có ảnh mới, chỉ cần giữ lại các ảnh cũ không bị xóa
                $data['images'] = array_values($currentImages);
            }


            // Cập nhật thông tin phòng
            $room->update($data);

            // Cập nhật tiện ích
            if ($request->has('amenity_ids')) {
                $amenityIds = json_decode($request->input('amenity_ids'), true);
                if (is_array($amenityIds)) {
                    $room->amenityList()->sync($amenityIds);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật phòng thành công!',
                'data' => $room->fresh()->load('amenityList'), // Lấy dữ liệu mới nhất
            ], 200);
        } catch (\Exception $e) {
            Log::error("Lỗi cập nhật phòng $id: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Xóa một phòng khách sạn.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        try {
            $room = HotelRoom::findOrFail($id);
            // Bạn có thể thêm logic xóa ảnh liên quan ở đây nếu cần
            $room->delete();
            return response()->json(['success' => true, 'message' => 'Xóa phòng thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa phòng'], 500);
        }
    }
}
