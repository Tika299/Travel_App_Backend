<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckinPlaceHotel extends Model
{
    protected $fillable = [
        'checkin_place_id',
        'hotel_id',
        'note',
        'distance_km',
    ];

    public function checkinPlace()
    {
        return $this->belongsTo(CheckinPlace::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }
}
