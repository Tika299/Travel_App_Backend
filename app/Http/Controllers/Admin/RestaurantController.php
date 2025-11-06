<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index()
    {
         $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'rating' => 'nullable|numeric|between:0,5',
            'price_range' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        \Log::info('Dữ liệu hợp lệ:', $validated); // ghi log để kiểm tra

        try {
            // Lưu ảnh nếu có
          if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();

            // Đường dẫn tuyệt đối tới frontend/public/image
            $frontendPath = base_path('../frontend/public/image');

            // Tạo thư mục nếu chưa có
            if (!File::exists($frontendPath)) {
                File::makeDirectory($frontendPath, 0755, true);
            }

            // Lưu ảnh vào thư mục
            $image->move($frontendPath, $filename);

            // Lưu tên ảnh vào DB
            $validated['image'] = 'image/' . $filename;
        }

            // Tạo nhà hàng
            $restaurant = Restaurant::create($validated);

            return response()->json([
                'message' => 'Tạo nhà hàng thành công.',
                'data' => $restaurant
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra khi tạo nhà hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        return view('restaurants.create');
    }
    public function show($id): JsonResponse
{
    try {
        $restaurant = Restaurant::with(['reviews', 'reviews.user'])->findOrFail($id);

        $transformed = [
            'id' => $restaurant->id,
            'name' => $restaurant->name,
            'description' => $restaurant->description,
            'address' => $restaurant->address,
            'latitude' => $restaurant->latitude,
            'longitude' => $restaurant->longitude,
            'rating' => $restaurant->rating,
            'price_range' => $restaurant->price_range,
            'image' => $restaurant->image,
            'created_at' => $restaurant->created_at,
            'updated_at' => $restaurant->updated_at,
            'reviews' => $restaurant->reviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'user_id' => $review->user_id,
                    'reviewable_type' => $review->reviewable_type,
                    'reviewable_id' => $review->reviewable_id,
                    'content' => $review->content,
                    'rating' => $review->rating,
                    'is_approved' => $review->is_approved,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $transformed,
            'message' => 'Restaurant retrieved successfully',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Restaurant not found',
            'error' => $e->getMessage(),
        ], 404);
    }
}

    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'rating' => 'nullable|numeric|between:0,5',
            'price_range' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        \Log::info('Dữ liệu hợp lệ:', $validated); // ghi log để kiểm tra

        try {
            // Lưu ảnh nếu có
          if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();

            // Đường dẫn tuyệt đối tới frontend/public/image
            $frontendPath = base_path('../frontend/public/image');

            // Tạo thư mục nếu chưa có
            if (!File::exists($frontendPath)) {
                File::makeDirectory($frontendPath, 0755, true);
            }

            // Lưu ảnh vào thư mục
            $image->move($frontendPath, $filename);

            // Lưu tên ảnh vào DB
            $validated['image'] = 'image/' . $filename;
        }

            // Tạo nhà hàng
            $restaurant = Restaurant::create($validated);

            return response()->json([
                'message' => 'Tạo nhà hàng thành công.',
                'data' => $restaurant
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra khi tạo nhà hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function edit($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        return view('restaurants.edit', compact('restaurant'));
    }

    public function update(Request $request, $id): JsonResponse
{
    try {
        $restaurant = Restaurant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'rating' => 'nullable|numeric|min:0|max:5',
            'price_range' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $filename = time() . '_' . $image->getClientOriginalName();
            $path = base_path('../frontend/public/image');

            if (!\File::exists($path)) {
                \File::makeDirectory($path, 0755, true);
            }

            $image->move($path, $filename);
            $restaurant->image = 'image/' . $filename;
        }

        $restaurant->fill($validated);
        $restaurant->save();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant updated successfully',
            'data' => $restaurant,
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update restaurant',
            'error' => $e->getMessage(),
        ], 500);
    }
}


   public function destroy(Restaurant $restaurant): JsonResponse
{
    try {
        // Xoá ảnh nếu tồn tại
        if ($restaurant->image && \File::exists(public_path($restaurant->image))) {
            \File::delete(public_path($restaurant->image));
        }

        $restaurant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Restaurant deleted successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete restaurant',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}