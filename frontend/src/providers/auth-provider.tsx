"use client";

import React, { createContext, useContext, useEffect, useState, useCallback } from "react";
import apiClient from "@/lib/api-client";

export interface User {
  id: string;
  first_name: string;
  last_name: string;
  name: string;
  email: string;
  phone?: string | null;
  avatar_url?: string | null;
  is_active: boolean;
  is_platform_admin?: boolean;
  two_factor_confirmed_at?: string | null;
}

export interface Tenant {
  id: string;
  name: string;
  slug?: string;
  subdomain?: string;
  domain?: string | null;
  status?: string;
}

export interface SchoolBranch {
  id: string;
  name: string;
  branch_code?: string;
}

export interface Membership {
  id: string;
  tenant_id: string;
  school_id?: string;
  branch_id?: string;
  role: string;
  is_owner: boolean;
  tenant?: Tenant;
}

interface AuthContextType {
  user: User | null;
  activeTenant: Tenant | null;
  activeBranch: SchoolBranch | null;
  roles: string[];
  permissions: string[];
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  isTwoFactorEnabled: boolean;
  hasRole: (role: string) => boolean;
  hasAnyRole: (roles: string[]) => boolean;
  hasPermission: (permission: string) => boolean;
  hasAnyPermission: (permissions: string[]) => boolean;
  login: (data: { email: string; password: string; device_name?: string }) => Promise<{
    requires2fa?: boolean;
    challengeToken?: string;
    user?: User;
  }>;
  complete2FaChallenge: (challengeToken: string, code: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
  switchTenant: (tenantId: string) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [activeTenant, setActiveTenant] = useState<Tenant | null>(null);
  const [activeBranch, setActiveBranch] = useState<SchoolBranch | null>(null);
  const [roles, setRoles] = useState<string[]>([]);
  const [permissions, setPermissions] = useState<string[]>([]);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const setAuthData = useCallback(
    (authToken: string, userData: User, tenantData: Tenant | null, userRoles: string[], userPermissions: string[]) => {
      setToken(authToken);
      setUser(userData);
      setActiveTenant(tenantData);
      setRoles(userRoles);
      setPermissions(userPermissions);

      if (typeof window !== "undefined") {
        localStorage.setItem("auth_token", authToken);
        if (tenantData?.id) {
          localStorage.setItem("current_tenant_id", tenantData.id);
        }
      }
    },
    []
  );

  const clearAuthData = useCallback(() => {
    setToken(null);
    setUser(null);
    setActiveTenant(null);
    setActiveBranch(null);
    setRoles([]);
    setPermissions([]);

    if (typeof window !== "undefined") {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("current_tenant_id");
      localStorage.removeItem("current_school_id");
    }
  }, []);

  const refreshProfile = useCallback(async () => {
    try {
      const response = await apiClient.get("/auth/me");
      const { user: refreshedUser, active_tenant, roles: refreshedRoles, permissions: refreshedPermissions } =
        response.data.data;
      setUser(refreshedUser);
      setActiveTenant(active_tenant || null);
      setRoles(refreshedRoles || []);
      setPermissions(refreshedPermissions || []);
    } catch {
      clearAuthData();
    }
  }, [clearAuthData]);

  // Initial session hydration
  useEffect(() => {
    const storedToken = typeof window !== "undefined" ? localStorage.getItem("auth_token") : null;
    if (storedToken) {
      setToken(storedToken);
      refreshProfile().finally(() => setIsLoading(false));
    } else {
      setIsLoading(false);
    }
  }, [refreshProfile]);

  const login = async (data: { email: string; password: string; device_name?: string }) => {
    const response = await apiClient.post("/auth/login", data);
    const result = response.data;

    if (result.requires_2fa) {
      return {
        requires2fa: true,
        challengeToken: result.challenge_token,
      };
    }

    const { token: authToken, data: payload } = result;
    setAuthData(authToken, payload.user, payload.active_tenant || null, payload.roles || [], payload.permissions || []);

    return {
      requires2fa: false,
      user: payload.user,
    };
  };

  const complete2FaChallenge = async (challengeToken: string, code: string) => {
    const response = await apiClient.post("/auth/2fa/challenge", {
      challenge_token: challengeToken,
      code,
    });

    const { token: authToken, data: payload } = response.data;
    setAuthData(authToken, payload.user, payload.active_tenant || null, payload.roles || [], payload.permissions || []);
  };

  const logout = async () => {
    try {
      await apiClient.post("/auth/logout");
    } catch {
      // Ignore network errors on logout
    } finally {
      clearAuthData();
      if (typeof window !== "undefined") {
        window.location.href = "/login";
      }
    }
  };

  const switchTenant = (tenantId: string) => {
    if (typeof window !== "undefined") {
      localStorage.setItem("current_tenant_id", tenantId);
      window.location.reload();
    }
  };

  const hasRole = useCallback(
    (role: string): boolean => {
      if (roles.includes("super_admin") || roles.includes("support_admin")) {
        return true;
      }
      return roles.includes(role);
    },
    [roles]
  );

  const hasAnyRole = useCallback(
    (targetRoles: string[]): boolean => {
      if (roles.includes("super_admin") || roles.includes("support_admin")) {
        return true;
      }
      return targetRoles.some((r) => roles.includes(r));
    },
    [roles]
  );

  const hasPermission = useCallback(
    (permission: string): boolean => {
      if (roles.includes("super_admin")) {
        return true;
      }
      return permissions.includes(permission);
    },
    [roles, permissions]
  );

  const hasAnyPermission = useCallback(
    (targetPermissions: string[]): boolean => {
      if (roles.includes("super_admin")) {
        return true;
      }
      return targetPermissions.some((p) => permissions.includes(p));
    },
    [roles, permissions]
  );

  const value: AuthContextType = {
    user,
    activeTenant,
    activeBranch,
    roles,
    permissions,
    token,
    isLoading,
    isAuthenticated: !!user && !!token,
    isTwoFactorEnabled: !!user?.two_factor_confirmed_at,
    hasRole,
    hasAnyRole,
    hasPermission,
    hasAnyPermission,
    login,
    complete2FaChallenge,
    logout,
    refreshProfile,
    switchTenant,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}

