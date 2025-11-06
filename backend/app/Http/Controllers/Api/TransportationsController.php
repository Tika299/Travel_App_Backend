<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transportation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TransportationsController extends Controller
{
    /**
     * Lấy danh sách các phương tiện được đề xuất.
     * Cột 'rating' đã bị xóa, nên tôi sẽ sắp xếp theo 'created_at' thay thế.
     */
    public function getSuggested(): JsonResponse
    {
        $transportations = Transportation::where('is_visible', true)
            ->orderByDesc('created_at') // Đã thay đổi từ 'rating' sang 'created_at'
            ->limit(8)
            ->get();

        if ($transportations->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transportations
        ]);
    }

    /**
     * Lấy tất cả các loại phương tiện.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Transportation::all()
        ]);
    }

    /**
     * Hiển thị chi tiết một loại phương tiện.
     */
    public function show($id): JsonResponse
    {
        $transportation = Transportation::find($id);

        if (!$transportation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transportation
        ]);
    }

    /**
     * Lưu một loại phương tiện mới.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:4096',
            'average_price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'features' => 'nullable|array',
            'is_visible' => 'boolean',
        ]);

        // Upload icon
        $data['icon'] = $request->file('icon')->store('transportations', 'public');

        // Upload banner nếu có
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('transportations', 'public');
        }

        // Tạo bản ghi mới
        $transportation = Transportation::create([
            'name' => $data['name'],
            'icon' => $data['icon'],
            'banner' => $data['banner'] ?? null,
            'average_price' => $data['average_price'] ?? null,
            'description' => $data['description'] ?? null,
            'tags' => $data['tags'] ?? [],
            'features' => $data['features'] ?? [],
            'is_visible' => $data['is_visible'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $transportation
        ]);
    }

    /**
     * Cập nhật một loại phương tiện.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $transportation = Transportation::find($id);

        if (!$transportation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy'
            ], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'sometimes|image|mimes:jpeg,png,jpg,svg|max:2048',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:4096',
            'average_price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'tags' => 'nullable|array',
            'features' => 'nullable|array',
            'is_visible' => 'boolean',
        ]);

        if ($request->hasFile('icon')) {
            // Xóa icon cũ nếu có
            if ($transportation->icon) {
                Storage::disk('public')->delete($transportation->icon);
            }
            $data['icon'] = $request->file('icon')->store('transportations', 'public');
        }

        if ($request->hasFile('banner')) {
            // Xóa banner cũ nếu có
            if ($transportation->banner) {
                Storage::disk('public')->delete($transportation->banner);
            }
            $data['banner'] = $request->file('banner')->store('transportations', 'public');
        }

        $transportation->update($data);

        return response()->json([
            'success' => true,
            'data' => $transportation
        ]);
    }

    /**
     * Xóa một loại phương tiện.
     */
    public function destroy($id): JsonResponse
    {
        $transportation = Transportation::find($id);

        if (!$transportation) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy'
            ], 404);
        }

        if ($transportation->icon) {
            Storage::disk('public')->delete($transportation->icon);
        }
        if ($transportation->banner) {
            Storage::disk('public')->delete($transportation->banner);
        }

        $transportation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa thành công'
        ]);
    }
}
