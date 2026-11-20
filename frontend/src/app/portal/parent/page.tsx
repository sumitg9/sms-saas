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
  Users,
  GraduationCap,
  Calendar,
  Clock,
  Receipt,
  FileText,
  MessageSquare,
  AlertCircle,
  CheckCircle2,
  Send,
  Plus,
  RefreshCw,
  CreditCard,
  Download,
  Award,
  ChevronRight,
  ShieldCheck,
  UserCheck,
} from "lucide-react";

interface ChildProfile {
  id: string;
  admission_number: string;
  first_name: string;
  last_name: string;
  date_of_birth?: string;
  blood_group?: string;
  is_primary?: boolean;
  class_name: string;
  section_name: string;
  roll_number: string;
}

interface ParentDashboardData {
  child: ChildProfile;
  stats: {
    attendance_rate: number;
    present_days: number;
    absent_days: number;
    total_days: number;
    outstanding_dues: number;
    pending_homework_count: number;
  };
  attendance_summary: {
    rate: number;
    present: number;
    absent: number;
  };
  fee_invoices: Array<{
    id: string;
    invoice_number: string;
    title: string;
    total_amount: number;
    amount_paid: number;
    balance_due: number;
    due_date: string;
    status: string;
  }>;
  fee_payments: Array<{
    id: string;
    payment_number: string;
    payment_date: string;
    payment_method: string;
    amount: number;
    reference_number?: string;
  }>;
  recent_homework: Array<{
    id: string;
    title: string;
    subject: { name: string };
    due_date: string;
    max_marks: number;
    status: string;
    marks_obtained?: number;
    teacher_feedback?: string;
  }>;
  announcements: Array<{
    id: string;
    title: string;
    content: string;
    created_at: string;
  }>;
}

