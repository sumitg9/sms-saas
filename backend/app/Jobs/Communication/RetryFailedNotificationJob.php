<?php

namespace App\Jobs\Communication;

use App\Models\NotificationLog;
use App\Services\Communication\CommunicationProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $logId
    ) {}

    public function handle(CommunicationProviderManager $providerManager): void
    {
        $log = NotificationLog::withoutGlobalScopes()->find($this->logId);

        if (!$log || $log->status === 'delivered') {
            return;
        }

        if ($log->retry_count >= $log->max_retries) {
            Log::warning("RetryFailedNotificationJob: Max retries ({$log->max_retries}) exceeded for log {$this->logId}");
            return;
        }

        $log->increment('retry_count');

        $result = match ($log->channel) {
            'sms' => $providerManager->sms()->send($log->recipient, $log->message_content),
            'whatsapp' => $providerManager->whatsapp()->send($log->recipient, $log->message_content),
            'email' => $providerManager->email()->send($log->recipient, 'Notification Retry', $log->message_content),
            'push' => $providerManager->push()->send($log->recipient, 'Notification Retry', $log->message_content),
            default => ['success' => false, 'error' => "Unsupported channel {$log->channel}"],
        };

        if ($result['success']) {
            $log->update([
                'status' => 'delivered',
                'error_message' => null,
                'gateway_response' => $result['raw_response'] ?? null,
            ]);
        } else {
            $log->update([
                'status' => 'failed',
                'error_message' => $result['error'] ?? 'Retry attempt failed',
                'gateway_response' => $result['raw_response'] ?? null,
            ]);
        }
    }
}

