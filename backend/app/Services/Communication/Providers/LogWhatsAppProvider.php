<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(
        string $to,
        string $message,
        ?string $templateName = null,
        array $templateParams = [],
        array $options = []
    ): array {
        Log::info("LogWhatsAppProvider: Sending WhatsApp message to {$to}", [
            'to' => $to,
            'message' => $message,
            'template' => $templateName,
            'params' => $templateParams,
            'options' => $options,
        ]);

        if (!empty($options['simulate_failure']) || str_ends_with($to, '999999')) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Simulated WhatsApp Gateway Error: Rate limit or invalid recipient',
                'raw_response' => ['status' => 'failed', 'code' => 429],
            ];
        }

        $messageId = 'WA-' . strtoupper(Str::random(14));

        return [
            'success' => true,
            'message_id' => $messageId,
            'error' => null,
            'raw_response' => [
                'status' => 'sent',
                'provider' => 'meta_log',
                'recipient' => $to,
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}

