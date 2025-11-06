<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleDetail extends Model
{
    protected $fillable = [
        'schedule_id',
        'schedule_item_id',
        'type',
        'title',
        'content',
        'status',
        'cost',
        'currency',
        'due_date',
        'priority',
        'attachments',
        'tags',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'due_date' => 'datetime',
        'attachments' => 'array',
        'tags' => 'array',
    ];

    /**
     * Lịch trình chính
     */
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Sự kiện con
     */
    public function scheduleItem()
    {
        return $this->belongsTo(ScheduleItem::class);
    }

    /**
     * Scope để lấy theo loại
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope để lấy theo trạng thái
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope để lấy theo độ ưu tiên
     */
    public function scopeWithPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }
}
