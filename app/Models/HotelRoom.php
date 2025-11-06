<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoom extends Model
{
    protected $fillable = [
        'hotel_id',
        'room_type',
        'price_per_night',
        'description',
        'room_area',
        'bed_type',
        'max_occupancy',
        'images'

    ];

    protected $casts = [
        'images' => 'array'
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    // Quan hệ với bảng trung gian amenity_hotel_room
    public function amenityRelations()
    {
        return $this->hasMany(AmenityHotelRoom::class, 'hotel_room_id');
    }

    // Quan hệ nhiều-nhiều với amenities thông qua bảng trung gian
    public function amenityList()
    {
        return $this->belongsToMany(
            \App\Models\Amenity::class,
            'amenity_hotel_rooms',
            'hotel_room_id',
            'amenity_id'
        );
    }

    public function images()
    {
        return $this->hasMany(RoomImages::class, 'room_id');
    }

    
}
