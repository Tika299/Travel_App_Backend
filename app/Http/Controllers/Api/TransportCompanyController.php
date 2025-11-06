<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransportCompany;
use App\Models\Review;
use App\Models\ReviewImage; // Import model ReviewImage
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TransportCompaniesImport;
use Illuminate\Support\Facades\Auth;

class TransportCompanyController extends Controller
{
    /**
     * Lấy danh sách các hãng vận tải.
     * Bổ sung tính điểm đánh giá trung bình và số lượng đánh giá.
     */
    public function index(): JsonResponse
    {
        try {
            // Sử dụng withAvg và withCount để tối ưu hóa truy vấn và lấy dữ liệu rating
            $companies = TransportCompany::with('transportation')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->get();

            return response()->json(['success' => true, 'data' => $companies], 200);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy danh sách hãng vận tải: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi khi lấy danh sách', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lấy chi tiết một hãng vận tải theo ID.
     * Bổ sung tính điểm đánh giá trung bình và số lượng đánh giá.
     */
    public function show($id): JsonResponse
    {
        try {
            // Sử dụng withAvg và withCount để lấy rating và review count
            $company = TransportCompany::with('transportation')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->findOrFail($id);

            return response()->json(['success' => true, 'data' => $company], 200);
        } catch (Exception $e) {
            Log::error('Không tìm thấy hãng vận tải ID: ' . $id . ' - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Không tìm thấy hãng', 'error' => $e->getMessage()], 404);
        }
    }

    /**
     * Lấy danh sách đánh giá của một hãng vận tải theo ID.
     */
    public function getCompanyReviews($id): JsonResponse
    {
        try {
            // Tìm công ty vận tải theo ID
            $company = TransportCompany::findOrFail($id);
            
            // Giả sử có một quan hệ (relationship) giữa TransportCompany và Review
            $reviews = $company->reviews; 

            // Trả về dữ liệu đánh giá dưới dạng JSON
            return response()->json([
                'success' => true,
                'data' => $reviews,
            ], 200);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy đánh giá của hãng vận tải ID: ' . $id . ' - ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy đánh giá',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gửi đánh giá mới, bao gồm cả văn bản và ảnh.
     * Hàm này được cập nhật để xử lý file ảnh được gửi từ FormData của frontend.
     */
    public function submitReview(Request $request): JsonResponse
    {
        try {
            // Bắt đầu một transaction để đảm bảo dữ liệu nhất quán
            DB::beginTransaction();
            
            // 1. Validate the input data (text and files)
            $validator = Validator::make($request->all(), [
                'transport_company_id' => 'required|exists:transport_companies,id',
                'rating' => 'required|integer|min:1|max:5',
                'content' => 'nullable|string|max:1000',
                'images' => 'nullable|array|max:3', // Tối đa 3 ảnh
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB mỗi ảnh
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 2. Get the authenticated user
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Người dùng chưa đăng nhập.'], 401);
            }

            // 3. Create and save the new review
            $review = Review::create([
                'transport_company_id' => $request->input('transport_company_id'),
                'user_id' => $user->id,
                'rating' => $request->input('rating'),
                'content' => $request->input('content'),
                'is_approved' => true, // Assuming reviews are approved by default
            ]);

            $imagePaths = [];
            // 4. Handle and save the uploaded image files
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    // Store the image in the 'public/review_images' directory
                    $path = $image->store('review_images', 'public');
                    
                    // Save the image path to the database
                    ReviewImage::create([
                        'review_id' => $review->id,
                        'image_path' => $path,
                    ]);
                    
                    // Add the public URL to the response array
                    $imagePaths[] = asset('storage/' . $path);
                }
            }

            // Commit the transaction after all operations are successful
            DB::commit();

            // 5. Return a successful response with the newly created review's ID and image URLs
            return response()->json([
                'success' => true,
                'message' => 'Đánh giá đã được gửi thành công!',
                'data' => $review,
                'images' => $imagePaths
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi gửi đánh giá: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi server khi gửi đánh giá.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Tạo mới một hãng vận tải.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateCompany($request);
            if ($request->hasFile('logo')) {
                $imagePath = $request->file('logo')->store('logos', 'public');
                $validated['logo'] = '/storage/' . $imagePath;
            } else {
                $validated['logo'] = null;
            }
            $company = TransportCompany::create($validated);
            return response()->json(['success' => true, 'data' => $company], 201);
        } catch (ValidationException $e) {
            Log::error('Lỗi xác thực khi thêm hãng vận tải: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Lỗi khi thêm hãng vận tải: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi khi thêm hãng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cập nhật thông tin một hãng vận tải.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $company = TransportCompany::findOrFail($id);
            $validated = $this->validateCompany($request);
            if ($request->hasFile('logo')) {
                if ($company->logo && Storage::disk('public')->exists(str_replace('/storage/', '', $company->logo))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $company->logo));
                }
                $imagePath = $request->file('logo')->store('logos', 'public');
                $validated['logo'] = '/storage/' . $imagePath;
            } elseif (array_key_exists('logo', $request->all()) && $request->input('logo') === null) {
                if ($company->logo && Storage::disk('public')->exists(str_replace('/storage/', '', $company->logo))) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $company->logo));
                }
                $validated['logo'] = null;
            } else {
                unset($validated['logo']);
            }
            $company->update($validated);
            return response()->json(['success' => true, 'data' => $company], 200);
        } catch (ValidationException $e) {
            Log::error('Lỗi xác thực khi cập nhật hãng vận tải ID: ' . $id . ' - ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật hãng vận tải ID: ' . $id . ' - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi khi cập nhật hãng', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Xóa một hãng vận tải.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $company = TransportCompany::findOrFail($id);
            if ($company->logo && Storage::disk('public')->exists(str_replace('/storage/', '', $company->logo))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $company->logo));
            }
            $company->delete();
            return response()->json(['success' => true, 'message' => 'Đã xoá hãng thành công.'], 200);
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa hãng vận tải ID: ' . $id . ' - ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi khi xoá hãng', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Import dữ liệu công ty vận tải từ file Excel (.xlsx) hoặc CSV.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File không hợp lệ. Vui lòng chọn file Excel (.xlsx, .xls) hoặc CSV dưới 2MB.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();
            // Lấy sheet đầu tiên từ file và import
            Excel::import(new TransportCompaniesImport, $request->file('file'));
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Import dữ liệu công ty vận tải thành công!',
            ], 201);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            DB::rollBack();
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Lỗi tại dòng " . $failure->row() . ": " . implode(', ', $failure->errors());
            }
            Log::error('Lỗi validation khi import: ' . implode(' | ', $errors));

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi validation trong file dữ liệu. Vui lòng kiểm tra lại.',
                'errors' => $errors,
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi server khi import: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                // Trả về message lỗi chi tiết hơn từ exception
                'message' => 'Lỗi server khi import dữ liệu: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hàm dùng chung để validate dữ liệu từ request.
     */
    private function validateCompany(Request $request): array
    {
        $data = $request->all();
        if (isset($data['operating_hours']) && is_string($data['operating_hours'])) {
            $data['operating_hours'] = json_decode($data['operating_hours'], true);
        }
        if (isset($data['price_range']) && is_string($data['price_range'])) {
            $data['price_range'] = json_decode($data['price_range'], true);
        }
        if (isset($data['payment_methods']) && is_string($data['payment_methods'])) {
            $data['payment_methods'] = json_decode($data['payment_methods'], true);
        }
        if (isset($data['has_mobile_app'])) {
            $data['has_mobile_app'] = filter_var($data['has_mobile_app'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $validator = Validator::make($data, [
            'transportation_id' => 'required|integer|exists:transportations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'operating_hours' => 'nullable|array',
            'price_range' => 'nullable|array',
            'payment_methods' => 'nullable|array',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'has_mobile_app' => 'boolean',
            'status' => 'nullable|in:active,inactive,draft',
        ]);

        return $validator->validate();
    }
}
