<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVisitedPlace extends Model
{
    protected $fillable = [
        'user_id',
        'place_id',
        'place_type',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function place()
    {
        return $this->morphTo();
    }
}
