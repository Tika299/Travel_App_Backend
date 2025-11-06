<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;

class Cuisine extends Model
{
    use HasFactory;

    // Tên bảng (nếu khác với tên model)
    protected $table = 'cuisine';

    // Các trường có thể fill (mass assignment)
    protected $fillable = [
        'categories_id',
        'name',
        'image',
        'short_description',
        'detailed_description',
        'region',
        'price',
        'address',
        'serving_time',
        'delivery',
        'operating_hours',
        'suitable_for',
        'status'
    ];

    // Các trường được cast (chuyển đổi kiểu dữ liệu)
    protected $casts = [
        'delivery' => 'boolean',
        'price' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationship với bảng categories
    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }
    // Relationship với bảng restaurants
    public function restaurants()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    // Relationship với bảng images 
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // Scope để lọc theo miền
    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    // Scope để lọc theo danh mục
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('categories_id', $categoryId);
    }

    // Scope để lọc món ăn available
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // Mutator để format giá tiền
    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = (int) $value;
    }

    // Accessor để format giá tiền hiển thị
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 0, ',', '.') . 'đ';
    }
}