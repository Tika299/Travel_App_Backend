<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Itinerary extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'start_date',
        'end_date',
        'budget',
        'people_count',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'float',
        'people_count' => 'integer',
    ];

    /**
     * Lịch trình thuộc về người dùng
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
