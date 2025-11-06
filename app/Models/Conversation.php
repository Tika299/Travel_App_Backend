<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'last_message',
        'message_count',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_message' => 'datetime'
    ];

    public function messages()
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