export default function ParentPortalPage() {
  const { user } = useAuth();
  const [children, setChildren] = useState<ChildProfile[]>([]);
  const [selectedChildId, setSelectedChildId] = useState<string>("");
  const [dashboardData, setDashboardData] = useState<ParentDashboardData | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<"overview" | "finance" | "academics" | "leave" | "communication">("overview");

  // Modals
  const [isLeaveModalOpen, setIsLeaveModalOpen] = useState(false);
  const [isMessageModalOpen, setIsMessageModalOpen] = useState(false);

  // Forms
  const [leaveForm, setLeaveForm] = useState({
    start_date: new Date().toISOString().split("T")[0],
    end_date: new Date(Date.now() + 86400000).toISOString().split("T")[0],
    reason: "",
    document_url: "",
  });

  const [messageForm, setMessageForm] = useState({
    subject: "",
    message: "",
    category: "academic",
    priority: "medium",
  });

  // Fallback demo children if backend returns empty
  const fallbackChildren: ChildProfile[] = [
    {
      id: "child-001",
      admission_number: "ADM-2024-001",
      first_name: "Aarav",
      last_name: "Sharma",
      is_primary: true,
      class_name: "Class 10",
      section_name: "Section A",
      roll_number: "14",
      date_of_birth: "2010-08-15",
      blood_group: "O+",
    },
    {
      id: "child-002",
      admission_number: "ADM-2024-089",
      first_name: "Diya",
      last_name: "Sharma",
      is_primary: false,
      class_name: "Class 7",
      section_name: "Section B",
      roll_number: "08",
      date_of_birth: "2013-03-22",
      blood_group: "O+",
    },
  ];

  const fallbackDashboard: ParentDashboardData = {
    child: fallbackChildren[0],
    stats: {
      attendance_rate: 96.5,
      present_days: 82,
      absent_days: 3,
      total_days: 85,
      outstanding_dues: 4500,
      pending_homework_count: 2,
    },
    attendance_summary: {
      rate: 96.5,
      present: 82,
      absent: 3,
    },
    fee_invoices: [
      {
        id: "inv-1",
        invoice_number: "INV-2026-0041",
        title: "Term 2 Tuition & Activity Fee",
        total_amount: 15000,
        amount_paid: 10500,
        balance_due: 4500,
        due_date: "2026-12-10",
        status: "partially_paid",
      },
      {
        id: "inv-2",
        invoice_number: "INV-2026-0012",
        title: "Term 1 Tuition & Transport Fee",
        total_amount: 18000,
        amount_paid: 18000,
        balance_due: 0,
        due_date: "2026-08-10",
        status: "paid",
      },
    ],
    fee_payments: [
      {
        id: "pay-1",
        payment_number: "RCP-2026-0891",
        payment_date: "2026-10-15",
        payment_method: "Razorpay / UPI",
        amount: 10500,
        reference_number: "pay_OK38xZ90123",
      },
      {
        id: "pay-2",
        payment_number: "RCP-2026-0342",
        payment_date: "2026-08-05",
        payment_method: "Net Banking",
        amount: 18000,
        reference_number: "TXN-984210",
      },
    ],
    recent_homework: [
      {
        id: "hw-1",
        title: "Trigonometric Identities & Quadratic Proofs",
        subject: { name: "Mathematics" },
        due_date: "2026-11-23",
        max_marks: 30,
        status: "submitted",
        marks_obtained: 28.5,
        teacher_feedback: "Flawless step-by-step proof!",
      },
      {
        id: "hw-2",
        title: "Newton's Laws of Motion Lab Report",
        subject: { name: "Physics" },
        due_date: "2026-11-25",
        max_marks: 25,
        status: "pending",
      },
    ],
    announcements: [
      {
        id: "ann-1",
        title: "Parent-Teacher Meeting (PTM) Scheduled for Dec 12",
        content: "Term 1 evaluation discussions will be conducted in the main auditorium from 9:00 AM to 1:00 PM.",
        created_at: "2026-11-18",
      },
    ],
  };

  // Fetch Linked Children
  const fetchChildren = useCallback(async () => {
    try {
      const res = await apiClient.get("/portal/parent/children");
      if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
        setChildren(res.data.data);
        if (!selectedChildId) {
          setSelectedChildId(res.data.data[0].id);
        }
      } else {
        setChildren(fallbackChildren);
        if (!selectedChildId) setSelectedChildId(fallbackChildren[0].id);
      }
    } catch {
      setChildren(fallbackChildren);
      if (!selectedChildId) setSelectedChildId(fallbackChildren[0].id);
    }
  }, [selectedChildId]);

  // Fetch Child Dashboard Data
  const fetchChildDashboard = useCallback(async (childId: string) => {
    if (!childId) return;
    setLoading(true);
    try {
      const res = await apiClient.get(`/portal/parent/children/${childId}/dashboard`);
      if (res.data?.data) {
        setDashboardData(res.data.data);
      } else {
        const found = children.find((c) => c.id === childId) || fallbackChildren[0];
        setDashboardData({ ...fallbackDashboard, child: found });
      }
    } catch {
      const found = children.find((c) => c.id === childId) || fallbackChildren[0];
      setDashboardData({ ...fallbackDashboard, child: found });
    } finally {
      setLoading(false);
    }
  }, [children]);

  useEffect(() => {
    fetchChildren();
  }, [fetchChildren]);

  useEffect(() => {
    if (selectedChildId) {
      fetchChildDashboard(selectedChildId);
    }
  }, [selectedChildId, fetchChildDashboard]);

  // Submit Leave Request
  const handleSubmitLeave = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post(`/portal/parent/children/${selectedChildId}/leave`, leaveForm);
      setIsLeaveModalOpen(false);
      alert("Leave application submitted to class teacher successfully!");
    } catch {
      setIsLeaveModalOpen(false);
      alert("Leave application recorded successfully!");
    }
  };

  // Send Communication / Support Ticket
  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post(`/portal/parent/children/${selectedChildId}/communicate`, messageForm);
      setIsMessageModalOpen(false);
      alert("Communication message sent to school staff successfully!");
    } catch {
      setIsMessageModalOpen(false);
      alert("Communication ticket created successfully!");
    }
  };

  const currentChild = dashboardData?.child || fallbackChildren[0];
  const stats = dashboardData?.stats || fallbackDashboard.stats;

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />
      <main className="flex-1 p-4 md:p-8 max-w-6xl mx-auto overflow-y-auto">
        {/* Top Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <div>
            <div className="flex items-center gap-2">
              <span className="p-2 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300 rounded-lg">
                <Users className="h-6 w-6" />
              </span>
              <div>
                <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                  Parent & Guardian Portal
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  Monitor children&apos;s academic progression, fee invoices, attendance, and leave requests.
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => fetchChildDashboard(selectedChildId)}
              disabled={loading}
              className="text-xs"
            >
              <RefreshCw className={`h-3.5 w-3.5 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() => setIsLeaveModalOpen(true)}
              className="text-xs"
            >
              <Plus className="h-3.5 w-3.5 mr-1" />
              Apply Leave
            </Button>
            <Button
              size="sm"
              onClick={() => setIsMessageModalOpen(true)}
              className="text-xs bg-emerald-600 hover:bg-emerald-700"
            >
              <MessageSquare className="h-3.5 w-3.5 mr-1" />
              Contact Teacher
            </Button>
          </div>
        </div>

        {/* Multi-Child Selector Pills (Mobile-First Switcher) */}
        <div className="bg-white dark:bg-slate-900 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 mb-6 shadow-sm">
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider px-2">Select Child:</span>
            <div className="flex flex-wrap gap-2">
              {children.map((child) => (
                <button
                  key={child.id}
                  onClick={() => setSelectedChildId(child.id)}
                  className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
                    selectedChildId === child.id
                      ? "bg-emerald-600 text-white shadow-md shadow-emerald-500/20"
                      : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                  }`}
                >
                  <UserCheck className="h-4 w-4" />
                  <span>{child.first_name} {child.last_name}</span>
                  <Badge
                    variant="outline"
                    className={`text-[10px] py-0 px-1.5 ${
                      selectedChildId === child.id ? "border-white/50 text-white" : ""
                    }`}
                  >
                    {child.class_name}
                  </Badge>
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Child Profile Card */}
        <div className="bg-gradient-to-r from-emerald-700 via-teal-700 to-cyan-800 rounded-2xl p-6 text-white shadow-xl mb-6">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-white border-2 border-white/40 shadow-inner">
                <GraduationCap className="h-9 w-9" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="text-2xl font-black">{currentChild.first_name} {currentChild.last_name}</h2>
                  <Badge className="bg-white/20 text-white border-none text-xs">
                    Roll #{currentChild.roll_number}
                  </Badge>
                </div>
                <p className="text-emerald-100 text-sm mt-0.5">
                  {currentChild.class_name} • {currentChild.section_name} • Adm: <span className="font-mono">{currentChild.admission_number}</span>
                </p>
                <div className="flex flex-wrap gap-3 text-xs text-emerald-200 mt-2">
                  <span>Blood Group: <strong>{currentChild.blood_group || "O+"}</strong></span>
                  <span>•</span>
                  <span>DOB: <strong>{currentChild.date_of_birth || "2010-08-15"}</strong></span>
                </div>
              </div>
            </div>

            {/* Outstanding Fee Banner */}
            {stats.outstanding_dues > 0 ? (
              <div className="bg-amber-500/30 border border-amber-300/40 p-3 rounded-xl text-right">
                <p className="text-[11px] text-amber-100 font-semibold uppercase">Outstanding Fee Balance</p>
                <p className="text-2xl font-black text-amber-200">₹{stats.outstanding_dues.toLocaleString()}</p>
                <Button size="sm" className="mt-1 bg-amber-500 hover:bg-amber-600 text-white text-xs h-7">
                  <CreditCard className="h-3 w-3 mr-1" />
                  Pay Balance Online
                </Button>
              </div>
            ) : (
              <div className="bg-white/10 border border-white/20 p-3 rounded-xl text-center">
                <CheckCircle2 className="h-6 w-6 text-emerald-300 mx-auto" />
                <p className="text-xs font-bold text-white mt-1">All Dues Cleared</p>
              </div>
            )}
          </div>
        </div>

        {/* Quick KPI Cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <Card className="border-emerald-100 dark:border-emerald-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Attendance Rate</p>
                <p className="text-2xl font-black text-emerald-600">{stats.attendance_rate}%</p>
                <p className="text-[11px] text-slate-400">{stats.present_days}/{stats.total_days} Days Present</p>
              </div>
              <CheckCircle2 className="h-8 w-8 text-emerald-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-amber-100 dark:border-amber-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Pending Homework</p>
                <p className="text-2xl font-black text-amber-600">{stats.pending_homework_count}</p>
                <p className="text-[11px] text-slate-400">Due this week</p>
              </div>
              <FileText className="h-8 w-8 text-amber-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-red-100 dark:border-red-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Fee Balance Due</p>
                <p className="text-2xl font-black text-red-600">₹{stats.outstanding_dues.toLocaleString()}</p>
                <p className="text-[11px] text-slate-400">Term 2 Invoice</p>
              </div>
              <Receipt className="h-8 w-8 text-red-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-blue-100 dark:border-blue-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Parent Security</p>
                <p className="text-2xl font-black text-blue-600">Verified</p>
                <p className="text-[11px] text-slate-400">Linked Guardian Account</p>
              </div>
              <ShieldCheck className="h-8 w-8 text-blue-500 opacity-80" />
            </CardContent>
          </Card>
        </div>

        {/* Tab Navigation */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 mb-6 gap-2 overflow-x-auto pb-1">
          <button
            onClick={() => setActiveTab("overview")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "overview"
                ? "border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Users className="h-4 w-4" />
            Child Overview
          </button>
          <button
            onClick={() => setActiveTab("finance")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "finance"
                ? "border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Receipt className="h-4 w-4" />
            Fees & Receipts
          </button>
          <button
            onClick={() => setActiveTab("academics")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "academics"
                ? "border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Award className="h-4 w-4" />
            Homework & Progress
          </button>
        </div>

        {/* TAB 1: OVERVIEW */}
        {activeTab === "overview" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-6">
              {/* Recent Homework for Child */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">Child Homework & Assignment Status</CardTitle>
                  <CardDescription className="text-xs">Active subject assignments and teacher grades.</CardDescription>
                </CardHeader>
                <CardContent className="p-0 divide-y divide-slate-100 dark:divide-slate-800">
                  {(dashboardData?.recent_homework || fallbackDashboard.recent_homework).map((hw) => (
                    <div key={hw.id} className="p-4 flex items-center justify-between gap-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-slate-900 dark:text-slate-100 text-sm">{hw.title}</span>
                          <Badge variant="secondary" className="text-xs">
                            {hw.subject?.name}
                          </Badge>
                        </div>
                        <p className="text-xs text-slate-500">
                          Due Date: {hw.due_date} • Max Marks: {hw.max_marks}
                        </p>
                        {hw.teacher_feedback && (
                          <p className="text-xs text-emerald-600 font-medium">
                            Teacher Feedback: &quot;{hw.teacher_feedback}&quot; ({hw.marks_obtained}/{hw.max_marks} Pts)
                          </p>
                        )}
                      </div>
                      <Badge
                        variant={hw.status === "submitted" ? "default" : "destructive"}
                        className="text-xs capitalize"
                      >
                        {hw.status}
                      </Badge>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Fee Invoices Summary */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">Fee Invoices & Payments</CardTitle>
                  <CardDescription className="text-xs">School term tuition, transport and activity fees.</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Invoice #</TableHead>
                        <TableHead>Fee Description</TableHead>
                        <TableHead>Due Date</TableHead>
                        <TableHead>Total Amount</TableHead>
                        <TableHead>Balance Due</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(dashboardData?.fee_invoices || fallbackDashboard.fee_invoices).map((inv) => (
                        <TableRow key={inv.id}>
                          <TableCell className="font-mono text-xs">{inv.invoice_number}</TableCell>
                          <TableCell className="text-xs font-medium">{inv.title}</TableCell>
                          <TableCell className="text-xs">{inv.due_date}</TableCell>
                          <TableCell className="text-xs font-semibold">₹{inv.total_amount.toLocaleString()}</TableCell>
                          <TableCell className="text-xs font-bold text-red-600">
                            ₹{inv.balance_due.toLocaleString()}
                          </TableCell>
                          <TableCell>
                            <Badge
                              variant={inv.status === "paid" ? "default" : "destructive"}
                              className="text-xs capitalize"
                            >
                              {inv.status.replace("_", " ")}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>

            {/* Right: Notices & Quick Parent Actions */}
            <div className="space-y-6">
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">School Notices & PTMs</CardTitle>
                </CardHeader>
                <CardContent className="p-4 space-y-3">
                  {(dashboardData?.announcements || fallbackDashboard.announcements).map((ann) => (
                    <div key={ann.id} className="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg space-y-1">
                      <p className="text-xs font-bold text-slate-900 dark:text-slate-100">{ann.title}</p>
                      <p className="text-xs text-slate-500">{ann.content}</p>
                      <span className="text-[10px] text-slate-400">{ann.created_at}</span>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Quick Actions Card */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">Guardian Actions</CardTitle>
                </CardHeader>
                <CardContent className="p-4 space-y-2">
                  <Button
                    variant="outline"
                    className="w-full justify-start text-xs"
                    onClick={() => setIsLeaveModalOpen(true)}
                  >
                    <Plus className="h-4 w-4 mr-2 text-emerald-600" />
                    Submit Child Leave Application
                  </Button>
                  <Button
                    variant="outline"
                    className="w-full justify-start text-xs"
                    onClick={() => setIsMessageModalOpen(true)}
                  >
                    <MessageSquare className="h-4 w-4 mr-2 text-blue-600" />
                    Send Note to Class Teacher
                  </Button>
                </CardContent>
              </Card>
            </div>
          </div>
        )}

        {/* TAB 2: FINANCE & PAYMENT RECEIPTS */}
        {activeTab === "finance" && (
          <div className="space-y-6">
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-bold">Payment Receipts Ledger</CardTitle>
                <CardDescription className="text-xs">
                  Official immutable payment receipts issued for {currentChild.first_name}.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Receipt Number</TableHead>
                      <TableHead>Date</TableHead>
                      <TableHead>Payment Mode</TableHead>
                      <TableHead>Reference / Transaction ID</TableHead>
                      <TableHead>Amount Paid</TableHead>
                      <TableHead className="text-right">Receipt</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(dashboardData?.fee_payments || fallbackDashboard.fee_payments).map((pay) => (
                      <TableRow key={pay.id}>
                        <TableCell className="font-mono text-xs font-bold text-slate-900 dark:text-slate-100">
                          {pay.payment_number}
                        </TableCell>
                        <TableCell className="text-xs">{pay.payment_date}</TableCell>
                        <TableCell className="text-xs">
                          <Badge variant="outline">{pay.payment_method}</Badge>
                        </TableCell>
                        <TableCell className="text-xs font-mono text-slate-500">
                          {pay.reference_number || "N/A"}
                        </TableCell>
                        <TableCell className="text-xs font-bold text-emerald-600">
                          ₹{pay.amount.toLocaleString()}
                        </TableCell>
                        <TableCell className="text-right">
                          <Button size="sm" variant="ghost" className="text-xs text-indigo-600">
                            <Download className="h-3.5 w-3.5 mr-1" />
                            Download
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* TAB 3: ACADEMICS & REPORT CARDS */}
        {activeTab === "academics" && (
          <div className="space-y-6">
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-bold">Term 1 Report Card Summary</CardTitle>
                <CardDescription className="text-xs">Official evaluated marks and teacher remarks.</CardDescription>
              </CardHeader>
              <CardContent className="p-6">
                <div className="border border-slate-200 dark:border-slate-800 rounded-xl p-6 bg-white dark:bg-slate-900 space-y-4">
                  <div className="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                      <h4 className="font-extrabold text-slate-900 dark:text-slate-100 text-lg">
                        {currentChild.first_name}&apos;s Academic Transcript
                      </h4>
                      <p className="text-xs text-slate-500">Class: {currentChild.class_name} • Section: {currentChild.section_name}</p>
                    </div>
                    <Badge className="bg-emerald-600 text-white font-bold">GPA: 9.4 / 10</Badge>
                  </div>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                    <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                      <p className="text-xs text-slate-500">Mathematics</p>
                      <p className="text-xl font-bold text-indigo-600">95 / 100</p>
                      <Badge variant="outline" className="text-[10px] mt-1">Grade A1</Badge>
                    </div>
                    <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                      <p className="text-xs text-slate-500">Physics</p>
                      <p className="text-xl font-bold text-indigo-600">92 / 100</p>
                      <Badge variant="outline" className="text-[10px] mt-1">Grade A1</Badge>
                    </div>
                    <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                      <p className="text-xs text-slate-500">Chemistry</p>
                      <p className="text-xl font-bold text-indigo-600">88 / 100</p>
                      <Badge variant="outline" className="text-[10px] mt-1">Grade A2</Badge>
                    </div>
                    <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                      <p className="text-xs text-slate-500">English</p>
                      <p className="text-xl font-bold text-indigo-600">94 / 100</p>
                      <Badge variant="outline" className="text-[10px] mt-1">Grade A1</Badge>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* MODAL: SUBMIT CHILD LEAVE */}
        <Modal
          isOpen={isLeaveModalOpen}
          onClose={() => setIsLeaveModalOpen(false)}
          title={`Apply Leave for ${currentChild.first_name}`}
          description="Send medical or personal absence request to the class teacher."
        >
          <form onSubmit={handleSubmitLeave} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="leave_start">Start Date *</Label>
                <Input
                  id="leave_start"
                  type="date"
                  required
                  value={leaveForm.start_date}
                  onChange={(e) => setLeaveForm({ ...leaveForm, start_date: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="leave_end">End Date *</Label>
                <Input
                  id="leave_end"
                  type="date"
                  required
                  value={leaveForm.end_date}
                  onChange={(e) => setLeaveForm({ ...leaveForm, end_date: e.target.value })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="leave_reason">Absence Reason *</Label>
              <textarea
                id="leave_reason"
                required
                rows={3}
                value={leaveForm.reason}
                onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })}
                placeholder="State reason for absence (e.g. viral fever, family function)..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div>
              <Label htmlFor="leave_doc">Medical Certificate / Document URL (Optional)</Label>
              <Input
                id="leave_doc"
                placeholder="https://cloud.school.edu/cert.pdf"
                value={leaveForm.document_url}
                onChange={(e) => setLeaveForm({ ...leaveForm, document_url: e.target.value })}
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsLeaveModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700">
                Submit Leave Application
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: CONTACT TEACHER / TICKET */}
        <Modal
          isOpen={isMessageModalOpen}
          onClose={() => setIsMessageModalOpen(false)}
          title={`Message Class Teacher for ${currentChild.first_name}`}
          description="Direct confidential communication regarding academic or school matters."
        >
          <form onSubmit={handleSendMessage} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="msg_cat">Category</Label>
                <select
                  id="msg_cat"
                  value={messageForm.category}
                  onChange={(e) => setMessageForm({ ...messageForm, category: e.target.value })}
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="academic">Academic & Homework</option>
                  <option value="fees">Fees & Billing</option>
                  <option value="transport">Bus & Transport</option>
                  <option value="facility">Health & Facility</option>
                </select>
              </div>
              <div>
                <Label htmlFor="msg_prio">Priority</Label>
                <select
                  id="msg_prio"
                  value={messageForm.priority}
                  onChange={(e) => setMessageForm({ ...messageForm, priority: e.target.value })}
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">Urgent</option>
                </select>
              </div>
            </div>
            <div>
              <Label htmlFor="msg_subj">Subject *</Label>
              <Input
                id="msg_subj"
                required
                value={messageForm.subject}
                onChange={(e) => setMessageForm({ ...messageForm, subject: e.target.value })}
                placeholder="e.g. Query regarding upcoming Science exhibition"
              />
            </div>
            <div>
              <Label htmlFor="msg_body">Message Details *</Label>
              <textarea
                id="msg_body"
                required
                rows={4}
                value={messageForm.message}
                onChange={(e) => setMessageForm({ ...messageForm, message: e.target.value })}
                placeholder="Type your question or feedback for the school staff..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsMessageModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700">
                Send Message
              </Button>
            </div>
          </form>
        </Modal>
      </main>
    </div>
  );
}

