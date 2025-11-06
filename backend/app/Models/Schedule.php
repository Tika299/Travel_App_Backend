<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'checkin_place_id',
        'hotel_id',
        'restaurant_id',
        'participants',
        'description',
        'budget',
        'status',
        'progress',
        'user_id',
        'travelers',
        'itinerary_data'
    ];

    protected $casts = [
        'itinerary_data' => 'array',
        'budget' => 'integer',
        'travelers' => 'integer'
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkinPlace()
    {
        return $this->belongsTo(CheckInPlace::class, 'checkin_place_id');
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id');
    }

    /**
     * Relationship với ItineraryEvent (các event con)
     */
    public function itineraryEvents()
    {
        return $this->hasMany(ItineraryEvent::class)->ordered();
    }

    /**
     * Lấy events theo ngày
     */
    public function getEventsByDate($date)
    {
        return $this->itineraryEvents()->forDate($date)->get();
    }

    /**
     * Tổng chi phí của lịch trình
     */
    public function getTotalCostAttribute()
    {
        return $this->itineraryEvents()->sum('cost');
    }

    /**
     * Số ngày của lịch trình
     */
    public function getDurationAttribute()
    {
        if ($this->start_date && $this->end_date) {
            $start = \Carbon\Carbon::parse($this->start_date);
            $end = \Carbon\Carbon::parse($this->end_date);
            return $start->diffInDays($end) + 1;
        }
        return 0;
    }
}
