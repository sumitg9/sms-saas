<?php

namespace App\Jobs\Communication;

use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $channels,
        public string $targetAudience, // all, class, section, role, individual
        public array $targetParameters, // class_id, section_id, role, user_ids
        public string $messageContent,
        public ?string $subject = null,
        public array $metadata = []
    ) {}

    public function handle(): void
    {
        $recipients = $this->resolveRecipients();

        foreach ($recipients as $recipient) {
            foreach ($this->channels as $channel) {
                $targetAddress = $this->resolveAddressForChannel($recipient, $channel);

                if (!$targetAddress && $channel !== 'in_app') {
                    continue;
                }

                $idempotencyKey = 'NOTIF-' . hash('sha256', "{$this->tenantId}:{$channel}:{$recipient->id}:" . substr($this->messageContent, 0, 50));

                SendNotificationJob::dispatch(
                    $this->tenantId,
                    $channel,
                    $targetAddress ?? $recipient->email ?? $recipient->id,
                    $this->messageContent,
                    $this->subject,
                    $this->metadata,
                    $idempotencyKey,
                    $recipient->id
                );
            }
        }
    }

    protected function resolveRecipients()
    {
        return match ($this->targetAudience) {
            'class' => $this->resolveClassRecipients(),
            'individual' => User::withoutGlobalScopes()
                ->whereIn('id', $this->targetParameters['user_ids'] ?? [])
                ->get(),
            'role' => User::withoutGlobalScopes()
                ->whereHas('roles', function ($q) {
                    $q->where('name', $this->targetParameters['role'] ?? '');
                })->get(),
            default => User::withoutGlobalScopes()
                ->whereHas('tenantMemberships', function ($q) {
                    $q->where('tenant_id', $this->tenantId)->where('is_active', true);
                })->get(),
        };
    }

    protected function resolveClassRecipients()
    {
        $classId = $this->targetParameters['class_id'] ?? null;
        $sectionId = $this->targetParameters['section_id'] ?? null;

        $enrollmentQuery = StudentEnrollment::withoutGlobalScopes()
            ->with(['student.user', 'student.guardians.user'])
            ->where('class_id', $classId)
            ->whereIn('status', ['active', 'enrolled']);

        if ($sectionId) {
            $enrollmentQuery->where('section_id', $sectionId);
        }

        $enrollments = $enrollmentQuery->get();
        $users = collect();

        foreach ($enrollments as $enr) {
            if ($enr->student?->user) {
                $users->push($enr->student->user);
            }
            if ($enr->student?->guardians) {
                foreach ($enr->student->guardians as $guardian) {
                    if ($guardian->user) {
                        $users->push($guardian->user);
                    }
                }
            }
        }

        return $users->unique('id');
    }

    protected function resolveAddressForChannel(User $user, string $channel): ?string
    {
        return match ($channel) {
            'email' => $user->email,
            'sms', 'whatsapp' => $user->phone ?? $user->mobile_number ?? null,
            'push' => $user->fcm_token ?? $user->id,
            'in_app' => $user->id,
            default => null,
        };
    }
}

