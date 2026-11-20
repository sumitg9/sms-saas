<?php

namespace App\Services\Communication;

use App\Jobs\Communication\ProcessBulkNotificationJob;
use App\Jobs\Communication\RetryFailedNotificationJob;
use App\Jobs\Communication\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class NotificationService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected TemplateRenderer $templateRenderer
    ) {}

    /**
     * Send an individual notification or queue via template.
     */
    public function send(array $data): array
    {
        $tenantId = $this->tenantContext->getTenantId();
        $channel = $data['channel'] ?? 'in_app';
        $priority = $data['priority'] ?? 'normal';
        $recipient = $data['recipient'] ?? null;
        $userId = $data['user_id'] ?? null;

        // 1. Resolve content (either explicit or from template)
        $subject = $data['subject'] ?? null;
        $messageContent = $data['message'] ?? '';

        if (!empty($data['template_id']) || !empty($data['event_name'])) {
            $query = NotificationTemplate::where('is_active', true)
                ->where('channel', $channel);

            if (!empty($data['template_id'])) {
                $query->where('id', $data['template_id']);
            } else {
                $query->where('event_name', $data['event_name']);
            }

            $template = $query->first();

            if ($template) {
                $rendered = $this->templateRenderer->renderNotification(
                    $template->subject,
                    $template->body_template,
                    $data['variables'] ?? [],
                    $data['branding'] ?? []
                );
                $subject = $rendered['subject'] ?? $subject;
                $messageContent = $rendered['body'];
            }
        }

        if (empty($messageContent)) {
            throw new InvalidArgumentException('Notification message content cannot be empty.');
        }

        $idempotencyKey = $data['idempotency_key'] ?? ('NOTIF-' . hash('sha256', "{$tenantId}:{$channel}:{$recipient}:" . substr($messageContent, 0, 50)));

        $metadata = array_merge($data['metadata'] ?? [], [
            'priority' => $priority,
            'event_name' => $data['event_name'] ?? null,
            'action_url' => $data['action_url'] ?? null,
        ]);

        // If scheduled for later
        if (!empty($data['scheduled_at']) && Carbon::parse($data['scheduled_at'])->isFuture()) {
            SendNotificationJob::dispatch(
                $tenantId,
                $channel,
                $recipient ?? $userId,
                $messageContent,
                $subject,
                $metadata,
                $idempotencyKey,
                $userId
            )->delay(Carbon::parse($data['scheduled_at']));

            return [
                'status' => 'scheduled',
                'scheduled_at' => $data['scheduled_at'],
                'idempotency_key' => $idempotencyKey,
            ];
        }

        // Dispatch immediately to queue
        SendNotificationJob::dispatch(
            $tenantId,
            $channel,
            $recipient ?? $userId,
            $messageContent,
            $subject,
            $metadata,
            $idempotencyKey,
            $userId
        );

        return [
            'status' => 'queued',
            'channel' => $channel,
            'recipient' => $recipient ?? $userId,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * Dispatch bulk notification job asynchronously.
     */
    public function sendBulk(array $data): array
    {
        $tenantId = $this->tenantContext->getTenantId();
        $channels = (array) ($data['channels'] ?? ['in_app']);
        $targetAudience = $data['target_audience'] ?? 'all';
        $targetParams = $data['target_parameters'] ?? [];
        $subject = $data['subject'] ?? 'School Notification';
        $messageContent = $data['message'] ?? '';

        if (!empty($data['template_id']) || !empty($data['event_name'])) {
            $template = NotificationTemplate::where('is_active', true)
                ->where(function ($q) use ($data) {
                    if (!empty($data['template_id'])) {
                        $q->where('id', $data['template_id']);
                    } else {
                        $q->where('event_name', $data['event_name']);
                    }
                })->first();

            if ($template) {
                $rendered = $this->templateRenderer->renderNotification(
                    $template->subject,
                    $template->body_template,
                    $data['variables'] ?? [],
                    $data['branding'] ?? []
                );
                $subject = $rendered['subject'] ?? $subject;
                $messageContent = $rendered['body'];
            }
        }

        ProcessBulkNotificationJob::dispatch(
            $tenantId,
            $channels,
            $targetAudience,
            $targetParams,
            $messageContent,
            $subject,
            $data['metadata'] ?? []
        );

        return [
            'status' => 'bulk_queued',
            'target_audience' => $targetAudience,
            'channels' => $channels,
            'message' => 'Bulk notifications queued for asynchronous background processing.',
        ];
    }

    /**
     * Query notification delivery logs.
     */
    public function getLogs(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationLog::query()->orderBy('sent_at', 'desc');

        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['recipient'])) {
            $query->where('recipient', 'like', "%{$filters['recipient']}%");
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Retry a failed notification log.
     */
    public function retry(string $logId): bool
    {
        $log = NotificationLog::findOrFail($logId);

        if ($log->status === 'delivered') {
            return false;
        }

        RetryFailedNotificationJob::dispatch($log->id);

        return true;
    }
}

