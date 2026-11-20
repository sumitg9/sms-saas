"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useAuth } from "@/providers/auth-provider";
import apiClient from "@/lib/api-client";
import { Sidebar } from "@/components/layout/Sidebar";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { Modal } from "@/components/ui/dialog";
import {
  ShieldAlert,
  Building2,
  Users,
  CreditCard,
  DollarSign,
  TrendingUp,
  Search,
  Filter,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Loader2,
  RefreshCw,
  Clock,
  Lock,
} from "lucide-react";

interface TenantItem {
  id: string;
  name: string;
  slug: string;
  status: "active" | "trial" | "suspended" | "cancelled";
  contact_email: string;
  contact_phone?: string;
  trial_ends_at?: string;
  created_at: string;
  schools?: Array<{ id: string; name: string }>;
  subscriptions?: Array<{
    id: string;
    status: string;
    billing_cycle: string;
    plan?: { name: string; monthly_price: number };
  }>;
}

interface PlatformMetrics {
  total_tenants: number;
  active_tenants: number;
  trial_tenants: number;
  suspended_tenants: number;
  mrr: number;
  arr: number;
}

export default function PlatformAdminPage() {
  const { user, hasRole } = useAuth();

  const isAuthorized = user?.is_platform_admin || hasRole("super_admin") || hasRole("support_admin");

  const [tenants, setTenants] = useState<TenantItem[]>([]);
  const [metrics, setMetrics] = useState<PlatformMetrics | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [searchTerm, setSearchTerm] = useState<string>("");
  const [selectedStatus, setSelectedStatus] = useState<string>("");
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Modal actions
  const [selectedTenant, setSelectedTenant] = useState<TenantItem | null>(null);
  const [actionType, setActionType] = useState<"suspend" | "activate" | "extend_trial" | null>(null);
  const [actionLoading, setActionLoading] = useState<boolean>(false);
  const [suspensionReason, setSuspensionReason] = useState<string>("");
  const [extendDays, setExtendDays] = useState<number>(14);

  // Load platform analytics & tenants
  const loadPlatformData = useCallback(async () => {
    setLoading(true);
    try {
      const [tenantsRes, analyticsRes] = await Promise.all([
        apiClient.get("/platform/tenants", {
          params: {
            search: searchTerm || undefined,
            status: selectedStatus || undefined,
          },
        }).catch(() => ({ data: { data: [] } })),
        apiClient.get("/analytics/platform").catch(() => ({ data: { data: null } })),
      ]);

      setTenants(tenantsRes.data?.data || []);

      if (analyticsRes.data?.data) {
        setMetrics(analyticsRes.data.data);
      } else {
        // Fallback calculation from tenant list
        const list: TenantItem[] = tenantsRes.data?.data || [];
        setMetrics({
          total_tenants: list.length,
          active_tenants: list.filter((t) => t.status === "active").length,
          trial_tenants: list.filter((t) => t.status === "trial").length,
          suspended_tenants: list.filter((t) => t.status === "suspended").length,
          mrr: list.filter((t) => t.status === "active").length * 4999,
          arr: list.filter((t) => t.status === "active").length * 49990,
        });
      }
    } catch (err) {
      console.error("Failed to load platform data", err);
    } finally {
      setLoading(false);
    }
  }, [searchTerm, selectedStatus]);

  useEffect(() => {
    if (isAuthorized) {
      loadPlatformData();
    }
  }, [isAuthorized, loadPlatformData]);

  // Handle status update
  const handleUpdateStatus = async () => {
    if (!selectedTenant || !actionType) return;
    setActionLoading(true);
    setMessage(null);

    try {
      if (actionType === "suspend" || actionType === "activate") {
        const newStatus = actionType === "suspend" ? "suspended" : "active";
        await apiClient.put(`/platform/tenants/${selectedTenant.id}/status`, {
          status: newStatus,
          suspension_reason: actionType === "suspend" ? suspensionReason : null,
        });
        setMessage({ type: "success", text: `Tenant status changed to ${newStatus}.` });
      } else if (actionType === "extend_trial") {
        await apiClient.post(`/platform/tenants/${selectedTenant.id}/extend-trial`, {
          days: extendDays,
        });
        setMessage({ type: "success", text: `Trial extended by ${extendDays} days.` });
      }

      setActionType(null);
      setSelectedTenant(null);
      loadPlatformData();
    } catch (err: any) {
      setMessage({ type: "error", text: err.response?.data?.message || "Failed to update tenant status." });
    } finally {
      setActionLoading(false);
    }
  };

  if (!isAuthorized) {
    return (
      <div className="flex h-screen bg-slate-50 dark:bg-slate-900">
        <Sidebar />
        <div className="flex-1 flex flex-col items-center justify-center p-8">
          <div className="max-w-md w-full bg-white dark:bg-slate-800 p-8 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 text-center">
            <Lock className="h-12 w-12 text-rose-500 mx-auto mb-4" />
            <h2 className="text-xl font-bold text-slate-900 dark:text-white">Platform Access Restricted</h2>
            <p className="text-sm text-slate-500 dark:text-slate-400 mt-2">
              Only platform super administrators have permissions to view and manage SaaS tenant organizations.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-screen bg-slate-50 dark:bg-slate-900">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-y-auto">
        <header className="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-8 py-5">
          <div className="flex justify-between items-center">
            <div>
              <div className="flex items-center gap-2">
                <ShieldAlert className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                  SaaS Platform Administration
                </h1>
                <Badge variant="destructive" className="ml-2 uppercase tracking-wider text-xs">
                  Super Admin
                </Badge>
              </div>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Cross-tenant oversight, subscription governance, school onboarding, and SaaS metrics.
              </p>
            </div>
            <Button variant="outline" size="sm" onClick={loadPlatformData} className="gap-2">
              <RefreshCw className="h-4 w-4" />
              Refresh Metrics
            </Button>
          </div>
        </header>

        <main className="p-8 space-y-6">
          {message && (
            <div
              className={`p-4 rounded-lg flex items-center gap-3 border ${
                message.type === "success"
                  ? "bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300"
                  : "bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300"
              }`}
            >
              {message.type === "success" ? (
                <CheckCircle2 className="h-5 w-5 shrink-0" />
              ) : (
                <AlertTriangle className="h-5 w-5 shrink-0" />
              )}
              <span className="text-sm font-medium">{message.text}</span>
            </div>
          )}

          {/* Metric Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card className="p-5">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-slate-500">Total Registered Schools</span>
                <Building2 className="h-5 w-5 text-indigo-500" />
              </div>
              <div className="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                {metrics?.total_tenants ?? 0}
              </div>
            </Card>

            <Card className="p-5">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-slate-500">Active Subscriptions</span>
                <CheckCircle2 className="h-5 w-5 text-emerald-500" />
              </div>
              <div className="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2">
                {metrics?.active_tenants ?? 0}
              </div>
            </Card>

            <Card className="p-5">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-slate-500">Trial Accounts</span>
                <Clock className="h-5 w-5 text-amber-500" />
              </div>
              <div className="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-2">
                {metrics?.trial_tenants ?? 0}
              </div>
            </Card>

            <Card className="p-5">
              <div className="flex justify-between items-center">
                <span className="text-sm font-medium text-slate-500">Estimated MRR</span>
                <TrendingUp className="h-5 w-5 text-indigo-600" />
              </div>
              <div className="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-2">
                ₹{(metrics?.mrr ?? 0).toLocaleString()}
              </div>
            </Card>
          </div>

          {/* Tenant Search & Filter Card */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                  <CardTitle className="text-lg">School Organization Directory</CardTitle>
                  <CardDescription>All onboarded school tenants and their subscription status.</CardDescription>
                </div>
                <div className="flex items-center gap-3">
                  <div className="relative w-64">
                    <Search className="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <Input
                      placeholder="Search school or email..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-9 h-10"
                    />
                  </div>

                  <select
                    value={selectedStatus}
                    onChange={(e) => setSelectedStatus(e.target.value)}
                    className="h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                  >
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="trial">Trial</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="py-12 flex justify-center items-center text-slate-400">
                  <Loader2 className="h-6 w-6 animate-spin mr-2" />
                  Loading tenant organizations...
                </div>
              ) : tenants.length === 0 ? (
                <div className="py-12 text-center text-slate-400">No school tenants found.</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>School / Tenant</TableHead>
                      <TableHead>Contact Email</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Active Plan</TableHead>
                      <TableHead>Registered</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {tenants.map((t) => {
                      const activeSub = t.subscriptions?.[0];
                      return (
                        <TableRow key={t.id}>
                          <TableCell>
                            <div className="font-semibold text-slate-900 dark:text-white">{t.name}</div>
                            <div className="text-xs text-slate-400">Slug: {t.slug}</div>
                          </TableCell>
                          <TableCell className="text-sm">{t.contact_email}</TableCell>
                          <TableCell>
                            <Badge
                              variant={
                                t.status === "active"
                                  ? "default"
                                  : t.status === "trial"
                                  ? "secondary"
                                  : "destructive"
                              }
                            >
                              {t.status.toUpperCase()}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            <div className="text-sm font-medium">
                              {activeSub?.plan?.name || (t.status === "trial" ? "Trial Period" : "None")}
                            </div>
                            {t.trial_ends_at && t.status === "trial" && (
                              <div className="text-xs text-slate-400">
                                Trial ends: {new Date(t.trial_ends_at).toLocaleDateString()}
                              </div>
                            )}
                          </TableCell>
                          <TableCell className="text-xs text-slate-500">
                            {new Date(t.created_at).toLocaleDateString()}
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex justify-end gap-1.5">
                              {t.status === "trial" && (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => {
                                    setSelectedTenant(t);
                                    setActionType("extend_trial");
                                  }}
                                  className="text-xs h-8"
                                >
                                  +14 Days Trial
                                </Button>
                              )}
                              {t.status === "suspended" ? (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => {
                                    setSelectedTenant(t);
                                    setActionType("activate");
                                  }}
                                  className="text-xs h-8 text-emerald-600 border-emerald-300 hover:bg-emerald-50"
                                >
                                  Activate
                                </Button>
                              ) : (
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => {
                                    setSelectedTenant(t);
                                    setActionType("suspend");
                                  }}
                                  className="text-xs h-8 text-rose-600 border-rose-300 hover:bg-rose-50"
                                >
                                  Suspend
                                </Button>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          {/* Action Modals */}
          {selectedTenant && actionType && (
            <Modal
              isOpen={Boolean(selectedTenant && actionType)}
              onClose={() => {
                setSelectedTenant(null);
                setActionType(null);
              }}
              title={
                actionType === "suspend"
                  ? `Suspend Tenant: ${selectedTenant.name}`
                  : actionType === "activate"
                  ? `Reactivate Tenant: ${selectedTenant.name}`
                  : `Extend Trial: ${selectedTenant.name}`
              }
            >
              <div className="space-y-4">
                {actionType === "suspend" && (
                  <div>
                    <Label htmlFor="suspension-reason">Reason for Suspension</Label>
                    <Input
                      id="suspension-reason"
                      placeholder="e.g. Overdue payment, policy breach..."
                      value={suspensionReason}
                      onChange={(e) => setSuspensionReason(e.target.value)}
                      className="mt-1.5"
                    />
                    <p className="text-xs text-rose-500 mt-2">
                      Warning: Users belonging to this school will be blocked from accessing ERP endpoints until reactivated.
                    </p>
                  </div>
                )}

                {actionType === "activate" && (
                  <p className="text-sm text-slate-600 dark:text-slate-400">
                    Are you sure you want to reactivate access for <strong>{selectedTenant.name}</strong>?
                  </p>
                )}

                {actionType === "extend_trial" && (
                  <div>
                    <Label htmlFor="extend-days">Extend Days</Label>
                    <Input
                      id="extend-days"
                      type="number"
                      min={1}
                      max={90}
                      value={extendDays}
                      onChange={(e) => setExtendDays(Number(e.target.value))}
                      className="mt-1.5"
                    />
                  </div>
                )}

                <div className="pt-4 flex justify-end gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setSelectedTenant(null);
                      setActionType(null);
                    }}
                  >
                    Cancel
                  </Button>
                  <Button
                    onClick={handleUpdateStatus}
                    disabled={actionLoading}
                    variant={actionType === "suspend" ? "destructive" : "default"}
                    className={actionType !== "suspend" ? "bg-indigo-600 hover:bg-indigo-700" : ""}
                  >
                    {actionLoading && <Loader2 className="h-4 w-4 animate-spin mr-2" />}
                    Confirm
                  </Button>
                </div>
              </div>
            </Modal>
          )}
        </main>
      </div>
    </div>
  );
}

