<?php

namespace App\Policies;

use App\Models\FileUpload;
use App\Models\User;

class FileUploadPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can download or view the file.
     */
    public function download(User $user, FileUpload $file): bool
    {
        // 1. Cross-tenant isolation check: Strictly prevent cross-tenant IDOR
        if (!$this->belongsToActiveTenant($file)) {
            return false;
        }

        // 2. Public files within tenant are accessible
        if (!$file->is_private) {
            return true;
        }

        // 3. User who uploaded the file can access it
        if ($file->user_id === $user->id) {
            return true;
        }

        // 4. Administrators can access all tenant files
        if ($this->userHasRole($user, ['school_admin', 'school_owner', 'principal'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, FileUpload $file): bool
    {
        if (!$this->belongsToActiveTenant($file)) {
            return false;
        }

        if ($file->user_id === $user->id) {
            return true;
        }

        return $this->userHasRole($user, ['school_admin', 'school_owner']);
    }
}

