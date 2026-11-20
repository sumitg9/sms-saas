<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\PushProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPushProvider implements PushProviderInterface
{
    public function send(string $toTokenOrTopic, string $title, string $body, array $data = []): array
    {
        Log::info("LogPushProvider: Dispatching push notification to {$toTokenOrTopic}", [
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        if (!empty($data['simulate_failure']) || str_ends_with($toTokenOrTopic, 'invalid_token')) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'FCM Invalid Registration Token (400)',
                'raw_response' => ['status' => 'failed', 'error' => 'UNREGISTERED'],
            ];
        }

        return [
            'success' => true,
            'message_id' => 'FCM-' . strtoupper(Str::random(16)),
            'error' => null,
            'raw_response' => [
                'status' => 'sent',
                'target' => $toTokenOrTopic,
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}

