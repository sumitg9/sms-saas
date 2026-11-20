<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(
        protected ?string $accessToken = null,
        protected ?string $phoneNumberId = null
    ) {
        $this->accessToken = $accessToken ?? config('services.whatsapp.token');
        $this->phoneNumberId = $phoneNumberId ?? config('services.whatsapp.phone_number_id');
    }

    public function send(
        string $to,
        string $message,
        ?string $templateName = null,
        array $templateParams = [],
        array $options = []
    ): array {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            Log::warning('Meta WhatsApp credentials not set; logging message instead.');
            return (new LogWhatsAppProvider())->send($to, $message, $templateName, $templateParams, $options);
        }

        try {
            $cleanedTo = preg_replace('/[^0-9]/', '', $to);

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $cleanedTo,
            ];

            if ($templateName) {
                $payload['type'] = 'template';
                $payload['template'] = [
                    'name' => $templateName,
                    'language' => ['code' => $options['language'] ?? 'en'],
                ];
                if (!empty($templateParams)) {
                    $payload['template']['components'] = [
                        [
                            'type' => 'body',
                            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $templateParams),
                        ],
                    ];
                }
            } else {
                $payload['type'] = 'text';
                $payload['text'] = ['body' => $message];
            }

            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'error' => null,
                    'raw_response' => $data,
                ];
            }

            return [
                'success' => false,
                'message_id' => null,
                'error' => $response->json('error.message') ?? 'Meta WhatsApp API request failed',
                'raw_response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'raw_response' => null,
            ];
        }
    }
}

