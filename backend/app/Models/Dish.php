<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'price',
        'description',
        'is_best_seller',
        'category',
        'image',
    ];

    /**
     * Relationship: Dish belongs to a Restaurant
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
    public function reviews()
{
    return $this->morphMany(Review::class, 'reviewable');
}

}