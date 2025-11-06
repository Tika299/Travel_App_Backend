<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $fillable = [
        'user_id',
        'interactable_type',
        'interactable_id',
        'type',
        'content',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function interactable()
    {
        return $this->morphTo();
    }
}
