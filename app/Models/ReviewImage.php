<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    use HasFactory;
    protected $fillable = [
        'review_id',
        'image_path',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    protected $appends = ['full_image_url'];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    // Accessor để lấy URL đầy đủ của ảnh
    public function getFullImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }
        
        // Nếu đã là URL đầy đủ, trả về nguyên bản
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        
        // Nếu là đường dẫn tương đối, tạo URL đầy đủ
        return asset('storage/' . $this->image_path);
    }
} 
