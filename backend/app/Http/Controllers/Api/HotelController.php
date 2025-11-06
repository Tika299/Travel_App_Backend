<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HotelImport;
use Illuminate\Support\Facades\DB;

class HotelController extends Controller
{
    /**
     * Import hotels from an Excel file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function importHotels(Request $request): JsonResponse
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
            Excel::import(new HotelImport, $request->file('file'));
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Import dữ liệu khách sạn thành công!',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi import khách sạn: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import khách sạn: Vui lòng kiểm tra dữ liệu trong sheet Hotels (không có dòng trống, hình ảnh hợp lệ). Chi tiết lỗi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $hotels = Hotel::with(['rooms' => function ($query) {
            $query->orderBy('price_per_night', 'asc')->take(1);
        }])->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $hotels->items(),
            'current_page' => $hotels->currentPage(),
            'last_page' => $hotels->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('Hotel create request', $request->all());

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:hotels,name',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate mảng ảnh
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:15',
            'website' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->except(['images']);

            if ($request->hasFile('images')) {
                $imagePaths = [];
                foreach ($request->file('images') as $imageFile) {
                    $imageName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
                    $imageFile->move(public_path('storage/uploads/hotels'), $imageName);
                    $imagePaths[] = 'storage/uploads/hotels/' . $imageName; // Lưu đường dẫn tương đối
                }
                $data['images'] = $imagePaths; // Lưu dưới dạng JSON
            } else {
                $data['images'] = null;
            }

            $hotel = Hotel::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Tạo mới thành công',
                'data' => $hotel,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Lỗi tạo khách sạn: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server',
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $hotel = Hotel::with([
            'rooms' => function ($query) {
                $query->with('amenityList')->orderBy('price_per_night', 'asc');
            },
            'reviews' => function ($query) {
                $query->with('user')->orderBy('created_at', 'desc')->limit(10);
            }
        ])->find($id);

        if (!$hotel) {
            return response()->json([
                'success' => false,
                'message' => 'Khách sạn không tồn tại'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hotel' => $hotel,
                'rooms' => $hotel->rooms,
                'reviews' => $hotel->reviews,
            ],
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        Log::info('Hotel update request', $request->all());

        $hotel = Hotel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:hotels,name,' . $id,
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate mảng ảnh
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:15',
            'website' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->except(['images']);

            if ($request->hasFile('images')) {
                // Xóa ảnh cũ nếu có
                if ($hotel->images) {
                    $oldImages = json_decode($hotel->images, true);
                    if (is_array($oldImages)) {
                        foreach ($oldImages as $imagePath) {
                            if (Storage::disk('public')->exists($imagePath)) {
                                Storage::disk('public')->delete($imagePath);
                            }
                        }
                    }
                }

                // Lưu ảnh mới
                $imagePaths = [];
                foreach ($request->file('images') as $imageFile) {
                    $imageName = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
                    $imageFile->move(public_path('storage/uploads/hotels'), $imageName);
                    $imagePaths[] = 'storage/uploads/hotels/' . $imageName;
                }
                $data['images'] = $imagePaths;
            } else {
                // Nếu không có ảnh mới, giữ nguyên ảnh cũ
                $data['images'] = $hotel->images;
            }

            $hotel->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Hotel updated successfully!',
                'data' => $hotel->refresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi cập nhật khách sạn: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server',
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        $hotel = Hotel::findOrFail($id);
        if ($hotel->images) {
            $oldImages = json_decode($hotel->images, true);
            if (is_array($oldImages)) {
                foreach ($oldImages as $imagePath) {
                    if (Storage::disk('public')->exists($imagePath)) {
                        Storage::disk('public')->delete($imagePath);
                    }
                }
            }
        }
        $hotel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hotel deleted successfully!',
        ]);
    }

    public function getSuggested(): JsonResponse
    {
        $hotels = Hotel::limit(6)->get();

        return response()->json(['success' => true, 'data' => $hotels]);
    }

    public function getPopularHotels(): JsonResponse
    {
        $hotels = Hotel::with(['rooms' => function ($query) {
            $query->orderBy('price_per_night', 'asc')->take(1);
        }])
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $hotels,
        ]);
    }

    public function getRooms(int $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json(['message' => 'Không tìm thấy khách sạn'], 404);
        }

        return response()->json(['data' => $hotel->rooms]);
    }
}
