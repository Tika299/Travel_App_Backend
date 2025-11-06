<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\File;


class RestaurantController extends Controller
{
    /**
     * Lấy tổng số nhà hàng
     */
    public function getCount()
    {
        try {
            $total = Restaurant::count();
            
            return response()->json([
                'success' => true,
                'total' => $total,
                'message' => 'Lấy tổng số nhà hàng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy tổng số nhà hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách nhà hàng
     */
    public function index(Request $request)
    {
        try {
            $query = Restaurant::query();

            // Lọc theo tìm kiếm
            if ($request->has('search') && $request->search) {
                $query->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
            }

            // Lọc theo nhà hàng tiêu biểu (lấy theo rating cao nhất)
            if ($request->has('featured') && $request->featured) {
                $query->orderBy('rating', 'desc');
            }

            // Sắp xếp
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Phân trang hoặc limit
            if ($request->has('limit')) {
                $limit = (int) $request->get('limit', 10);
                $restaurants = $query->limit($limit)->get();
                
                // Thêm thông tin total_reviews cho mỗi nhà hàng
                $restaurants->each(function ($restaurant) {
                    $restaurant->total_reviews = $restaurant->reviews()->count();
                });
                
                return response()->json([
                    'success' => true,
                    'data' => $restaurants,
                    'message' => 'Lấy danh sách nhà hàng thành công'
                ]);
            } else {
                // Phân trang
                $perPage = $request->get('per_page', 10);
                $restaurants = $query->paginate($perPage);

                // Thêm thông tin total_reviews cho mỗi nhà hàng
                $restaurants->getCollection()->each(function ($restaurant) {
                    $restaurant->total_reviews = $restaurant->reviews()->count();
                });

                return response()->json([
                    'success' => true,
                    'data' => $restaurants->items(),
                    'meta' => [
                        'current_page' => $restaurants->currentPage(),
                        'last_page' => $restaurants->lastPage(),
                        'per_page' => $restaurants->perPage(),
                        'total' => $restaurants->total()
                    ],
                    'message' => 'Lấy danh sách nhà hàng thành công'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách nhà hàng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy chi tiết nhà hàng
     */
    public function show($id)
    {
        try {
            $restaurant = Restaurant::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $restaurant,
                'message' => 'Lấy chi tiết nhà hàng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy nhà hàng hoặc có lỗi xảy ra'
            ], 404);
        }
    }

    public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'name' => [
            'required',
            'string',
            'max:255',
            'regex:/^[\p{L}\p{N}\s]+$/u' // ✅ Không ký tự đặc biệt, hỗ trợ Unicode
                ],
            'description' => 'nullable|string|max:1000',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'rating' => 'nullable|numeric|between:0,5',
            'price_range' => [
            'required',
            'string',
            'regex:/^(\d{1,3}(,\d{3})*)(\s*-\s*\d{1,3}(,\d{3})*)?\s*VND$/'
                ],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            
        ]);
        // Kiểm tra Kinh Độ Và Vĩ Độ 
        $errors = [];

        if ($validated['latitude'] < -90 || $validated['latitude'] > 90) {
            $errors['latitude'][] = 'Vĩ độ phải nằm trong khoảng -90 đến 90.';
        }

        if ($validated['longitude'] < -180 || $validated['longitude'] > 180) {
            $errors['longitude'][] = 'Kinh độ phải nằm trong khoảng -180 đến 180.';
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], 422);
        }

        // Kiểm Tra Giá Trị Hợp Lệ
        $allowedRanges = [
            '100,000 - 300,000 VND',
            '300,000 - 500,000 VND',
            '500,000 - 800,000 VND',
            '1,000,000 - 1,500,000 VND',
            '1,800,000 VND',
        ];

        if (!in_array($validated['price_range'], $allowedRanges)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'price_range' => ['Giá trị price_range không hợp lệ.'],
                ],
            ], 422);
        }

        // Kiểm tra Ký Tự Lặp
        function hasRepeatedCharacters($string, $limit = 3) {
            return preg_match('/(.)\1{' . $limit . ',}/u', $string); // có /u để hỗ trợ Unicode
        }

        if (hasRepeatedCharacters($validated['name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'name' => ['Tên không được chứa ký tự lặp quá nhiều lần.'],
                ],
            ], 422);
        }

        // Handle image if provided
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();

            // Path to frontend/public/image
            $frontendPath = base_path('../frontend/public/image');

            if (!File::exists($frontendPath)) {
                File::makeDirectory($frontendPath, 0755, true);
            }

            $image->move($frontendPath, $filename);
            $validated['image'] = 'image/' . $filename; // Relative path
        }

        // Create restaurant
        $restaurant = Restaurant::create($validated);

        return response()->json([
            'success' => true,
            'data' => $restaurant,
            'message' => 'Restaurant created successfully',
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create restaurant',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
        ], 500);
    }
}

    public function update(Request $request, $id)
    {
        try {
            $restaurant = Restaurant::findOrFail($id);

            $validated = $request->validate([
                'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L}\p{N}\s]+$/u' // ✅ Không ký tự đặc biệt, hỗ trợ Unicode
                ],
                'description' => 'nullable|string|max:1000',
                'address' => 'sometimes|required|string|max:500',
                'latitude' => 'sometimes|required|numeric|between:-90,90',
                'longitude' => 'sometimes|required|numeric|between:-180,180',
                'rating' => 'nullable|numeric|between:0,5',
                'price_range' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);
            function hasRepeatedCharacters($string, $limit = 3) {
            return preg_match('/(.)\1{' . $limit . ',}/u', $string); // có /u để hỗ trợ Unicode
        }

        if (hasRepeatedCharacters($validated['name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'name' => ['Tên không được chứa ký tự lặp quá nhiều lần.'],
                ],
            ], 422);
        }
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();

                // Path to frontend/public/image
                $frontendPath = base_path('../frontend/public/image');

                // Tạo thư mục nếu chưa có
                if (!File::exists($frontendPath)) {
                    File::makeDirectory($frontendPath, 0755, true);
                }

                // ✅ Xóa ảnh cũ nếu tồn tại
                if ($restaurant->image && File::exists($frontendPath . '/' . basename($restaurant->image))) {
                    File::delete($frontendPath . '/' . basename($restaurant->image));
                }

                // Lưu ảnh mới
                $image->move($frontendPath, $filename);
                $validated['image'] = 'image/' . $filename; // Relative path
            } else {
                $validated['image'] = $restaurant->image;
            }

            $restaurant->update($validated);

            return response()->json([
                'success' => true,
                'data' => $restaurant->fresh(),
                'message' => 'Restaurant updated successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update restaurant',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy($id)
{
    try {
        $restaurant = Restaurant::findOrFail($id);

        // Xóa ảnh từ frontend/public/image nếu tồn tại
        if ($restaurant->image) {
            $imagePath = base_path('../frontend/public/' . $restaurant->image);
            if (File::exists($imagePath)) {
                File::delete($imagePath);
            }
        }

        $restaurant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant deleted successfully'
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Restaurant not found'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete restaurant',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}
}
