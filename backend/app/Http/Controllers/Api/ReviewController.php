<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    /**
     * Store a newly created review in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Review::with(['user:id,name,avatar', 'images:id,review_id,image_path', 'reviewable:id,name']);

        if ($request->has('reviewable_type') && $request->has('reviewable_id')) {
            $query->where('reviewable_type', $request->reviewable_type)
                ->where('reviewable_id', $request->reviewable_id);
        }

        // Filter theo rating nếu có
        if ($request->has('rating') && $request->rating > 0) {
            $query->where('rating', (int) $request->rating);
        }

        $limit = $request->get('limit', 10);
        $page = $request->get('page', 1);
        $sort = $request->get('sort', 'latest'); 
        
        if ($sort === 'latest') {
            $query->latest();
        } elseif ($sort === 'oldest') {
            $query->oldest();
        } elseif ($sort === 'highest_rating') {
            $query->orderBy('rating', 'desc');
        } elseif ($sort === 'lowest_rating') {
            $query->orderBy('rating', 'asc');
        } elseif ($sort === 'best_and_latest') {
            // Sắp xếp theo rating cao nhất trước, sau đó theo thời gian mới nhất
            $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc');
        }

        // Tính toán thống kê tổng thể (không bị ảnh hưởng bởi filter)
        $cacheKey = "reviews_stats_{$request->reviewable_type}";
        if ($request->has('reviewable_id')) {
            $cacheKey .= "_{$request->reviewable_id}";
        }
        
        $stats = Cache::remember($cacheKey, 300, function () use ($request) { // Cache 5 phút
            $overallQuery = Review::where('reviewable_type', $request->reviewable_type);
            
            // Nếu có reviewable_id, thêm filter
            if ($request->has('reviewable_id')) {
                $overallQuery->where('reviewable_id', $request->reviewable_id);
            }
            
            $totalReviewsOverall = $overallQuery->count();
            $sumRating = $overallQuery->sum('rating');
            $averageRatingOverall = $totalReviewsOverall > 0 ? round($sumRating / $totalReviewsOverall, 1) : 0;
            
            // Debug log
            \Log::info("Rating calculation for {$request->reviewable_type} ID {$request->reviewable_id}: total={$totalReviewsOverall}, sum={$sumRating}, average={$averageRatingOverall}");
            
            return [
                'total' => $totalReviewsOverall,
                'average' => $averageRatingOverall
            ];
        });
        
        $totalReviewsOverall = $stats['total'];
        $averageRatingOverall = $stats['average'];

        // Tính distribution tổng thể 
        $distributionOverall = [];
        for ($i = 5; $i >= 1; $i--) {
            $countQuery = Review::where('reviewable_type', $request->reviewable_type);
            if ($request->has('reviewable_id')) {
                $countQuery->where('reviewable_id', $request->reviewable_id);
            }
            $count = $countQuery->where('rating', $i)->count();
            $percentage = $totalReviewsOverall > 0 ? round(($count / $totalReviewsOverall) * 100) : 0;
            
            // Debug log
            \Log::info("Rating distribution for {$i} stars: count={$count}, percentage={$percentage}, total={$totalReviewsOverall}");
            
            $distributionOverall[] = [
                'star' => $i,
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        // Pagination
        $offset = ($page - 1) * $limit;
        $totalFiltered = $query->count(); // Count TRƯỚC khi apply pagination
        $reviews = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $limit,
                'total' => $totalReviewsOverall, // Sử dụng tổng số reviews thực tế
                'last_page' => ceil($totalFiltered / $limit),
                'has_more' => ($page * $limit) < $totalFiltered,
                'average_rating' => $averageRatingOverall,
                'rating_distribution' => $distributionOverall
            ]
        ]);
    }

    public function getMyReviews(Request $request)
    {
        $query = Review::with('user', 'images')->where('user_id', Auth::id());

        if ($request->has('reviewable_type') && $request->has('reviewable_id')) {
            $query->where('reviewable_type', $request->reviewable_type)
                ->where('reviewable_id', $request->reviewable_id);
        }

        return response()->json($query->latest()->paginate(4));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'reviewable_type' => 'nullable|string',
            'reviewable_id' => 'nullable|integer',
            'content' => 'required|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để gửi đánh giá.'
            ], 401);
        }

        $review = Review::create([
            'user_id' => Auth::id(),
            'reviewable_type' => $request->reviewable_type,
            'reviewable_id' => $request->reviewable_id,
            'content' => $request->content,
            'rating' => $request->rating,
        ]);

        // Xử lý upload ảnh nếu có
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('review_images', 'public');
                
                $review->images()->create([
                    'image_path' => $path
                ]);
            }
        }

        // Clear cache khi có review mới
        $cacheKey = "reviews_stats_{$request->reviewable_type}";
        if ($request->reviewable_id) {
            $cacheKey .= "_{$request->reviewable_id}";
        }
        Cache::forget($cacheKey);
        
        return response()->json([
            'success' => true,
            'message' => 'Đánh giá của bạn đã được gửi thành công và đang chờ duyệt!',
            'data' => $review->load('images')

        ], 201);
    }

    public function show($id)
    {
        $review = Review::with(['user', 'images', 'reviewable'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'review' => $review,
                'reviewd_object' => [
                    'name' => $review->reviewable->name,
                    'latitude' => $review->reviewable->latitude,
                    'longitude' => $review->reviewable->longitude
                ]
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $review = Review::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $request->validate([
            'content' => 'required|string',
            'rating' => 'nullable|integer|min:1|max:5'
        ]);
        $review->update($request->only(['content', 'rating']));

        return response()->json([
            'message' => 'Review updated successfully.',
            'data' => $review,
        ]);
    }

    public function destroy($id)
    {
        $review = Review::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $review->delete();

        return response()->json([
            'message' => 'Review deleted.',
        ]);
    }
    public function getStats(Request $request, $id)
{
    $reviewableType = $request->get('type', 'App\\Models\\Restaurant');

    $baseQuery = Review::where('reviewable_type', $reviewableType)
        ->where('reviewable_id', $id)
        ->where('is_approved', true);

    // Clone query để dùng nhiều nơi
    $reviews = $baseQuery->get();

    $stats = [
        'total_reviews' => $reviews->count(),
        'average_rating' => round($reviews->avg('rating'), 1),
        'rating_breakdown' => [
            5 => $reviews->where('rating', 5)->count(),
            4 => $reviews->where('rating', 4)->count(),
            3 => $reviews->where('rating', 3)->count(),
            2 => $reviews->where('rating', 2)->count(),
            1 => $reviews->where('rating', 1)->count(),
        ],
        'reviews' => $reviews,
    ];

    return response()->json([
        'success' => true,
        'data' => $stats,
    ]);
}
}