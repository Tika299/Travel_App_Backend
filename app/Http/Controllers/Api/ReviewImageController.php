<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReviewImageController extends Controller
{
    public function store(Request $request, $reviewId)
    {
        $review = Review::findOrFail($reviewId);

        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $uploaded = [];

        foreach ($request->file('images') as $file) {
            $path = $file->store('review_images', 'public');

            $image = $review->images()->create([
                'image_path' => $path
            ]);

            $uploaded[] = $image;
        }

        return response()->json([
            'message' => 'Images uploaded,',
            'data' => $uploaded,
        ]);
    }

    public function index($reviewId)
    {
        $review = Review::with('images')->findOrFail($reviewId);
        return response()->json($review->images);
    }

    public function destroy($id)
    {
        $image = ReviewImage::findOrFail($id);

        $relativePath = str_replace('storage/', '', $image->image_path);
        Storage::disk('public')->delete($relativePath);

        $image->delete();

        return response()->json(['message' => 'Image deleted.']);
    }
}
