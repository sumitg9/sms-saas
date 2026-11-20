<?php

namespace App\Services\Lms;

use App\Models\LearningResource;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class LearningResourceService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Create study material or learning resource.
     */
    public function createResource(array $data, User $user): LearningResource
    {
        return LearningResource::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $this->tenantContext->getBranchId(),
            'class_id' => $data['class_id'],
            'section_id' => $data['section_id'] ?? null,
            'subject_id' => $data['subject_id'],
            'uploaded_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'resource_type' => $data['resource_type'] ?? 'document',
            'file_url' => $data['file_url'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'attachments' => $data['attachments'] ?? [],
            'is_published' => $data['is_published'] ?? true,
        ]);
    }

    /**
     * Update learning resource.
     */
    public function updateResource(string $id, array $data, User $user): LearningResource
    {
        $resource = LearningResource::findOrFail($id);

        $resource->update(array_intersect_key($data, array_flip([
            'title', 'description', 'resource_type', 'file_url', 'video_url', 'external_url', 'attachments', 'is_published', 'section_id'
        ])));

        return $resource->fresh(['schoolClass', 'section', 'subject', 'uploader']);
    }

    /**
     * Delete resource.
     */
    public function deleteResource(string $id, User $user): bool
    {
        $resource = LearningResource::findOrFail($id);
        return (bool) $resource->delete();
    }

    /**
     * List resources with filters.
     */
    public function listResources(array $filters = []): Collection
    {
        $query = LearningResource::with(['schoolClass', 'section', 'subject', 'uploader'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['section_id'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('section_id', $filters['section_id'])
                  ->orWhereNull('section_id');
            });
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        if (isset($filters['is_published'])) {
            $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }
}

