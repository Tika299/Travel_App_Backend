<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItineraryWeather extends Model
{
    protected $table = 'itinerary_weather'; // Tên bảng tương ứng trong DB

    protected $fillable = [
        'itinerary_id',
        'weather_data_id',
    ];

    public $timestamps = true; // Nếu bạn có cột timestamps trong bảng
}
