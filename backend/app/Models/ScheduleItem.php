<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleItem extends Model
{
    protected $fillable = [
        'schedule_id',
        'title',
        'start_time',
        'end_time',
        'location',
        'description',
        'cost',
        'weather',
        'all_day',
        'repeat',
        'order',
        'custom_fields',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'all_day' => 'boolean',
        'custom_fields' => 'array',
    ];

    /**
     * Lịch trình chính
     */
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Chi tiết của sự kiện
     */
    public function details()
    {
        return $this->hasMany(ScheduleDetail::class);
    }

    /**
     * Scope để lấy sự kiện theo ngày
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('start_time', $date);
    }

    /**
     * Scope để lấy sự kiện theo khoảng thời gian
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }
}
