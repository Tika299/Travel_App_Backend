<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomImages extends Model
{
    protected $fillable = [
        'room_id',
        'image_url',
    ];

    /**
     * Quan hệ: hình ảnh thuộc về 1 phòng khách sạn
     */
    public function hotelRoom()
    {
        return $this->belongsTo(HotelRoom::class);
    }
}
