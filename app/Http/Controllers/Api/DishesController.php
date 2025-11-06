<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class DishesController extends Controller
{
    public function getDishesByRestaurant($id)
    {
        $restaurant = Restaurant::find($id);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found'
            ], 404);
        }

        $dishes = Dish::where('restaurant_id', $id)->get();

        return response()->json([
            'success' => true,
            'data' => $dishes
        ]);
    }
}
