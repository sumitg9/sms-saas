"use client";

import React, { useState, useEffect } from "react";
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
  Settings,
  School,
  Building,
  CreditCard,
  Sliders,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Save,
  Plus,
  RefreshCw,
  Clock,
  ShieldCheck,
  Receipt,
} from "lucide-react";

interface SchoolData {
  id: string;
  name: string;
  code: string;
  affiliation_number?: string;
  education_board: string;
  email: string;
  phone?: string;
  address?: {
    street?: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country?: string;
  };
}

interface BranchData {
  id: string;
  name: string;
  branch_code: string;
  campus_type?: string;
  email?: string;
  phone?: string;
  is_active: boolean;
  classes_count?: number;
}

interface SubscriptionData {
  tenant: {
    id: string;
    name: string;
    status: string;
    trial_ends_at?: string;
  };
  subscription?: {
    id: string;
    status: string;
    billing_cycle: string;
    current_period_end: string;
    plan?: {
      name: string;
      monthly_price: number;
      annual_price: number;
      currency: string;
    };
  };
  days_remaining: number;
  is_expired: boolean;
  usage?: {
    students: number;
    staff: number;
    branches: number;
    limits?: {
      max_students: number;
      max_staff: number;
      max_branches: number;
    };
  };
}

export default function SettingsPage() {
  const { user, activeTenant } = useAuth();

  const [activeTab, setActiveTab] = useState<"school" | "branches" | "preferences" | "subscription">("school");
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // School Profile state
  const [school, setSchool] = useState<SchoolData>({
    id: "",
    name: "",
    code: "",
    affiliation_number: "",
    education_board: "CBSE",
    email: "",
    phone: "",
  });

  // Branches state
  const [branches, setBranches] = useState<BranchData[]>([]);
  const [showAddBranchModal, setShowAddBranchModal] = useState<boolean>(false);
  const [newBranch, setNewBranch] = useState({
    name: "",
    branch_code: "",
    campus_type: "main",
    email: "",
    phone: "",
  });

  // Preferences state
  const [preferences, setPreferences] = useState({
    currency: "INR",
    timezone: "Asia/Kolkata",
    academic_start_month: "April",
    attendance_sms_alert: "enabled",
  });

  // Subscription state
  const [subscriptionInfo, setSubscriptionInfo] = useState<SubscriptionData | null>(null);
  const [invoices, setInvoices] = useState<any[]>([]);

  // Load initial data
  const loadData = async () => {
    setLoading(true);
    try {
      const [schoolRes, branchRes, prefRes, subRes] = await Promise.all([
        apiClient.get("/settings/school").catch(() => ({ data: { data: null } })),
        apiClient.get("/settings/branches").catch(() => ({ data: { data: [] } })),
        apiClient.get("/settings/operational").catch(() => ({ data: { data: {} } })),
        apiClient.get("/subscriptions/current").catch(() => ({ data: { data: null } })),
      ]);

      if (schoolRes.data?.data) setSchool(schoolRes.data.data);
      if (branchRes.data?.data) setBranches(branchRes.data.data);
      if (prefRes.data?.data) setPreferences((prev) => ({ ...prev, ...prefRes.data.data }));
      if (subRes.data?.data) setSubscriptionInfo(subRes.data.data);
    } catch (err) {
      console.error("Failed to load settings data", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  // Save School Profile
  const handleSaveSchool = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      const res = await apiClient.put("/settings/school", school);
      setMessage({ type: "success", text: "School profile updated successfully!" });
      if (res.data?.data) setSchool(res.data.data);
    } catch (err: any) {
      setMessage({ type: "error", text: err.response?.data?.message || "Failed to update school profile." });
    } finally {
      setSaving(false);
    }
  };

  // Add Branch
  const handleAddBranch = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      const res = await apiClient.post("/settings/branches", newBranch);
      setMessage({ type: "success", text: "New campus branch added successfully!" });
      setShowAddBranchModal(false);
      setNewBranch({ name: "", branch_code: "", campus_type: "main", email: "", phone: "" });
      loadData();
    } catch (err: any) {
      setMessage({ type: "error", text: err.response?.data?.message || "Failed to add branch." });
    } finally {
      setSaving(false);
    }
  };

  // Save Preferences
  const handleSavePreferences = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      await apiClient.post("/settings/operational", { settings: preferences });
      setMessage({ type: "success", text: "Operational preferences saved!" });
    } catch (err: any) {
      setMessage({ type: "error", text: err.response?.data?.message || "Failed to save preferences." });
    } finally {
      setSaving(false);
    }
  };

  // Load Invoices when subscription tab opened
  useEffect(() => {
    if (activeTab === "subscription") {
      apiClient.get("/subscriptions/invoices").then((res) => {
        setInvoices(res.data?.data || []);
      }).catch(console.error);
    }
  }, [activeTab]);

  return (
    <div className="flex h-screen bg-slate-50 dark:bg-slate-900">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-y-auto">
        <header className="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-8 py-5">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Settings className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                School Settings & Configuration
              </h1>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Manage institution details, campus branches, operational rules, and SaaS billing.
              </p>
            </div>
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
                <AlertCircle className="h-5 w-5 shrink-0" />
              )}
              <span className="text-sm font-medium">{message.text}</span>
            </div>
          )}

          {/* Navigation Tabs */}
          <div className="flex gap-2 border-b border-slate-200 dark:border-slate-700 pb-3">
            {[
              { id: "school", label: "School Profile", icon: School },
              { id: "branches", label: "Branches & Campuses", icon: Building },
              { id: "preferences", label: "Preferences", icon: Sliders },
              { id: "subscription", label: "Subscription & Billing", icon: CreditCard },
            ].map((tab) => {
              const Icon = tab.icon;
              const isSelected = activeTab === tab.id;
              return (
                <Button
                  key={tab.id}
                  variant={isSelected ? "default" : "outline"}
                  onClick={() => {
                    setActiveTab(tab.id as any);
                    setMessage(null);
                  }}
                  className="gap-2"
                >
                  <Icon className="h-4 w-4" />
                  {tab.label}
                </Button>
              );
            })}
          </div>

          {loading ? (
            <div className="py-16 flex justify-center items-center text-slate-400">
              <Loader2 className="h-7 w-7 animate-spin mr-2" />
              Loading configuration...
            </div>
          ) : (
            <>
              {/* Tab 1: School Profile */}
              {activeTab === "school" && (
                <Card>
                  <CardHeader>
                    <CardTitle>Institution Profile</CardTitle>
                    <CardDescription>
                      Official school information used on report cards, fee receipts, and parent communications.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <form onSubmit={handleSaveSchool} className="space-y-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <Label htmlFor="school-name">School Name</Label>
                          <Input
                            id="school-name"
                            value={school.name}
                            onChange={(e) => setSchool({ ...school, name: e.target.value })}
                            required
                            className="mt-1.5"
                          />
                        </div>
                        <div>
                          <Label htmlFor="school-code">School Code</Label>
                          <Input
                            id="school-code"
                            value={school.code}
                            onChange={(e) => setSchool({ ...school, code: e.target.value })}
                            className="mt-1.5"
                          />
                        </div>
                        <div>
                          <Label htmlFor="school-board">Education Board</Label>
                          <select
                            id="school-board"
                            value={school.education_board}
                            onChange={(e) => setSchool({ ...school, education_board: e.target.value })}
                            className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                          >
                            <option value="CBSE">CBSE (Central Board of Secondary Education)</option>
                            <option value="ICSE">ICSE / ISC</option>
                            <option value="State Board">State Board</option>
                            <option value="Cambridge">Cambridge (IGCSE)</option>
                            <option value="IB">International Baccalaureate (IB)</option>
                            <option value="Other">Other / Custom</option>
                          </select>
                        </div>
                        <div>
                          <Label htmlFor="school-affiliation">Affiliation / Registration Number</Label>
                          <Input
                            id="school-affiliation"
                            value={school.affiliation_number || ""}
                            onChange={(e) => setSchool({ ...school, affiliation_number: e.target.value })}
                            placeholder="e.g. CBSE/AFF/2026/8912"
                            className="mt-1.5"
                          />
                        </div>
                        <div>
                          <Label htmlFor="school-email">Official Contact Email</Label>
                          <Input
                            id="school-email"
                            type="email"
                            value={school.email}
                            onChange={(e) => setSchool({ ...school, email: e.target.value })}
                            required
                            className="mt-1.5"
                          />
                        </div>
                        <div>
                          <Label htmlFor="school-phone">Official Phone</Label>
                          <Input
                            id="school-phone"
                            value={school.phone || ""}
                            onChange={(e) => setSchool({ ...school, phone: e.target.value })}
                            className="mt-1.5"
                          />
                        </div>
                      </div>

                      <div className="pt-4 flex justify-end">
                        <Button type="submit" disabled={saving} className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                          Save Changes
                        </Button>
                      </div>
                    </form>
                  </CardContent>
                </Card>
              )}

              {/* Tab 2: Branches */}
              {activeTab === "branches" && (
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between pb-3">
                    <div>
                      <CardTitle>Campuses & Branches</CardTitle>
                      <CardDescription>
                        Multi-campus setup allows isolated class scheduling, attendance, and fee tracking.
                      </CardDescription>
                    </div>
                    <Button onClick={() => setShowAddBranchModal(true)} className="gap-2">
                      <Plus className="h-4 w-4" />
                      Add Branch
                    </Button>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Branch Name</TableHead>
                          <TableHead>Code</TableHead>
                          <TableHead>Campus Type</TableHead>
                          <TableHead>Contact</TableHead>
                          <TableHead>Status</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {branches.map((b) => (
                          <TableRow key={b.id}>
                            <TableCell className="font-semibold text-slate-900 dark:text-white">
                              {b.name}
                            </TableCell>
                            <TableCell>{b.branch_code}</TableCell>
                            <TableCell className="capitalize">{b.campus_type || "Main"}</TableCell>
                            <TableCell className="text-sm text-slate-500">
                              {b.email || b.phone || "—"}
                            </TableCell>
                            <TableCell>
                              <Badge variant={b.is_active ? "default" : "secondary"}>
                                {b.is_active ? "Active" : "Inactive"}
                              </Badge>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              )}

              {/* Tab 3: Preferences */}
              {activeTab === "preferences" && (
                <Card>
                  <CardHeader>
                    <CardTitle>Operational Preferences</CardTitle>
                    <CardDescription>Configure system-wide regional and operational behaviors.</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <form onSubmit={handleSavePreferences} className="space-y-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <Label htmlFor="pref-currency">Default Currency</Label>
                          <select
                            id="pref-currency"
                            value={preferences.currency}
                            onChange={(e) => setPreferences({ ...preferences, currency: e.target.value })}
                            className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                          >
                            <option value="INR">INR (₹ - Indian Rupee)</option>
                            <option value="USD">USD ($ - US Dollar)</option>
                            <option value="GBP">GBP (£ - British Pound)</option>
                            <option value="AED">AED (د.إ - UAE Dirham)</option>
                          </select>
                        </div>

                        <div>
                          <Label htmlFor="pref-timezone">Time Zone</Label>
                          <select
                            id="pref-timezone"
                            value={preferences.timezone}
                            onChange={(e) => setPreferences({ ...preferences, timezone: e.target.value })}
                            className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                          >
                            <option value="Asia/Kolkata">Asia/Kolkata (IST +5:30)</option>
                            <option value="Asia/Dubai">Asia/Dubai (GST +4:00)</option>
                            <option value="UTC">UTC (+0:00)</option>
                            <option value="America/New_York">America/New_York (EST -5:00)</option>
                          </select>
                        </div>

                        <div>
                          <Label htmlFor="pref-start-month">Academic Year Starting Month</Label>
                          <select
                            id="pref-start-month"
                            value={preferences.academic_start_month}
                            onChange={(e) => setPreferences({ ...preferences, academic_start_month: e.target.value })}
                            className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                          >
                            <option value="April">April (Standard Indian CBSE/ICSE)</option>
                            <option value="January">January (Calendar Year)</option>
                            <option value="June">June (State Boards)</option>
                            <option value="September">September (International / Autumn)</option>
                          </select>
                        </div>
                      </div>

                      <div className="pt-4 flex justify-end">
                        <Button type="submit" disabled={saving} className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                          Save Preferences
                        </Button>
                      </div>
                    </form>
                  </CardContent>
                </Card>
              )}

              {/* Tab 4: Subscription & Billing */}
              {activeTab === "subscription" && (
                <div className="space-y-6">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card className="p-6 bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md">
                      <div className="flex justify-between items-start">
                        <div>
                          <div className="text-xs font-semibold tracking-wider uppercase text-indigo-100">
                            Current SaaS Plan
                          </div>
                          <div className="text-2xl font-bold mt-1">
                            {subscriptionInfo?.subscription?.plan?.name || "Free Trial"}
                          </div>
                        </div>
                        <Badge className="bg-white/20 text-white border-none">
                          {subscriptionInfo?.tenant?.status?.toUpperCase()}
                        </Badge>
                      </div>

                      <div className="mt-6 flex items-baseline gap-2">
                        <span className="text-3xl font-extrabold">
                          {subscriptionInfo?.days_remaining ?? 0}
                        </span>
                        <span className="text-indigo-100 text-sm">days remaining</span>
                      </div>

                      <div className="text-xs text-indigo-100 mt-2">
                        Cycle: {subscriptionInfo?.subscription?.billing_cycle || "Trial"} | Renews:{" "}
                        {subscriptionInfo?.subscription?.current_period_end
                          ? new Date(subscriptionInfo.subscription.current_period_end).toLocaleDateString()
                          : subscriptionInfo?.tenant?.trial_ends_at
                          ? new Date(subscriptionInfo.tenant.trial_ends_at).toLocaleDateString()
                          : "N/A"}
                      </div>
                    </Card>

                    <Card className="p-6">
                      <div className="text-sm font-semibold text-slate-500">Resource Consumption</div>
                      <div className="mt-4 space-y-3">
                        <div>
                          <div className="flex justify-between text-xs mb-1">
                            <span>Students Enrolled</span>
                            <span className="font-bold">
                              {subscriptionInfo?.usage?.students || 0} /{" "}
                              {subscriptionInfo?.usage?.limits?.max_students || "∞"}
                            </span>
                          </div>
                          <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div className="bg-indigo-600 h-full w-[25%]" />
                          </div>
                        </div>
                        <div>
                          <div className="flex justify-between text-xs mb-1">
                            <span>Staff Members</span>
                            <span className="font-bold">
                              {subscriptionInfo?.usage?.staff || 0} /{" "}
                              {subscriptionInfo?.usage?.limits?.max_staff || "∞"}
                            </span>
                          </div>
                          <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div className="bg-emerald-600 h-full w-[15%]" />
                          </div>
                        </div>
                      </div>
                    </Card>

                    <Card className="p-6 flex flex-col justify-between">
                      <div>
                        <div className="text-sm font-semibold text-slate-500">Plan Actions</div>
                        <p className="text-xs text-slate-500 mt-1">
                          Need higher student caps or additional campuses? Upgrade anytime.
                        </p>
                      </div>
                      <Button className="w-full bg-indigo-600 hover:bg-indigo-700 text-white mt-4">
                        Upgrade Subscription
                      </Button>
                    </Card>
                  </div>

                  <Card>
                    <CardHeader>
                      <CardTitle className="text-lg">Subscription Invoices</CardTitle>
                      <CardDescription>Payment receipts and transaction records for this school.</CardDescription>
                    </CardHeader>
                    <CardContent>
                      {invoices.length === 0 ? (
                        <div className="py-8 text-center text-slate-400">No invoices generated yet.</div>
                      ) : (
                        <Table>
                          <TableHeader>
                            <TableRow>
                              <TableHead>Invoice #</TableHead>
                              <TableHead>Date</TableHead>
                              <TableHead>Amount</TableHead>
                              <TableHead>Status</TableHead>
                            </TableRow>
                          </TableHeader>
                          <TableBody>
                            {invoices.map((inv) => (
                              <TableRow key={inv.id}>
                                <TableCell className="font-semibold">{inv.invoice_number}</TableCell>
                                <TableCell>{inv.paid_at || inv.created_at}</TableCell>
                                <TableCell>
                                  {inv.currency} {inv.total_amount}
                                </TableCell>
                                <TableCell>
                                  <Badge variant={inv.status === "paid" ? "default" : "secondary"}>
                                    {inv.status}
                                  </Badge>
                                </TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      )}
                    </CardContent>
                  </Card>
                </div>
              )}
            </>
          )}

          {/* Add Branch Modal */}
          {showAddBranchModal && (
            <Modal
              isOpen={showAddBranchModal}
              onClose={() => setShowAddBranchModal(false)}
              title="Add New Campus Branch"
            >
              <form onSubmit={handleAddBranch} className="space-y-4">
                <div>
                  <Label htmlFor="branch-name">Branch Name</Label>
                  <Input
                    id="branch-name"
                    value={newBranch.name}
                    onChange={(e) => setNewBranch({ ...newBranch, name: e.target.value })}
                    placeholder="e.g. North Campus"
                    required
                    className="mt-1.5"
                  />
                </div>
                <div>
                  <Label htmlFor="branch-code">Branch Code</Label>
                  <Input
                    id="branch-code"
                    value={newBranch.branch_code}
                    onChange={(e) => setNewBranch({ ...newBranch, branch_code: e.target.value })}
                    placeholder="e.g. BR-NORTH"
                    required
                    className="mt-1.5"
                  />
                </div>
                <div>
                  <Label htmlFor="campus-type">Campus Type</Label>
                  <select
                    id="campus-type"
                    value={newBranch.campus_type}
                    onChange={(e) => setNewBranch({ ...newBranch, campus_type: e.target.value })}
                    className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                  >
                    <option value="main">Main Campus</option>
                    <option value="secondary">Secondary Campus</option>
                    <option value="kindergarten">Kindergarten / Junior Wing</option>
                    <option value="sports_complex">Sports / Activity Complex</option>
                  </select>
                </div>
                <div>
                  <Label htmlFor="branch-email">Branch Email</Label>
                  <Input
                    id="branch-email"
                    type="email"
                    value={newBranch.email}
                    onChange={(e) => setNewBranch({ ...newBranch, email: e.target.value })}
                    className="mt-1.5"
                  />
                </div>

                <div className="pt-4 flex justify-end gap-2">
                  <Button type="button" variant="outline" onClick={() => setShowAddBranchModal(false)}>
                    Cancel
                  </Button>
                  <Button type="submit" disabled={saving} className="bg-indigo-600 hover:bg-indigo-700">
                    {saving ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : null}
                    Create Branch
                  </Button>
                </div>
              </form>
            </Modal>
          )}
        </main>
      </div>
    </div>
  );
}

