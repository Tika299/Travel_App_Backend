<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryItem extends Model
{
    protected $fillable = [
        'itinerary_id',
        'item_type',
        'item_id',
        'date',
        'start_time',
        'end_time',
        'order',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'order' => 'integer',
    ];

    public function itinerary()
    {
        return $this->belongsTo(Itinerary::class);
    }

    public function item()
    {
        return $this->morphTo();
    }
}
