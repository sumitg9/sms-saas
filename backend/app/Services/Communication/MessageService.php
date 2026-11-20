<?php

namespace App\Services\Communication;

use App\Models\Message;
use App\Models\MessageConversation;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class MessageService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Get or create a direct conversation.
     */
    public function getOrCreateDirectConversation(User $userA, User $userB, ?string $title = null): MessageConversation
    {
        $tenantId = $this->tenantContext->getTenantId();
        $branch = $this->tenantContext->getBranch();

        // Check if existing conversation exists
        $existing = MessageConversation::where('tenant_id', $tenantId)
            ->where('conversation_type', 'direct')
            ->where(function ($q) use ($userA, $userB) {
                $q->where('created_by', $userA->id)
                  ->orWhere('created_by', $userB->id);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        return MessageConversation::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branch?->id,
            'title' => $title ?? "Direct Conversation: {$userA->first_name} & {$userB->first_name}",
            'conversation_type' => 'direct',
            'created_by' => $userA->id,
        ]);
    }

    /**
     * Send a message in a conversation.
     */
    public function sendMessage(MessageConversation $conversation, User $sender, string $content, ?array $attachments = null): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => $content,
            'attachment_paths' => $attachments,
            'is_read' => false,
        ]);
    }

    /**
     * List messages for conversation.
     */
    public function getMessages(MessageConversation $conversation): Collection
    {
        return Message::with('sender')
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Mark conversation messages as read.
     */
    public function markAsRead(MessageConversation $conversation, User $reader): int
    {
        return Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}

