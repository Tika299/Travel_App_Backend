<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'type', // 'user' hoặc 'ai'
        'content',
        'metadata', // JSON để lưu thông tin bổ sung
        'order_index'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}

