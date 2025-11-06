<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'react_icon'
    ];

    public function hotelRooms()
    {
        return $this->belongsToMany(
            HotelRoom::class,
            'amenity_hotel_rooms',
            'amenity_id',
            'hotel_room_id'
        );
    }
}

