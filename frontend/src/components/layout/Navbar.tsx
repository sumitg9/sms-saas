"use client";

import Link from "next/link";
import { GraduationCap, ShieldCheck, LogOut, User as UserIcon, LogIn } from "lucide-react";
import { ThemeToggle } from "./ThemeToggle";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/providers/auth-provider";

export function Navbar() {
  const { isAuthenticated, user, roles, logout } = useAuth();

  const primaryRole = roles[0] ? roles[0].replace("_", " ") : "Member";

  return (
    <header className="sticky top-0 z-40 w-full border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
      <div className="container mx-auto flex h-14 items-center justify-between px-4 sm:px-8">
        <div className="flex items-center gap-3">
          <Link href="/" className="flex items-center gap-2.5 font-bold text-slate-900 dark:text-white">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-500/20">
              <GraduationCap className="h-4 w-4" />
            </div>
            <div className="flex flex-col">
              <span className="text-sm tracking-tight font-bold">EduCore ERP</span>
              <span className="text-[9px] uppercase font-semibold text-blue-600 dark:text-blue-400 tracking-wider">
                Multi-Tenant SaaS
              </span>
            </div>
          </Link>

          <div className="hidden sm:flex items-center ml-4 pl-4 border-l border-slate-200 dark:border-slate-800">
            <Badge variant="success" className="text-[11px] gap-1">
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
              API v1 Connected
            </Badge>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <div className="hidden md:flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mr-2">
            <ShieldCheck className="h-4 w-4 text-emerald-500" />
            <span>Tenant Scoped &amp; RLS Protected</span>
          </div>

          <ThemeToggle />

          {isAuthenticated && user ? (
            <div className="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-800">
              <Link href="/profile">
                <Button variant="ghost" size="sm" className="gap-2 text-xs font-normal">
                  <UserIcon className="h-3.5 w-3.5 text-slate-500" />
                  <span className="font-semibold text-slate-800 dark:text-slate-200">{user.first_name}</span>
                  <Badge variant="outline" className="text-[10px] capitalize py-0 px-1.5">
                    {primaryRole}
                  </Badge>
                </Button>
              </Link>

              <Button
                variant="outline"
                size="sm"
                onClick={() => logout()}
                className="gap-1.5 text-xs text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40"
              >
                <LogOut className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Logout</span>
              </Button>
            </div>
          ) : (
            <Link href="/login">
              <Button size="sm" className="gap-1.5 text-xs">
                <LogIn className="h-3.5 w-3.5" />
                <span>Log In</span>
              </Button>
            </Link>
          )}
        </div>
      </div>
    </header>
  );
}
