<?php

namespace App\Services\Communication;

use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AnnouncementService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = Announcement::with(['author', 'schoolClass', 'section'])
            ->withCount('acknowledgements')
            ->orderBy('published_at', 'desc');

        if (!empty($filters['target_audience'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('target_audience', $filters['target_audience'])
                  ->orWhere('target_audience', 'all');
            });
        }

        if (!empty($filters['class_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('class_id', $filters['class_id'])
                  ->orWhereNull('class_id');
            });
        }

        if (isset($filters['is_published'])) {
            $query->where('is_published', (bool) $filters['is_published']);
        }

        // Active only (published <= now and not expired)
        if (!empty($filters['active_only'])) {
            $now = Carbon::now();
            $query->where('is_published', true)
                ->where('published_at', '<=', $now)
                ->where(function ($q) use ($now) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', $now);
                });
        }

        return $query->get();
    }

    public function create(array $data, User $author): Announcement
    {
        $tenantId = $this->tenantContext->getTenantId();
        $branch = $this->tenantContext->getBranch();

        return Announcement::create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'] ?? $branch?->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'target_audience' => $data['target_audience'] ?? 'all',
            'class_id' => $data['class_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'published_at' => $data['published_at'] ?? Carbon::now(),
            'expires_at' => $data['expires_at'] ?? null,
            'author_id' => $author->id,
            'is_published' => $data['is_published'] ?? true,
            'attachments' => $data['attachments'] ?? null,
            'requires_acknowledgement' => $data['requires_acknowledgement'] ?? false,
        ]);
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        $announcement->update(array_filter([
            'title' => $data['title'] ?? $announcement->title,
            'content' => $data['content'] ?? $announcement->content,
            'target_audience' => $data['target_audience'] ?? $announcement->target_audience,
            'class_id' => array_key_exists('class_id', $data) ? $data['class_id'] : $announcement->class_id,
            'section_id' => array_key_exists('section_id', $data) ? $data['section_id'] : $announcement->section_id,
            'priority' => $data['priority'] ?? $announcement->priority,
            'published_at' => $data['published_at'] ?? $announcement->published_at,
            'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $announcement->expires_at,
            'is_published' => isset($data['is_published']) ? (bool) $data['is_published'] : $announcement->is_published,
            'attachments' => $data['attachments'] ?? $announcement->attachments,
            'requires_acknowledgement' => isset($data['requires_acknowledgement']) ? (bool) $data['requires_acknowledgement'] : $announcement->requires_acknowledgement,
        ], fn ($val) => $val !== null));

        return $announcement;
    }

    public function delete(Announcement $announcement): bool
    {
        return (bool) $announcement->delete();
    }

    /**
     * Parent or user acknowledges announcement.
     */
    public function acknowledge(Announcement $announcement, User $user): AnnouncementAcknowledgement
    {
        return AnnouncementAcknowledgement::firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            [
                'acknowledged_at' => Carbon::now(),
            ]
        );
    }
}

