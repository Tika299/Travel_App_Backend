<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckinPlace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\Log;

class CheckinPlaceController extends Controller
{
    /**
     * Lấy danh sách tất cả các địa điểm check-in.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            
            $places = CheckinPlace::with(['hotel', 'reviews'])->get();

            if ($places->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Không có địa điểm check-in nào được tìm thấy.',
                    'data'    => [],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách địa điểm check-in và reviews thành công.',
                'data'    => $places,
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy danh sách địa điểm check-in: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tải danh sách địa điểm. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }

    /**
     * Hiển thị thông tin chi tiết của một địa điểm check-in cụ thể.
     *
     * @param int $id ID của địa điểm check-in.
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            
            $place = CheckinPlace::with('hotel')->find($id);

            if (! $place) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa điểm check-in với ID đã cung cấp.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin địa điểm check-in thành công.',
                'data'    => $place,
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy chi tiết địa điểm check-in ID: ' . $id . ' - ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tải chi tiết địa điểm. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }

    /**
     * Lấy danh sách đánh giá cho một địa điểm check-in cụ thể.
     *
     * @param int $id ID của địa điểm check-in.
     * @return JsonResponse
     */
    public function getPlaceReviews(int $id): JsonResponse
    {
        try {
            $place = CheckinPlace::find($id);

            if (! $place) {
                return response()->json([
                    'success' => false,
                    'message' => 'Địa điểm check-in không tồn tại.',
                ], 404);
            }

            // Đã xóa logic lọc theo 'is_approved' để hiển thị tất cả reviews.
            // Bây giờ, phương thức này sẽ trả về tất cả các đánh giá liên quan đến địa điểm này.
            $reviews = $place->reviews()
                ->with(['user', 'reviewable'])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lấy đánh giá thành công.',
                'data'    => $reviews,
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy đánh giá cho địa điểm ID: ' . $id . ' - ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tải đánh giá. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }
    
    /**
     * Lưu trữ một địa điểm check-in mới.
     *
     * @param Request $request Dữ liệu yêu cầu.
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Giải mã 'operating_hours' và 'transport_options' nếu chúng được gửi dưới dạng chuỗi JSON
            $request->merge([
                'operating_hours'   => $request->has('operating_hours') ? json_decode($request->input('operating_hours'), true) : null,
                'transport_options' => $request->has('transport_options') ? json_decode($request->input('transport_options'), true) : null,
            ]);

            $validated = $this->validateRequest($request);

            /* Xử lý ảnh đại diện --------------------------------------------- */
            if ($request->hasFile('image')) {
                // Đã thay đổi đường dẫn lưu ảnh từ 'uploads/checkin' thành 'checkin'
                $validated['image'] = $request->file('image')->store('checkin', 'public');
            } else {
                $validated['image'] = null;
            }

            /* Xử lý ảnh phụ (gallery) ---------------------------------------- */
            $imagePaths = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $img) {
                    // Đã thay đổi đường dẫn lưu ảnh từ 'uploads/checkin' thành 'checkin'
                    $imagePaths[] = $img->store('checkin', 'public');
                }
            }
            $validated['images'] = $imagePaths;

            /* Xử lý các trường logic và giá trị mặc định --------------------- */
            $validated['operating_hours']   = $validated['operating_hours']   ?? ['all_day' => false, 'open' => null, 'close' => null];
            $validated['transport_options'] = $validated['transport_options'] ?? [];
            $validated['status']            = $validated['status']            ?? 'active';

            // Logic giá miễn phí
            $validated['is_free'] = (bool) ($validated['is_free'] ?? false);
            if (!isset($validated['price']) || $validated['price'] == 0) {
                $validated['is_free'] = true;
                $validated['price']   = null;
            }

            // Ép kiểu các trường số, đảm bảo null nếu rỗng
            foreach (['latitude', 'longitude', 'price'] as $floatField) {
                if (isset($validated[$floatField]) && $validated[$floatField] === '') {
                    $validated[$floatField] = null;
                }
            }

            $place = CheckinPlace::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Địa điểm check-in đã được tạo thành công.',
                'data'    => $place,
            ], 201);
        } catch (ValidationException $e) {
            Log::error('Lỗi xác thực khi tạo địa điểm: ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu gửi lên không hợp lệ. Vui lòng kiểm tra lại thông tin.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Lỗi khi tạo địa điểm: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tạo địa điểm. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin của một địa điểm check-in hiện có.
     *
     * @param Request $request Dữ liệu yêu cầu.
     * @param int $id ID của địa điểm check-in cần cập nhật.
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $place = CheckinPlace::find($id);
            if (! $place) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa điểm check-in để cập nhật.',
                ], 404);
            }

            // Giải mã 'operating_hours' và 'transport_options' nếu chúng được gửi dưới dạng chuỗi JSON
            $request->merge([
                'operating_hours'   => $request->has('operating_hours') ? json_decode($request->input('operating_hours'), true) : null,
                'transport_options' => $request->has('transport_options') ? json_decode($request->input('transport_options'), true) : null,
            ]);

            $validated = $this->validateRequest($request);

            /* Cập nhật ảnh đại diện ----------------------------------------- */
            if ($request->hasFile('image')) {
                // Xóa ảnh cũ nếu tồn tại
                if ($place->image && Storage::disk('public')->exists($place->image)) {
                    Storage::disk('public')->delete($place->image);
                }
                // Đã thay đổi đường dẫn lưu ảnh từ 'uploads/checkin' thành 'checkin'
                $validated['image'] = $request->file('image')->store('checkin', 'public');
            } else if ($request->input('image_removed') === 'true') { // Xử lý nếu ảnh đại diện bị xóa
                if ($place->image && Storage::disk('public')->exists($place->image)) {
                    Storage::disk('public')->delete($place->image);
                }
                $validated['image'] = null;
            } else {
                // Nếu không có file mới và không có yêu cầu xóa, giữ nguyên ảnh cũ
                unset($validated['image']);
            }

            /* Cập nhật ảnh phụ (gallery): giữ lại ảnh cũ + thêm ảnh mới ------ */
            // Lấy danh sách ảnh cũ được giữ lại từ request, chuyển đổi URL thành path lưu trữ
            $currentImages = array_map(
                fn($imgUrl) => str_replace(asset('storage/'), '', $imgUrl),
                $request->input('old_images', [])
            );

            // Thêm các ảnh mới được tải lên
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $img) {
                    // Đã thay đổi đường dẫn lưu ảnh từ 'uploads/checkin' thành 'checkin'
                    $currentImages[] = $img->store('checkin', 'public');
                }
            }

            /* Xóa các file ảnh phụ không còn được sử dụng */
            $imagesInDb = is_array($place->images) ? $place->images : (json_decode($place->images, true) ?? []);
            foreach ($imagesInDb as $dbImg) {
                // Kiểm tra xem ảnh cũ từ DB có còn trong danh sách ảnh hiện tại không
                // và có tồn tại trên storage không trước khi xóa
                if (! in_array($dbImg, $currentImages) && Storage::disk('public')->exists($dbImg)) {
                    Storage::disk('public')->delete($dbImg);
                }
            }
            $validated['images'] = $currentImages;

            /* Xử lý logic và ép kiểu tương tự như hàm store() ---------------- */
            $validated['operating_hours']   = $validated['operating_hours']   ?? ['all_day' => false, 'open' => null, 'close' => null];
            $validated['transport_options'] = $validated['transport_options'] ?? [];
            $validated['status']            = $validated['status']            ?? $place->status;

            // Logic giá miễn phí
            $validated['is_free'] = (bool) ($validated['is_free'] ?? false);
            if (!isset($validated['price']) || $validated['price'] == 0) {
                $validated['is_free'] = true;
                $validated['price']   = null;
            }

            // Ép kiểu các trường số, đảm bảo null nếu rỗng
            foreach (['latitude', 'longitude', 'price'] as $floatField) {
                if (isset($validated[$floatField]) && $validated[$floatField] === '') {
                    $validated[$floatField] = null;
                }
            }

            // Loại bỏ các trường 'rating', 'checkin_count', 'review_count' khỏi dữ liệu cập nhật
            // vì chúng không còn tồn tại trong model và schema
            unset($validated['rating']);
            unset($validated['checkin_count']);
            unset($validated['review_count']);
            // Đảm bảo 'distance' cũng bị loại bỏ nếu có (nếu bạn vẫn truyền nó qua request)
            unset($validated['distance']);


            $place->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Địa điểm check-in đã được cập nhật thành công.',
                'data'    => $place,
            ]);
        } catch (ValidationException $e) {
            Log::error('Lỗi xác thực khi cập nhật địa điểm ' . $id . ': ' . $e->getMessage(), ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu gửi lên không hợp lệ. Vui lòng kiểm tra lại thông tin.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Lỗi khi cập nhật địa điểm ' . $id . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi cập nhật địa điểm. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }

    /**
     * Xóa một địa điểm check-in.
     *
     * @param int $id ID của địa điểm check-in cần xóa.
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $place = CheckinPlace::find($id);
            if (! $place) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa điểm check-in để xóa.',
                ], 404);
            }

            /* Xóa ảnh đại diện liên quan */
            if ($place->image && Storage::disk('public')->exists($place->image)) {
                Storage::disk('public')->delete($place->image);
            }

            /* Xóa tất cả ảnh phụ (gallery) liên quan */
            $auxImages = is_array($place->images) ? $place->images : (json_decode($place->images, true) ?? []);
            foreach ($auxImages as $img) {
                if (Storage::disk('public')->exists($img)) {
                    Storage::disk('public')->delete($img);
                }
            }

            $place->delete();

            return response()->json([
                'success' => true,
                'message' => 'Địa điểm check-in và toàn bộ ảnh liên quan đã được xóa thành công.',
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi xóa địa điểm ID: ' . $id . ' - ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi xóa địa điểm. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }

    /**
     * Lấy các số liệu thống kê liên quan đến địa điểm check-in.
     *
     * @return JsonResponse
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $data = [
                'totalCheckinPlaces'      => CheckinPlace::count(),
                
                'activeCheckinPlaces' => CheckinPlace::where('status', 'active')->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Lấy thống kê thành công.',
                'data'    => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy thống kê: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy số liệu thống kê. Vui lòng thử lại sau.',
                'error'   => env('APP_DEBUG') ? $e->getMessage() : 'Lỗi nội bộ máy chủ.',
            ], 500);
        }
    }

    
    public function getPopularPlaces(): JsonResponse
    {
        try {
            Log::info('Bắt đầu lấy danh sách địa điểm check-in đề xuất');

            
            $places = CheckinPlace::latest()->limit(8)->get();
            

            Log::info('Lấy danh sách địa điểm thành công', [
                'count' => $places->count(),
                'first_id' => $places->first() ? $places->first()->id : null
            ]);

            return response()->json([
                'success' => true,
                'data' => $places,
                'metadata' => [
                    'total' => $places->count(),
                    'timestamp' => now()->toDateTimeString()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Lỗi khi lấy địa điểm phổ biến: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi lấy địa điểm phổ biến.',
                'error_details' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'type' => get_class($e)
                ]
            ], 500);
        }
    }

    /**
     * Hàm private để xác thực dữ liệu yêu cầu.
     *
     * @param Request $request Dữ liệu yêu cầu.
     * @return array Dữ liệu đã được xác thực.
     * @throws ValidationException
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'address'               => 'nullable|string|max:255',
            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
            'image'                 => 'nullable|image|max:2048', // Ảnh đại diện, tối đa 2MB
            
            'location_id'           => 'nullable|integer|exists:locations,id',
            'price'                 => 'nullable|numeric|min:0',
            'is_free'               => 'nullable|boolean',
            'operating_hours'       => 'nullable|array',
            'operating_hours.all_day' => 'nullable|boolean',
            'operating_hours.open'  => 'nullable|date_format:H:i',
            'operating_hours.close' => 'nullable|date_format:H:i|after:operating_hours.open',
            
            'images'                => 'nullable|array', // Mảng các ảnh phụ
            'images.*'              => 'image|max:2048', // Mỗi ảnh phụ tối đa 2MB
            'old_images'            => 'nullable|array', // Mảng các URL ảnh cũ được giữ lại
            'old_images.*'          => 'nullable|string',
            'region'                => 'nullable|string|max:100',
            'caption'               => 'nullable|string|max:255', // Chú thích
            'transport_options'     => 'nullable|array',
            'transport_options.*'   => 'nullable|string|max:255',
            'status'                => 'nullable|string|in:active,inactive,draft', // Trạng thái của địa điểm
            'image_removed'         => 'nullable|boolean', // Cờ báo hiệu ảnh đại diện đã bị xóa
            
        ]);
    }
}
