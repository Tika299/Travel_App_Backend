<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\CategoryResource;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CategoryImport;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query();

        // Nếu có yêu cầu đếm số lượng món ăn
        if ($request->boolean('with_cuisines_count')) {
            $query->withCount('cuisines');
        }

        $categories = $query->get();

        return CategoryResource::collection($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name|max:255',
            'icon' => 'nullable|file|mimes:png,svg,jpg,jpeg,gif,webp|max:2048',
            'type' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->except('icon');
        if ($request->hasFile('icon')) {
            // Lấy đuôi file gốc
            $originalExtension = $request->file('icon')->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $originalExtension;
            $path = $request->file('icon')->storeAs('uploads/category_icons', $fileName, 'public');
            // Đảm bảo path luôn có format storage/uploads/
            $data['icon'] = 'storage/' . $path;
        } else {
            $data['icon'] = $request->input('icon');
        }

        $category = Category::create($data);

        return new CategoryResource($category);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return new CategoryResource($category);
    }

    public function update(Request $request, $id)
    {
        \Log::info('🔧 CategoryController.update called', [
            'id' => $id,
            'request_data' => $request->all(),
            'has_file' => $request->hasFile('icon'),
            'files' => $request->allFiles()
        ]);

        $category = Category::findOrFail($id);
        
        \Log::info('🔧 Found category', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'current_icon' => $category->icon
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|unique:categories,name,' . $category->id . '|max:255',
            'icon' => 'nullable|file|mimes:png,svg,jpg,jpeg,gif,webp|max:2048',
            'type' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            \Log::error('🔧 Validation failed', $validator->errors()->toArray());
            return response()->json($validator->errors(), 422);
        }

        $data = $request->except('icon');
        \Log::info('🔧 Data before icon processing', $data);

        if ($request->hasFile('icon')) {
            \Log::info('🔧 Processing new icon file', [
                'file_name' => $request->file('icon')->getClientOriginalName(),
                'file_size' => $request->file('icon')->getSize(),
                'file_type' => $request->file('icon')->getMimeType()
            ]);

            // Xóa ảnh cũ nếu có
            if ($category->icon && !str_starts_with($category->icon, 'http')) {
                // Xử lý cả 2 format: storage/uploads/ và uploads/
                $oldIconPath = $category->icon;
                if (str_starts_with($oldIconPath, 'storage/')) {
                    $oldPath = storage_path('app/public/' . $oldIconPath);
                } else {
                    $oldPath = storage_path('app/public/' . $oldIconPath);
                }
                \Log::info('🔧 Checking old icon path', ['old_path' => $oldPath, 'exists' => file_exists($oldPath)]);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                    \Log::info('🔧 Deleted old icon file');
                }
            }
            
            // Lấy đuôi file gốc
            $originalExtension = $request->file('icon')->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $originalExtension;
            $path = $request->file('icon')->storeAs('uploads/category_icons', $fileName, 'public');
            // Đảm bảo path luôn có format storage/uploads/
            $data['icon'] = 'storage/' . $path;
            \Log::info('🔧 Stored new icon', [
                'new_path' => $path,
                'original_extension' => $originalExtension,
                'file_name' => $fileName
            ]);
        } else {
            // Giữ nguyên ảnh cũ nếu không upload ảnh mới
            $data['icon'] = $category->icon;
            \Log::info('🔧 Keeping existing icon', ['existing_icon' => $category->icon]);
        }

        \Log::info('🔧 Final data to update', $data);
        $category->update($data);
        $updatedCategory = $category->fresh();
        \Log::info('🔧 Category updated successfully', [
            'updated_category' => $updatedCategory->toArray()
        ]);

        $response = new CategoryResource($updatedCategory);
        \Log::info('🔧 Response being sent to frontend', [
            'response_data' => $response->toArray($request)
        ]);

        return $response;
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        
        // Kiểm tra xem danh mục có món ăn nào không trước khi xóa
        if ($category->cuisines()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa danh mục này vì vẫn còn món ăn liên quan.'
            ], 409); // 409 Conflict
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Danh mục đã được xóa thành công'
        ]);
    }

    /**
     * Import categories từ file Excel
     */
    public function importCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $import = new CategoryImport();
            Excel::import($import, $request->file('file'));

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errors = $import->getErrors();

            DB::commit();

            $message = "Import thành công! Đã import {$importedCount} danh mục.";
            if ($skippedCount > 0) {
                $message .= " Bỏ qua {$skippedCount} dòng (trùng lặp hoặc lỗi).";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'imported' => $importedCount,
                    'skipped' => $skippedCount,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }
}
