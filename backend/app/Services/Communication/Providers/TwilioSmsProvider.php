<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioSmsProvider implements SmsProviderInterface
{
    public function __construct(
        protected ?string $accountSid = null,
        protected ?string $authToken = null,
        protected ?string $fromNumber = null
    ) {
        $this->accountSid = $accountSid ?? config('services.twilio.sid');
        $this->authToken = $authToken ?? config('services.twilio.token');
        $this->fromNumber = $fromNumber ?? config('services.twilio.from');
    }

    public function send(string $to, string $message, array $options = []): array
    {
        if (empty($this->accountSid) || empty($this->authToken)) {
            // Fall back to log provider if credentials not configured
            Log::warning('Twilio credentials not configured; logging SMS instead.');
            return (new LogSmsProvider())->send($to, $message, $options);
        }

        try {
            $from = $options['from'] ?? $this->fromNumber;
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['sid'] ?? null,
                    'error' => null,
                    'raw_response' => $data,
                ];
            }

            return [
                'success' => false,
                'message_id' => null,
                'error' => $response->json('message') ?? 'Twilio API Request Failed',
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

