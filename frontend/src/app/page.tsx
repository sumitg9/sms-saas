"use client";

import React from "react";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import {
  Shield,
  Layers,
  Server,
  Lock,
  Building2,
  Activity,
  UserCheck,
  KeyRound,
  LogIn,
  PlusCircle,
  Receipt,
  FileCheck,
  UserPlus,
  ShieldAlert,
} from "lucide-react";
import apiClient from "@/lib/api-client";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Sidebar } from "@/components/layout/Sidebar";
import { Can } from "@/components/auth/Can";
import { useAuth } from "@/providers/auth-provider";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

interface HealthData {
  success: boolean;
  message: string;
  version: string;
  timestamp: string;
}

const telemetrySample = [
  { time: "06:00", reqs: 120 },
  { time: "07:00", reqs: 480 },
  { time: "08:00", reqs: 1850 },
  { time: "09:00", reqs: 2400 },
  { time: "10:00", reqs: 1950 },
  { time: "11:00", reqs: 1620 },
  { time: "12:00", reqs: 1780 },
];

export default function HomePage() {
  const { user, activeTenant, roles, permissions, isAuthenticated } = useAuth();

  const { data, isLoading, isError } = useQuery({
    queryKey: ["apiHealth"],
    queryFn: async () => {
      const res = await apiClient.get<HealthData>("/health");
      return res.data;
    },
    retry: 1,
  });

  return (
    <div className="flex min-h-[calc(100vh-3.5rem)]">
      {/* Role-Aware Sidebar */}
      <Sidebar className="hidden md:flex" />

      {/* Main Content Area */}
      <div className="flex-1 overflow-y-auto px-4 py-8 sm:px-8 max-w-7xl mx-auto space-y-8">
        {/* Top Header Banner */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-6">
          <div>
            <div className="flex items-center gap-2 mb-1.5">
              <Badge
                variant="outline"
                className="font-mono text-[11px] uppercase tracking-wider text-blue-600 dark:text-blue-400 border-blue-500/20 bg-blue-500/5"
              >
                RBAC &amp; Tenancy Verified
              </Badge>
              <span className="text-xs text-slate-500 dark:text-slate-400">
                &bull; 17 Roles &bull; 104 Granular Capabilities
              </span>
            </div>
            <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
              {isAuthenticated && user
                ? `Welcome back, ${user.first_name}`
                : "School ERP SaaS Platform"}
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {isAuthenticated && activeTenant
                ? `Managing ${activeTenant.name} &bull; Multi-Branch Isolation &bull; PostgreSQL RLS Active`
                : "Multi-Tenant B2B Cloud ERP &bull; Dual-Layer PostgreSQL RLS &bull; Board-Agnostic Academic Architecture"}
            </p>
          </div>

          <div className="flex items-center gap-3">
            {isAuthenticated ? (
              <Link href="/profile">
                <Button variant="outline" size="sm" className="gap-2 text-xs">
                  <UserCheck className="h-3.5 w-3.5 text-blue-600" />
                  <span>Security &amp; 2FA</span>
                </Button>
              </Link>
            ) : (
              <Link href="/register">
                <Button variant="outline" size="sm" className="gap-2 text-xs">
                  <Building2 className="h-3.5 w-3.5 text-blue-600" />
                  <span>Register Institution</span>
                </Button>
              </Link>
            )}
          </div>
        </div>

        {/* Authenticated Permission-Aware Action Center */}
        {isAuthenticated && (
          <Card className="border-blue-200 bg-blue-50/40 dark:border-blue-900/60 dark:bg-blue-950/20">
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-sm font-semibold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                    <Shield className="h-4 w-4 text-blue-600" />
                    <span>Role-Gated Action Center (&lt;Can /&gt; Authorization Showcase)</span>
                  </CardTitle>
                  <CardDescription className="text-xs">
                    Buttons below are evaluated against the current user&apos;s active tenant permissions and roles:
                  </CardDescription>
                </div>
                <div className="flex items-center gap-1.5">
                  <span className="text-xs text-slate-500">Active Roles:</span>
                  {roles.map((r) => (
                    <Badge key={r} variant="outline" className="text-[10px] capitalize">
                      {r.replace("_", " ")}
                    </Badge>
                  ))}
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="flex flex-wrap gap-2.5">
                <Can permission="students.create">
                  <Button size="sm" className="gap-1.5 text-xs bg-emerald-600 hover:bg-emerald-700">
                    <UserPlus className="h-3.5 w-3.5" />
                    <span>Admit Student</span>
                  </Button>
                </Can>

                <Can permission="fees.collect">
                  <Button size="sm" className="gap-1.5 text-xs bg-blue-600 hover:bg-blue-700">
                    <Receipt className="h-3.5 w-3.5" />
                    <span>Collect Fee</span>
                  </Button>
                </Can>

                <Can permission="attendance.manage">
                  <Button size="sm" variant="outline" className="gap-1.5 text-xs">
                    <Activity className="h-3.5 w-3.5 text-purple-600" />
                    <span>Take Attendance</span>
                  </Button>
                </Can>

                <Can permission="examinations.publish">
                  <Button size="sm" variant="outline" className="gap-1.5 text-xs">
                    <FileCheck className="h-3.5 w-3.5 text-amber-600" />
                    <span>Publish Results</span>
                  </Button>
                </Can>

                <Can role="school_admin">
                  <Button size="sm" variant="outline" className="gap-1.5 text-xs">
                    <Building2 className="h-3.5 w-3.5 text-slate-600" />
                    <span>Branch Settings</span>
                  </Button>
                </Can>

                <Can role="super_admin">
                  <Button size="sm" variant="destructive" className="gap-1.5 text-xs">
                    <ShieldAlert className="h-3.5 w-3.5" />
                    <span>Platform Admin Console</span>
                  </Button>
                </Can>

                <Link href="/unauthorized">
                  <Button size="sm" variant="ghost" className="gap-1 text-xs text-slate-500">
                    <span>Test 403 Page</span>
                  </Button>
                </Link>
              </div>
            </CardContent>
          </Card>
        )}

        {/* KPI Cards Grid */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Card className="hover:shadow-md transition-shadow">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-300">
                API Service Status
              </CardTitle>
              <Server className="h-4 w-4 text-blue-600 dark:text-blue-400" />
            </CardHeader>
            <CardContent>
              {isLoading ? (
                <div className="text-sm text-slate-400">Probing API...</div>
              ) : isError ? (
                <div className="flex items-center gap-2">
                  <span className="h-2 w-2 rounded-full bg-amber-500" />
                  <span className="text-sm font-semibold text-amber-600 dark:text-amber-400">Standby (Offline)</span>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <span className="h-2 w-2 rounded-full bg-emerald-500" />
                  <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Operational (v1)</span>
                </div>
              )}
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {data?.message ?? "Prefix: /api/v1"}
              </p>
            </CardContent>
          </Card>

          <Card className="hover:shadow-md transition-shadow">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-300">
                Tenant Isolation
              </CardTitle>
              <Shield className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-slate-900 dark:text-white">Dual-Layer</div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                PostgreSQL RLS + Eloquent Scope
              </p>
            </CardContent>
          </Card>

          <Card className="hover:shadow-md transition-shadow">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-300">
                Active Permissions
              </CardTitle>
              <Layers className="h-4 w-4 text-purple-600 dark:text-purple-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-slate-900 dark:text-white">
                {permissions.length > 0 ? `${permissions.length} Grants` : "104 Matrix"}
              </div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                14 Functional Domains
              </p>
            </CardContent>
          </Card>

          <Card className="hover:shadow-md transition-shadow">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-slate-600 dark:text-slate-300">
                Role Architecture
              </CardTitle>
              <Lock className="h-4 w-4 text-amber-600 dark:text-amber-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-slate-900 dark:text-white">17 Roles</div>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Platform + Institution Gating
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Telemetry and Security Details */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Security & Multi-Tenancy Card */}
          <Card className="lg:col-span-1">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Shield className="h-4 w-4 text-blue-600" />
                <CardTitle className="text-base">Authentication Engine</CardTitle>
              </div>
              <CardDescription>Sanctum + Tenant RBAC verification</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {[
                { title: "Session Auth", desc: "Laravel Sanctum Token Engine", ok: true },
                { title: "Multi-Factor", desc: "RFC 6238 TOTP + 8 Recovery Codes", ok: true },
                { title: "Session Tracking", desc: "User Sessions & Login History Logs", ok: true },
                { title: "Rate Limiting", desc: "Brute Force Protection (5 attempts/min)", ok: true },
                { title: "Tenant Gating", desc: "Membership Verification Middleware", ok: true },
                { title: "Cross-Tenant IDOR", desc: "Tenant Scopes & Composite Keys", ok: true },
              ].map((item, i) => (
                <div
                  key={i}
                  className="flex items-start justify-between text-xs py-1 border-b border-slate-100 dark:border-slate-800 last:border-0"
                >
                  <div>
                    <span className="font-semibold text-slate-800 dark:text-slate-200">{item.title}</span>
                    <p className="text-slate-500 dark:text-slate-400">{item.desc}</p>
                  </div>
                  <Badge variant="success" className="text-[10px] py-0 px-1.5 shrink-0">
                    Active
                  </Badge>
                </div>
              ))}
            </CardContent>
          </Card>

          {/* Quick Access Portals */}
          <Card className="lg:col-span-1">
            <CardHeader>
              <div className="flex items-center gap-2">
                <KeyRound className="h-4 w-4 text-purple-600" />
                <CardTitle className="text-base">Authentication Views</CardTitle>
              </div>
              <CardDescription>Direct navigation to auth endpoints</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2.5">
              <Link href="/login" className="block">
                <div className="rounded-lg border border-slate-200 dark:border-slate-800 p-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                  <div className="flex items-center justify-between text-xs font-semibold text-slate-800 dark:text-slate-200">
                    <span>Sign In Page</span>
                    <Badge variant="outline" className="text-[10px]">/login</Badge>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5">Supports credentials + TOTP 2FA Challenge</p>
                </div>
              </Link>

              <Link href="/forgot-password" className="block">
                <div className="rounded-lg border border-slate-200 dark:border-slate-800 p-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                  <div className="flex items-center justify-between text-xs font-semibold text-slate-800 dark:text-slate-200">
                    <span>Forgot Password</span>
                    <Badge variant="outline" className="text-[10px]">/forgot-password</Badge>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5">Dispatches 64-character secure reset tokens</p>
                </div>
              </Link>

              <Link href="/profile" className="block">
                <div className="rounded-lg border border-slate-200 dark:border-slate-800 p-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                  <div className="flex items-center justify-between text-xs font-semibold text-slate-800 dark:text-slate-200">
                    <span>Profile &amp; Sessions</span>
                    <Badge variant="outline" className="text-[10px]">/profile</Badge>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5">2FA Setup, Device Revocation, Password Change</p>
                </div>
              </Link>

              <Link href="/unauthorized" className="block">
                <div className="rounded-lg border border-slate-200 dark:border-slate-800 p-2.5 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors">
                  <div className="flex items-center justify-between text-xs font-semibold text-slate-800 dark:text-slate-200">
                    <span>Unauthorized Error Page</span>
                    <Badge variant="outline" className="text-[10px]">/unauthorized</Badge>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5">403 Forbidden with tenant role feedback</p>
                </div>
              </Link>
            </CardContent>
          </Card>

          {/* Live Recharts Telemetry Demo */}
          <Card className="lg:col-span-1 flex flex-col justify-between">
            <CardHeader>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Activity className="h-4 w-4 text-blue-600" />
                  <CardTitle className="text-base">Load Simulator</CardTitle>
                </div>
                <Badge variant="outline" className="text-[10px]">Recharts</Badge>
              </div>
              <CardDescription>Morning Peak Auth Concurrency</CardDescription>
            </CardHeader>
            <CardContent className="pt-2">
              <div className="h-[180px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={telemetrySample} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                    <defs>
                      <linearGradient id="reqGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#2563eb" stopOpacity={0.4} />
                        <stop offset="95%" stopColor="#2563eb" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <XAxis dataKey="time" tick={{ fontSize: 10 }} stroke="#94a3b8" />
                    <YAxis tick={{ fontSize: 10 }} stroke="#94a3b8" />
                    <Tooltip
                      contentStyle={{
                        backgroundColor: "rgba(15, 23, 42, 0.9)",
                        borderRadius: "6px",
                        border: "none",
                        color: "#fff",
                        fontSize: "12px",
                      }}
                    />
                    <Area type="monotone" dataKey="reqs" stroke="#2563eb" fillOpacity={1} fill="url(#reqGradient)" />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
              <p className="text-[11px] text-slate-500 dark:text-slate-400 mt-3 text-center">
                High-throughput Sanctum token verification with Redis cache
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
