<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'reviewable_id',
        'reviewable_type',
        'content',
        'rating',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'float',
        'is_approved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable()
    {
        return $this->morphTo();
    }
    public function images()
    {
        return $this->hasMany(ReviewImage::class);
    }
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }
    public function isLikedBy($user)
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
    public function interactions()
    {
        return $this->morphMany(Interaction::class, 'interactable');
    }
}
 