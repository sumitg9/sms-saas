<?php

namespace App\Jobs\Communication;

use App\Models\CommunicationPreference;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Services\Communication\CommunicationProviderManager;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public string $tenantId,
        public string $channel,
        public string $recipient,
        public string $messageContent,
        public ?string $subject = null,
        public array $metadata = [],
        public ?string $idempotencyKey = null,
        public ?string $userId = null
    ) {}

    public function handle(CommunicationProviderManager $providerManager): void
    {
        // 1. Duplicate Delivery Prevention via Idempotency Key
        if ($this->idempotencyKey) {
            $existing = NotificationLog::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('idempotency_key', $this->idempotencyKey)
                ->whereIn('status', ['sent', 'delivered'])
                ->first();

            if ($existing) {
                Log::info("SendNotificationJob: Skipped duplicate notification [Key: {$this->idempotencyKey}]");
                return;
            }
        }

        // 2. Communication Preference & Consent Verification
        $isEmergency = ($this->metadata['priority'] ?? 'normal') === 'emergency';

        if ($this->userId && !$isEmergency) {
            $preference = CommunicationPreference::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->userId)
                ->first();

            if ($preference) {
                $channelKey = "{$this->channel}_enabled";
                if (isset($preference->$channelKey) && !$preference->$channelKey) {
                    NotificationLog::withoutGlobalScopes()->create([
                        'tenant_id' => $this->tenantId,
                        'channel' => $this->channel,
                        'recipient' => $this->recipient,
                        'idempotency_key' => $this->idempotencyKey,
                        'message_content' => $this->messageContent,
                        'status' => 'suppressed_opt_out',
                        'error_message' => "Recipient opted out of {$this->channel} notifications.",
                        'sent_at' => now(),
                    ]);
                    return;
                }

                // Check DND (Do Not Disturb) window if specified
                if (!empty($preference->dnd_start_time) && !empty($preference->dnd_end_time)) {
                    $nowTime = Carbon::now()->format('H:i');
                    $start = $preference->dnd_start_time;
                    $end = $preference->dnd_end_time;

                    $inDnd = ($start < $end)
                        ? ($nowTime >= $start && $nowTime <= $end)
                        : ($nowTime >= $start || $nowTime <= $end);

                    if ($inDnd) {
                        NotificationLog::withoutGlobalScopes()->create([
                            'tenant_id' => $this->tenantId,
                            'channel' => $this->channel,
                            'recipient' => $this->recipient,
                            'idempotency_key' => $this->idempotencyKey,
                            'message_content' => $this->messageContent,
                            'status' => 'suppressed_dnd',
                            'error_message' => "Message suppressed: recipient within DND hours ({$start}-{$end}).",
                            'sent_at' => now(),
                        ]);
                        return;
                    }
                }
            }
        }

        // 3. Channel Dispatch via Provider Abstraction
        $result = match ($this->channel) {
            'sms' => $providerManager->sms()->send(
                $this->recipient,
                $this->messageContent,
                $this->metadata
            ),
            'whatsapp' => $providerManager->whatsapp()->send(
                $this->recipient,
                $this->messageContent,
                $this->metadata['template_name'] ?? null,
                $this->metadata['template_params'] ?? [],
                $this->metadata
            ),
            'email' => $providerManager->email()->send(
                $this->recipient,
                $this->subject ?? 'School Notification',
                $this->messageContent,
                $this->metadata
            ),
            'push' => $providerManager->push()->send(
                $this->recipient,
                $this->subject ?? 'School Notification',
                $this->messageContent,
                $this->metadata
            ),
            'in_app' => $this->storeInAppNotification(),
            default => [
                'success' => false,
                'message_id' => null,
                'error' => "Unsupported communication channel: {$this->channel}",
                'raw_response' => null,
            ],
        };

        // 4. Record Audit Log in notification_logs
        NotificationLog::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'idempotency_key' => $this->idempotencyKey,
            'message_content' => $this->messageContent,
            'status' => $result['success'] ? 'delivered' : 'failed',
            'error_message' => $result['error'] ?? null,
            'gateway_response' => $result['raw_response'] ?? null,
            'sent_at' => now(),
            'retry_count' => 0,
            'max_retries' => 3,
        ]);
    }

    protected function storeInAppNotification(): array
    {
        if (!$this->userId) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Cannot create in-app notification without valid user_id.',
                'raw_response' => null,
            ];
        }

        $notification = Notification::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'title' => $this->subject ?? 'New Notification',
            'body' => $this->messageContent,
            'channel' => 'in_app',
            'action_url' => $this->metadata['action_url'] ?? null,
            'metadata' => $this->metadata,
        ]);

        return [
            'success' => true,
            'message_id' => $notification->id,
            'error' => null,
            'raw_response' => ['status' => 'stored'],
        ];
    }
}

