<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRole;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait HasTenantRoles
{
    /**
     * Check if user has a specific role or any of the given roles within the active tenant.
     */
    public function hasRole(string|array $roles, ?string $tenantId = null): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        $roles = (array) $roles;
        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        return $this->userRoles()
            ->where(function ($query) use ($tenantId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                }
            })
            ->whereHas('role', function ($query) use ($roles) {
                $query->whereIn('name', $roles);
            })
            ->exists();
    }

    /**
     * Check if user has all of the given roles.
     */
    public function hasAllRoles(array $roles, ?string $tenantId = null): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        foreach ($roles as $role) {
            if (!$this->hasRole($role, $tenantId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(string|array $roles, ?string $tenantId = null): bool
    {
        return $this->hasRole($roles, $tenantId);
    }

    /**
     * Check if user has a specific permission within the active tenant.
     */
    public function hasPermissionTo(string $permission, ?string $tenantId = null): bool
    {
        if ($this->is_platform_admin) {
            return true;
        }

        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        return $this->userRoles()
            ->where(function ($query) use ($tenantId) {
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                }
            })
            ->whereHas('role.permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
    }

    /**
     * Assign a role to the user within a tenant and optional branch.
     */
    public function assignRole(Role|string $role, ?string $tenantId = null, ?string $branchId = null): UserRole
    {
        $roleModel = is_string($role) ? Role::where('name', $role)->firstOrFail() : $role;
        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        // Platform roles require tenantId = null; School roles require valid tenantId
        if (in_array($roleModel->name, ['platform_admin', 'platform_support', 'super_admin'])) {
            $currentUser = auth()->user();
            if ($currentUser && !$currentUser->is_platform_admin) {
                throw new AccessDeniedHttpException('Privilege escalation denied: Only Super Admins can assign platform roles.');
            }
        } else {
            if (empty($tenantId)) {
                throw new \InvalidArgumentException("Cannot assign school role '{$roleModel->name}' without a tenant context.");
            }
        }

        return UserRole::firstOrCreate([
            'user_id' => $this->id,
            'role_id' => $roleModel->id,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
        ]);
    }

    /**
     * Remove a role from the user within a tenant.
     */
    public function removeRole(Role|string $role, ?string $tenantId = null, ?string $branchId = null): bool
    {
        $roleModel = is_string($role) ? Role::where('name', $role)->first() : $role;
        if (!$roleModel) {
            return false;
        }

        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        $query = $this->userRoles()
            ->where('role_id', $roleModel->id);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return (bool) $query->delete();
    }

    /**
     * Sync roles for this user in the specified tenant context.
     */
    public function syncRoles(array $roles, ?string $tenantId = null, ?string $branchId = null): self
    {
        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        // Delete existing tenant roles
        $this->userRoles()
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->delete();

        foreach ($roles as $role) {
            $this->assignRole($role, $tenantId, $branchId);
        }

        return $this;
    }

    /**
     * Get all permission names the user has for the active tenant.
     */
    public function getPermissionsForTenant(?string $tenantId = null): Collection
    {
        if ($this->is_platform_admin) {
            return Permission::pluck('name');
        }

        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        return Permission::whereHas('roles.users', function ($q) use ($tenantId) {
            $q->where('users.id', $this->id)
                ->where(function ($sub) use ($tenantId) {
                    if ($tenantId) {
                        $sub->where('user_roles.tenant_id', $tenantId)->orWhereNull('user_roles.tenant_id');
                    }
                });
        })->pluck('name')->unique()->values();
    }

    /**
     * Get all role names the user has for the active tenant.
     */
    public function getRolesForTenant(?string $tenantId = null): Collection
    {
        if ($this->is_platform_admin) {
            return Role::pluck('name');
        }

        $tenantId = $tenantId ?: app(TenantContext::class)->getTenantId();

        return Role::whereHas('users', function ($q) use ($tenantId) {
            $q->where('users.id', $this->id)
                ->where(function ($sub) use ($tenantId) {
                    if ($tenantId) {
                        $sub->where('user_roles.tenant_id', $tenantId)->orWhereNull('user_roles.tenant_id');
                    }
                });
        })->pluck('name')->unique()->values();
    }
}

