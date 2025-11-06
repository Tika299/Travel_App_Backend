<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherData extends Model
{
    use HasFactory;

    protected $table = 'weather_data';

    protected $fillable = [
        'latitude',
        'longitude',
        'date',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Scope: Lọc theo ngày cụ thể.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope: Lọc theo toạ độ gần đúng.
     */
    public function scopeNearLocation($query, $lat, $lng, $precision = 0.01)
    {
        return $query->whereBetween('latitude', [$lat - $precision, $lat + $precision])
                     ->whereBetween('longitude', [$lng - $precision, $lng + $precision]);
    }
}
