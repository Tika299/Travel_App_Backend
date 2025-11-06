<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Favourite;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FavouriteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $userId = Auth::id();
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $type = $request->query('type');

        $favouritesQuery = Favourite::with('favouritable')
            ->where('user_id', $userId);

        // Filter by favouritable_type
        if ($type) {
            $modelMap = [
                'hotel' => 'App\Models\Hotel',
                'checkin_place' => 'App\Models\CheckinPlace',
                'cuisine' => 'App\Models\Cuisine',
            ];

            if (array_key_exists($type, $modelMap)) {
                $favouritesQuery->where('favouritable_type', $modelMap[$type]);
            } else {
                return response()->json(['error' => 'Invalid filter type'], 400);
            }
        }

        $favourites = $favouritesQuery->latest()->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $favourites->items(),
            'total' => $favourites->total(),
            'current_page' => $favourites->currentPage(),
            'last_page' => $favourites->lastPage(),
        ]);
    }

    public function counts(Request $request)
    {
        $userId = Auth::id();

        $counts = [
            'all' => Favourite::where('user_id', $userId)->count(),
            'cuisine' => Favourite::where('user_id', $userId)
                ->where('favouritable_type', 'App\Models\Cuisine')
                ->count(),
            'checkin_place' => Favourite::where('user_id', $userId)
                ->where('favouritable_type', 'App\Models\CheckinPlace')
                ->count(),
            'hotel' => Favourite::where('user_id', $userId)
                ->where('favouritable_type', 'App\Models\Hotel')
                ->count(),
        ];

        return response()->json($counts);
    }

    public function store(Request $request)
    {
        try {
            Log::info('Request data:', $request->all());
            $validator = Validator::make($request->all(), [
                'favouritable_id' => 'required|integer',
                'favouritable_type' => 'required|string|in:App\\Models\\CheckinPlace,App\\Models\\Hotel,App\\Models\\Cuisine',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $userId = Auth::id();

            $existingFavourite = Favourite::where('user_id', $userId)
                ->where('favouritable_id', $request->favouritable_id)
                ->where('favouritable_type', $request->favouritable_type)
                ->first();

            if ($existingFavourite) {
                return response()->json(['message' => 'This item is already in favourites'], 409);
            }

            $favourite = Favourite::create([
                'user_id' => $userId,
                'favouritable_id' => $request->favouritable_id,
                'favouritable_type' => $request->favouritable_type,
            ]);

            return response()->json([
                'message' => 'Favourite added successfully',
                'favourite' => $favourite->load('favouritable'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to add favourite: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to add favourite',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $userId = Auth::id();

        $favourite = Favourite::with('favouritable')
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$favourite) {
            return response()->json(['message' => 'Favourite not found'], 404);
        }

        return response()->json($favourite);
    }

    public function update(Request $request, $id)
    {
        $userId = Auth::id();

        $favourite = Favourite::where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$favourite) {
            return response()->json(['message' => 'Favourite not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'favouritable_id' => 'sometimes|integer',
            'favouritable_type' => 'sometimes|string|in:App\\Models\\CheckinPlace,App\\Models\\Hotel,App\\Models\\Cuisine',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $favourite->update($request->only(['favouritable_id', 'favouritable_type']));

        return response()->json([
            'message' => 'Favourite updated successfully',
            'favourite' => $favourite->load('favouritable'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $userId = Auth::id();

        $favourite = Favourite::where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$favourite) {
            return response()->json(['message' => 'Favourite not found'], 404);
        }

        $favourite->delete();

        return response()->json(['message' => 'Favourite deleted successfully']);
    }

    public function checkStatus(Request $request)
    {
        $userId = Auth::id();
        
        $validator = Validator::make($request->all(), [
            'favouritable_id' => 'required|integer',
            'favouritable_type' => 'required|string|in:App\\Models\\CheckinPlace,App\\Models\\Hotel,App\\Models\\Cuisine',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $favourite = Favourite::where('user_id', $userId)
            ->where('favouritable_id', $request->favouritable_id)
            ->where('favouritable_type', $request->favouritable_type)
            ->first();

        return response()->json([
            'is_favourite' => !is_null($favourite),
            'favourite_id' => $favourite ? $favourite->id : null,
        ]);
    }
}