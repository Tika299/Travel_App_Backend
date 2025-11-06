<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelLocations extends Model
{
    protected $fillable = [
        'hotel_id',
        'address',
        'city',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
