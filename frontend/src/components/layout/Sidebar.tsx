"use client";

import React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/providers/auth-provider";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  GraduationCap,
  Users,
  CalendarCheck,
  Receipt,
  BookOpenCheck,
  Briefcase,
  Megaphone,
  Bus,
  BarChart3,
  Settings,
  ShieldAlert,
  ChevronRight,
  School,
} from "lucide-react";

interface NavItem {
  title: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  permission?: string;
  permissions?: string[];
  role?: string;
  roles?: string[];
  badge?: string;
}

const navItems: NavItem[] = [
  {
    title: "Dashboard",
    href: "/",
    icon: LayoutDashboard,
  },
  {
    title: "Academic Management",
    href: "/academic",
    icon: GraduationCap,
    permissions: ["academics.view", "academics.manage"],
  },
  {
    title: "Students & Admissions",
    href: "/students",
    icon: Users,
    permissions: ["students.view", "admissions.view"],
  },
  {
    title: "Attendance",
    href: "/attendance",
    icon: CalendarCheck,
    permissions: ["attendance.view", "attendance.manage"],
  },
  {
    title: "Fees & Finance",
    href: "/finance",
    icon: Receipt,
    permissions: ["fees.view", "finance.view", "fees.collect"],
  },
  {
    title: "Examinations",
    href: "/examinations",
    icon: BookOpenCheck,
    permissions: ["examinations.view", "examinations.manage"],
  },
  {
    title: "Staff & HR",
    href: "/staff",
    icon: Briefcase,
    permissions: ["staff.view", "staff.manage"],
  },
  {
    title: "Communication",
    href: "/communication",
    icon: Megaphone,
    permissions: ["communication.view", "communication.manage"],
  },
  {
    title: "Operations (Transport/Library)",
    href: "/operations",
    icon: Bus,
    permissions: ["transport.view", "library.view", "inventory.view"],
  },
  {
    title: "Reports & Analytics",
    href: "/reports",
    icon: BarChart3,
    permissions: ["reports.view", "reports.export"],
  },
  {
    title: "School Settings",
    href: "/settings",
    icon: Settings,
    permissions: ["settings.view", "settings.manage"],
  },
  {
    title: "SaaS Platform Admin",
    href: "/platform",
    icon: ShieldAlert,
    roles: ["super_admin", "support_admin"],
    badge: "Super Admin",
  },
];

export function Sidebar({ className }: { className?: string }) {
  const pathname = usePathname();
  const { user, activeTenant, hasRole, hasAnyRole, hasPermission, hasAnyPermission } = useAuth();

  const filteredNavItems = navItems.filter((item) => {
    // If item specifies a role requirement
    if (item.role && !hasRole(item.role)) {
      return false;
    }
    if (item.roles && !hasAnyRole(item.roles)) {
      return false;
    }

    // If item specifies a permission requirement
    if (item.permission && !hasPermission(item.permission)) {
      return false;
    }
    if (item.permissions && !hasAnyPermission(item.permissions)) {
      return false;
    }

    return true;
  });

  return (
    <aside
      className={cn(
        "flex flex-col w-64 border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 h-[calc(100vh-3.5rem)] sticky top-14",
        className
      )}
    >
      {/* Tenant Context Indicator */}
      <div className="p-4 border-b border-slate-200 dark:border-slate-800">
        <div className="flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
            <School className="h-5 w-5" />
          </div>
          <div className="flex flex-col overflow-hidden">
            <span className="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
              {activeTenant?.name || "School ERP Platform"}
            </span>
            <span className="text-xs text-slate-500 capitalize">
              {activeTenant?.subdomain ? `${activeTenant.subdomain}.school.erp` : "Multi-Tenant"}
            </span>
          </div>
        </div>
      </div>

      {/* Navigation List */}
      <nav className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        {filteredNavItems.map((item) => {
          const isActive = pathname === item.href || (item.href !== "/" && pathname.startsWith(item.href));
          const Icon = item.icon;

          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "group flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition-colors",
                isActive
                  ? "bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-semibold"
                  : "text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
              )}
            >
              <div className="flex items-center gap-3">
                <Icon
                  className={cn(
                    "h-4 w-4 transition-colors",
                    isActive ? "text-blue-600 dark:text-blue-400" : "text-slate-500 group-hover:text-slate-700 dark:text-slate-400"
                  )}
                />
                <span>{item.title}</span>
              </div>
              {item.badge ? (
                <span className="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                  {item.badge}
                </span>
              ) : (
                isActive && <ChevronRight className="h-4 w-4 text-blue-500" />
              )}
            </Link>
          );
        })}
      </nav>

      {/* User Status Footer */}
      {user && (
        <div className="p-3 border-t border-slate-200 dark:border-slate-800">
          <Link
            href="/profile"
            className="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200">
              {user.first_name[0]}
              {user.last_name[0]}
            </div>
            <div className="flex flex-col overflow-hidden text-left">
              <span className="text-xs font-medium text-slate-900 dark:text-slate-100 truncate">
                {user.name}
              </span>
              <span className="text-[11px] text-slate-500 truncate">{user.email}</span>
            </div>
          </Link>
        </div>
      )}
    </aside>
  );
}

