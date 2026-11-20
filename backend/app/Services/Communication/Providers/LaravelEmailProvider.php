<?php

namespace App\Services\Communication\Providers;

use App\Contracts\Communication\EmailProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LaravelEmailProvider implements EmailProviderInterface
{
    public function send(string $to, string $subject, string $body, array $options = []): array
    {
        Log::info("LaravelEmailProvider: Sending email to {$to}", [
            'subject' => $subject,
            'options' => $options,
        ]);

        if (!empty($options['simulate_failure']) || str_ends_with($to, 'fail@example.com')) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Simulated SMTP Connection Refused (550)',
                'raw_response' => ['status' => 'failed', 'code' => 550],
            ];
        }

        try {
            Mail::raw($body, function ($msg) use ($to, $subject, $options) {
                $msg->to($to)->subject($subject);

                if (!empty($options['from'])) {
                    $msg->from($options['from'], $options['from_name'] ?? null);
                }
            });

            return [
                'success' => true,
                'message_id' => 'EML-' . strtoupper(Str::random(12)),
                'error' => null,
                'raw_response' => ['status' => 'sent', 'timestamp' => now()->toISOString()],
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

