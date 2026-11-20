<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Models\MessageConversation;
use App\Models\User;
use App\Services\Communication\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        protected MessageService $messageService
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = MessageConversation::with(['creator', 'latestMessage'])
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                  ->orWhereHas('messages', fn ($mq) => $mq->where('sender_id', $userId));
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => 'required|uuid|exists:users,id',
            'title' => 'nullable|string|max:150',
            'initial_message' => 'nullable|string',
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);
        $conversation = $this->messageService->getOrCreateDirectConversation(
            $request->user(),
            $recipient,
            $validated['title'] ?? null
        );

        if (!empty($validated['initial_message'])) {
            $this->messageService->sendMessage($conversation, $request->user(), $validated['initial_message']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversation started successfully.',
            'data' => $conversation->load('messages'),
        ], 201);
    }

    public function getMessages(string $id, Request $request): JsonResponse
    {
        $conversation = MessageConversation::findOrFail($id);
        $this->messageService->markAsRead($conversation, $request->user());

        $messages = $this->messageService->getMessages($conversation);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        $conversation = MessageConversation::findOrFail($id);

        $message = $this->messageService->sendMessage(
            $conversation,
            $request->user(),
            $validated['content'],
            $validated['attachments'] ?? null
        );

        $conversation->touch();

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully.',
            'data' => $message,
        ], 201);
    }
}

