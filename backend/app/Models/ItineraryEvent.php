<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItineraryEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'checkin_place_id',
        'hotel_id', 
        'restaurant_id',
        'title',
        'description',
        'type',
        'date',
        'start_time',
        'end_time',
        'duration',
        'cost',
        'location',
        'metadata',
        'order_index'
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'duration' => 'integer',
        'cost' => 'decimal:2',
        'metadata' => 'array'
    ];

    /**
     * Relationship với Schedule (event chính)
     */
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Relationship với CheckinPlace (địa điểm tham quan)
     */
    public function checkinPlace()
    {
        return $this->belongsTo(CheckinPlace::class);
    }

    /**
     * Relationship với Hotel (khách sạn)
     */
    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Relationship với Restaurant (nhà hàng)
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Lấy icon cho loại event
     */
    public function getIconAttribute()
    {
        return match($this->type) {
            'activity' => '🎯',
            'restaurant' => '🍽️',
            'hotel' => '🏨',
            'transport' => '🚗',
            'shopping' => '🛍️',
            'culture' => '🏛️',
            'nature' => '🌿',
            'entertainment' => '🎪',
            default => '📍'
        };
    }

    /**
     * Format thời gian hiển thị
     */
    public function getTimeDisplayAttribute()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
        } elseif ($this->start_time) {
            return $this->start_time->format('H:i');
        }
        return '';
    }

    /**
     * Format chi phí hiển thị
     */
    public function getCostDisplayAttribute()
    {
        return number_format($this->cost, 0, ',', '.') . ' VND';
    }

    /**
     * Scope để lấy events theo ngày
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope để sắp xếp theo thứ tự
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('date')->orderBy('order_index');
    }
}
