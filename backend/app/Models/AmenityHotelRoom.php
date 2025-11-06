<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmenityHotelRoom extends Model
{

    protected $table = 'amenity_hotel_rooms'; // Đây là bảng bạn đã tạo trong migration

    public function hotelRoom()
    {
        return $this->belongsTo(HotelRoom::class);
    }

    public function amenity()
    {
        return $this->belongsTo(Amenity::class);
    }

}
