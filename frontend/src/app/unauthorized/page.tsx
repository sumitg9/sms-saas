"use client";

import React from "react";
import Link from "next/link";
import { ShieldAlert, ArrowLeft, Home, School } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/providers/auth-provider";

export default function UnauthorizedPage() {
  const { user, roles, activeTenant } = useAuth();

  return (
    <div className="flex min-h-[calc(100vh-10rem)] items-center justify-center p-4">
      <Card className="w-full max-w-lg shadow-xl border-rose-200 dark:border-rose-950/60 bg-gradient-to-b from-white to-rose-50/20 dark:from-slate-900 dark:to-rose-950/10">
        <CardHeader className="text-center space-y-3 pb-4">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-900 shadow-inner">
            <ShieldAlert className="h-8 w-8" />
          </div>
          <CardTitle className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
            Access Denied &mdash; 403 Forbidden
          </CardTitle>
          <CardDescription className="text-slate-600 dark:text-slate-400 text-sm">
            You do not possess the required tenant role or granular permissions to access this administrative resource.
          </CardDescription>
        </CardHeader>

        <CardContent className="space-y-4">
          <div className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-slate-950/40 p-4 space-y-3 text-xs">
            <div className="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
              <span className="text-slate-500">Authenticated User</span>
              <span className="font-semibold text-slate-800 dark:text-slate-200">{user?.name || "Anonymous"}</span>
            </div>
            <div className="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
              <span className="text-slate-500">Current School Organization</span>
              <span className="font-semibold text-slate-800 dark:text-slate-200">
                {activeTenant?.name || "Unscoped Tenant"}
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-500">Active Roles</span>
              <div className="flex flex-wrap gap-1">
                {roles.length > 0 ? (
                  roles.map((r) => (
                    <Badge key={r} variant="outline" className="text-[10px] capitalize">
                      {r.replace("_", " ")}
                    </Badge>
                  ))
                ) : (
                  <Badge variant="secondary" className="text-[10px]">
                    No Active Roles
                  </Badge>
                )}
              </div>
            </div>
          </div>

          <div className="rounded-lg bg-amber-50 dark:bg-amber-950/30 p-3 text-xs text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900 leading-relaxed">
            <strong>Security Isolation Notice:</strong> In multi-tenant architecture, access is restricted strictly to
            authorized branches and verified academic memberships. If you believe this is in error, contact your School
            Principal or System Administrator to verify your role assignments.
          </div>
        </CardContent>

        <CardFooter className="flex flex-col sm:flex-row items-center gap-3 pt-2">
          <Link href="/" className="w-full sm:w-auto flex-1">
            <Button className="w-full gap-2">
              <Home className="h-4 w-4" />
              <span>Return to Dashboard</span>
            </Button>
          </Link>
          <Link href="/profile" className="w-full sm:w-auto">
            <Button variant="outline" className="w-full gap-1.5 text-xs">
              <School className="h-3.5 w-3.5" />
              <span>View Profile &amp; Roles</span>
            </Button>
          </Link>
        </CardFooter>
      </Card>
    </div>
  );
}

