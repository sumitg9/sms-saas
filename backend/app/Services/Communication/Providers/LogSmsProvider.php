<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\SmsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message, array $options = []): array
    {
        Log::info("LogSmsProvider: Sending SMS to {$to}", [
            'to' => $to,
            'message' => $message,
            'options' => $options,
        ]);

        // Support simulated failure testing
        if (!empty($options['simulate_failure']) || str_ends_with($to, '999999')) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Simulated SMS Gateway Timeout (504)',
                'raw_response' => ['status' => 'failed', 'code' => 504],
            ];
        }

        $messageId = 'SMS-' . strtoupper(Str::random(12));

        return [
            'success' => true,
            'message_id' => $messageId,
            'error' => null,
            'raw_response' => [
                'status' => 'delivered',
                'provider' => 'log',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}

