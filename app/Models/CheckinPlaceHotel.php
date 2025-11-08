<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckInPlaceHotel extends Model
{
    protected $fillable = [
        'checkin_place_id',
        'hotel_id',
        'note',
        'distance_km',
    ];

    public function CheckInPlace()
    {
        return $this->belongsTo(CheckInPlace::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }
}
