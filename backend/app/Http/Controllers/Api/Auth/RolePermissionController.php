<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RolePermissionController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * List all system and tenant-specific roles.
     */
    public function indexRoles(): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        $roles = Role::where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id');
            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            }
        })
        ->with('permissions:id,name,module,display_name')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * List all available granular permissions across system modules.
     */
    public function indexPermissions(): JsonResponse
    {
        $permissions = Permission::all()
            ->groupBy('module');

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Assign a role to a user within the active tenant and branch.
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_name' => ['required', 'string', 'exists:roles,name'],
            'branch_id' => ['nullable', 'uuid', 'exists:school_branches,id'],
        ]);

        $roleName = $request->input('role_name');
        $tenantId = $this->tenantContext->getTenantId();
        $branchId = $request->input('branch_id', $this->tenantContext->getBranchId());

        $currentUser = $request->user();

        // Prevent privilege escalation: Non-super admins cannot grant platform roles
        if (in_array($roleName, ['platform_admin', 'platform_support', 'super_admin']) && !$currentUser->is_platform_admin) {
            return response()->json([
                'success' => false,
                'code' => 'PRIVILEGE_ESCALATION_DENIED',
                'message' => 'Unauthorized: Only Platform Super Admins can assign platform-level roles.',
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        // Prevent cross-tenant role assignment: Must be in tenant context
        if (!in_array($roleName, ['platform_admin', 'platform_support', 'super_admin']) && !$tenantId) {
            return response()->json([
                'success' => false,
                'code' => 'TENANT_REQUIRED',
                'message' => 'Cannot assign school role without an active tenant context.',
                'timestamp' => now()->toIso8601String(),
            ], 400);
        }

        $user->assignRole($roleName, $tenantId, $branchId);

        return response()->json([
            'success' => true,
            'message' => "Role '{$roleName}' successfully assigned to user.",
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->getRolesForTenant($tenantId),
                'permissions' => $user->getPermissionsForTenant($tenantId),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Revoke a role from a user within the active tenant.
     */
    public function removeRole(Request $request, User $user, string $roleName): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();

        $user->removeRole($roleName, $tenantId);

        return response()->json([
            'success' => true,
            'message' => "Role '{$roleName}' successfully removed from user.",
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->getRolesForTenant($tenantId),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

