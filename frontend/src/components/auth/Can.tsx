"use client";

import React from "react";
import { useAuth } from "@/providers/auth-provider";

export interface CanProps {
  permission?: string;
  permissions?: string[];
  role?: string;
  roles?: string[];
  requireAll?: boolean;
  fallback?: React.ReactNode;
  children: React.ReactNode;
}

/**
 * Granular Authorization Component
 * Conditionally renders UI children based on verified user permissions and roles.
 * Note: Frontend gating is UX only; backend API always enforces permissions.
 */
export function Can({
  permission,
  permissions = [],
  role,
  roles = [],
  requireAll = false,
  fallback = null,
  children,
}: CanProps) {
  const { hasPermission, hasAnyPermission, hasRole, hasAnyRole } = useAuth();

  let hasPermissionAccess = true;
  let hasRoleAccess = true;

  // Evaluate permission checks
  if (permission) {
    hasPermissionAccess = hasPermission(permission);
  } else if (permissions.length > 0) {
    hasPermissionAccess = requireAll
      ? permissions.every((p) => hasPermission(p))
      : hasAnyPermission(permissions);
  }

  // Evaluate role checks
  if (role) {
    hasRoleAccess = hasRole(role);
  } else if (roles.length > 0) {
    hasRoleAccess = requireAll
      ? roles.every((r) => hasRole(r))
      : hasAnyRole(roles);
  }

  const isAllowed = hasPermissionAccess && hasRoleAccess;

  if (!isAllowed) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

