<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Auth;

class ConversationService
{
    /**
     * Tạo hoặc lấy conversation hiện tại
     */
    public function getOrCreateConversation($conversationId = null)
    {
        if ($conversationId) {
            $conversation = Conversation::where('conversation_id', $conversationId)->first();
            if ($conversation) {
                return $conversation;
            }
        }

        // Tạo conversation mới
        $conversation = Conversation::create([
            'user_id' => Auth::id() ?? null,
            'conversation_id' => $conversationId ?? time() . '_' . rand(1000, 9999),
            'title' => 'Cuộc hội thoại mới',
            'is_active' => true
        ]);

        return $conversation;
    }

    /**
     * Lưu tin nhắn vào conversation
     */
    public function saveMessage($conversationId, $type, $content, $metadata = [])
    {
        $conversation = $this->getOrCreateConversation($conversationId);
        
        // Lấy order_index tiếp theo
        $lastMessage = $conversation->messages()->orderBy('order_index', 'desc')->first();
        $orderIndex = $lastMessage ? $lastMessage->order_index + 1 : 1;

        // Lưu tin nhắn
        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
            'order_index' => $orderIndex
        ]);

        // Cập nhật conversation
        $conversation->update([
            'last_message' => now(),
            'message_count' => $conversation->messages()->count()
        ]);

        return $message;
    }

    /**
     * Lấy toàn bộ conversation history
     */
    public function getConversationHistory($conversationId)
    {
        $conversation = Conversation::where('conversation_id', $conversationId)->first();
        
        if (!$conversation) {
            return [];
        }

        return $conversation->messages()
            ->orderBy('order_index', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'type' => $message->type,
                    'content' => $message->content,
                    'timestamp' => $message->created_at,
                    'metadata' => $message->metadata
                ];
            })
            ->toArray();
    }

    /**
     * Lấy conversation history cho AI (chỉ tin nhắn gần đây)
     */
    public function getConversationHistoryForAI($conversationId, $limit = 10)
    {
        $conversation = Conversation::where('conversation_id', $conversationId)->first();
        
        if (!$conversation) {
            return [];
        }

        return $conversation->messages()
            ->orderBy('order_index', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(function ($message) {
                return [
                    'type' => $message->type,
                    'content' => $message->content,
                    'timestamp' => $message->created_at
                ];
            })
            ->toArray();
    }

    /**
     * Cập nhật title của conversation
     */
    public function updateConversationTitle($conversationId, $title)
    {
        $conversation = Conversation::where('conversation_id', $conversationId)->first();
        
        if ($conversation) {
            $conversation->update(['title' => $title]);
        }
    }
}
