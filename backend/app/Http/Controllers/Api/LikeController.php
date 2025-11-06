<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Review;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request, $reviewId)
    {
        $user = $request->user();
        $review = Review::findOrFail($reviewId);

        $like = Like::where([
            'user_id' => $user->id,
            'likeable_id' => $review->id,
            'likeable_type' => Review::class,
        ])->first();

        if ($like) {
            $like->delete();
            return response()->json(['liked' => false, 'message' => 'Unliked']);
        } else {
            Like::create([
                'user_id' => $user->id,
                'likeable_id' => $review->id,
                'likeable_type' => Review::class,
            ]);
            return response()->json(['liked' => true, 'message' => 'Liked']);
        }
    }
    public function count(Request $request, $reviewId)
    {
        $review = Review::findOrFail($reviewId);
        $count = $review->likes()->count();

        $liked = false;
        if ($request->user()) {
            $liked = $review->likes()
                ->where('user_id', $request->user()->id)
                ->exists();
        }

        return response()->json([
            'like_count' => $count,
            'liked_by_user' => $liked,
        ]);
    }
}
