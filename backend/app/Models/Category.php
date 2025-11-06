<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'type'
    ];

    // Relationship với bảng cuisine
    public function cuisines()
    {
        return $this->hasMany(Cuisine::class, 'categories_id');
    }
}