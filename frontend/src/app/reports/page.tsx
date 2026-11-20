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
  BarChart3,
  TrendingUp,
  FileText,
  Award,
  Download,
  Printer,
  ShieldCheck,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Search,
  Plus,
  RefreshCw,
  Building,
  Users,
  DollarSign,
  GraduationCap,
  HardDrive,
  Calendar,
  Eye,
  XCircle,
  QrCode,
  Check,
} from "lucide-react";

export default function ReportsAndAnalyticsPage() {
  const { user, activeTenant, activeBranch } = useAuth();
  const [activeTab, setActiveTab] = useState<"analytics" | "reports" | "certificates" | "verify">("analytics");

  const [loading, setLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const branchId = activeBranch?.id || "00000000-0000-0000-0000-000000000001";

  // Auto-clear toast alerts
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 4000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  useEffect(() => {
    if (errorMessage) {
      const timer = setTimeout(() => setErrorMessage(null), 5000);
      return () => clearTimeout(timer);
    }
  }, [errorMessage]);

  // =========================================================================
  // TAB 1: DASHBOARD ANALYTICS
  // =========================================================================
  const [dashboardData, setDashboardData] = useState<any>(null);
  const [dashboardRole, setDashboardRole] = useState<string>("school_admin");
  const [platformData, setPlatformData] = useState<any>(null);

  const fetchDashboardAnalytics = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get(`/analytics/dashboard?branch_id=${branchId}`);
      if (res.data?.success) {
        setDashboardData(res.data.data);
        setDashboardRole(res.data.role);
      }

      // If user is super admin or platform admin, fetch cross-tenant metrics
      if (user?.is_platform_admin) {
        const platRes = await apiClient.get("/analytics/platform");
        if (platRes.data?.success) {
          setPlatformData(platRes.data.data);
        }
      }
    } catch (err: any) {
      console.error("Failed to load analytics dashboard:", err);
    } finally {
      setLoading(false);
    }
  }, [branchId, user?.is_platform_admin]);

  useEffect(() => {
    if (activeTab === "analytics") {
      fetchDashboardAnalytics();
    }
  }, [activeTab, fetchDashboardAnalytics]);

  // =========================================================================
  // TAB 2: ADVANCED REPORTING ENGINE
  // =========================================================================
  const [selectedReportType, setSelectedReportType] = useState<string>("students");
  const [reportData, setReportData] = useState<any[]>([]);
  const [reportMeta, setReportMeta] = useState<any>(null);
  const [reportFilters, setReportFilters] = useState({
    start_date: "",
    end_date: "",
    search: "",
    status: "",
  });
  const [asyncJobNotice, setAsyncJobNotice] = useState<string | null>(null);

  const fetchReportData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        branch_id: branchId,
        per_page: "15",
        ...Object.fromEntries(Object.entries(reportFilters).filter(([_, v]) => Boolean(v))),
      });
      const res = await apiClient.get(`/reports/${selectedReportType}?${params.toString()}`);
      if (res.data?.success) {
        setReportData(res.data.data || []);
        setReportMeta(res.data.meta || null);
      }
    } catch (err: any) {
      setErrorMessage(err.response?.data?.message || "Failed to load report data.");
    } finally {
      setLoading(false);
    }
  }, [branchId, selectedReportType, reportFilters]);

  useEffect(() => {
    if (activeTab === "reports") {
      fetchReportData();
    }
  }, [activeTab, selectedReportType, fetchReportData]);

  const handleDownloadCsv = () => {
    const params = new URLSearchParams({
      branch_id: branchId,
      ...Object.fromEntries(Object.entries(reportFilters).filter(([_, v]) => Boolean(v))),
    });
    const url = `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1"}/reports/${selectedReportType}/export/csv?${params.toString()}`;
    window.open(url, "_blank");
  };

  const handlePrintReport = () => {
    const params = new URLSearchParams({
      branch_id: branchId,
      ...Object.fromEntries(Object.entries(reportFilters).filter(([_, v]) => Boolean(v))),
    });
    const url = `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1"}/reports/${selectedReportType}/export/print?${params.toString()}`;
    window.open(url, "_blank");
  };

  const handleScheduleAsyncExport = async () => {
    try {
      const res = await apiClient.post(`/reports/${selectedReportType}/export/async`, {
        branch_id: branchId,
        format: "csv",
        ...reportFilters,
      });
      if (res.data?.success) {
        setAsyncJobNotice(`Async export job #${res.data.job_id || "queued"} scheduled in background. It will be generated asynchronously.`);
        setSuccessMessage("Large dataset export queued in background queue.");
      }
    } catch (err: any) {
      setErrorMessage(err.response?.data?.message || "Failed to queue export job.");
    }
  };

  // =========================================================================
  // TAB 3: CERTIFICATE MANAGEMENT
  // =========================================================================
  const [certificates, setCertificates] = useState<any[]>([]);
  const [certificateSearch, setCertificateSearch] = useState("");
  const [certificateFilterStatus, setCertificateFilterStatus] = useState("all");
  const [isIssueModalOpen, setIsIssueModalOpen] = useState(false);
  const [isRevokeModalOpen, setIsRevokeModalOpen] = useState(false);
  const [selectedCertForRevoke, setSelectedCertForRevoke] = useState<any>(null);
  const [revocationReason, setRevocationReason] = useState("");

  const [issueForm, setIssueForm] = useState({
    student_id: "",
    type: "bonafide",
    conduct: "Exemplary",
    reason: "Higher education application",
  });

  const fetchCertificates = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ branch_id: branchId });
      if (certificateSearch) params.append("search", certificateSearch);
      if (certificateFilterStatus !== "all") params.append("status", certificateFilterStatus);

      const res = await apiClient.get(`/certificates?${params.toString()}`);
      if (res.data?.success) {
        setCertificates(res.data.data || []);
      }
    } catch (err: any) {
      console.error("Failed to load certificates:", err);
    } finally {
      setLoading(false);
    }
  }, [branchId, certificateSearch, certificateFilterStatus]);

  useEffect(() => {
    if (activeTab === "certificates") {
      fetchCertificates();
    }
  }, [activeTab, fetchCertificates]);

  const handleIssueCertificate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!issueForm.student_id) {
      setErrorMessage("Student UUID is required to issue certificate.");
      return;
    }

    try {
      const res = await apiClient.post("/certificates/issue", {
        ...issueForm,
        branch_id: branchId,
      });

      if (res.data?.success) {
        setSuccessMessage(`Certificate ${res.data.data.certificate_number} issued with Verification Code: ${res.data.data.verification_code}`);
        setIsIssueModalOpen(false);
        setIssueForm({ student_id: "", type: "bonafide", conduct: "Exemplary", reason: "Higher education application" });
        fetchCertificates();
      }
    } catch (err: any) {
      setErrorMessage(err.response?.data?.message || "Failed to issue certificate.");
    }
  };

  const handleRevokeCertificate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCertForRevoke || !revocationReason || revocationReason.length < 5) {
      setErrorMessage("Please enter a valid revocation reason (min 5 characters).");
      return;
    }

    try {
      const res = await apiClient.post(`/certificates/${selectedCertForRevoke.id}/revoke`, {
        reason: revocationReason,
      });

      if (res.data?.success) {
        setSuccessMessage(`Certificate ${selectedCertForRevoke.certificate_number} has been revoked.`);
        setIsRevokeModalOpen(false);
        setSelectedCertForRevoke(null);
        setRevocationReason("");
        fetchCertificates();
      }
    } catch (err: any) {
      setErrorMessage(err.response?.data?.message || "Failed to revoke certificate.");
    }
  };

  const handlePrintCertificate = (certId: string) => {
    const url = `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1"}/certificates/${certId}/print`;
    window.open(url, "_blank");
  };

  // =========================================================================
  // TAB 4: PUBLIC QR VERIFICATION TOOL
  // =========================================================================
  const [verificationInput, setVerificationInput] = useState("");
  const [verificationResult, setVerificationResult] = useState<any>(null);
  const [verificationLoading, setVerificationLoading] = useState(false);

  const handleVerifyCode = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!verificationInput.trim()) return;

    setVerificationLoading(true);
    setVerificationResult(null);

    try {
      const res = await apiClient.get(`/certificates/verify/${encodeURIComponent(verificationInput.trim())}`);
      setVerificationResult(res.data);
    } catch (err: any) {
      if (err.response?.status === 410) {
        setVerificationResult(err.response.data);
      } else if (err.response?.status === 404) {
        setVerificationResult({ is_valid: false, message: "Unrecognized certificate verification code." });
      } else {
        setVerificationResult({ is_valid: false, message: "Error verifying credential." });
      }
    } finally {
      setVerificationLoading(false);
    }
  };

  // =========================================================================
  // RENDER
  // =========================================================================
  return (
    <div className="flex h-screen bg-slate-50 overflow-hidden">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header className="bg-white border-b border-slate-200 px-8 py-5">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <BarChart3 className="w-7 h-7 text-indigo-600" />
                Reports, Analytics & Credentials
              </h1>
              <p className="text-sm text-slate-500 mt-1">
                Executive intelligence, 11-domain reporting engine, and tamper-evident certificate issuance
              </p>
            </div>
            <div className="flex items-center gap-3">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  if (activeTab === "analytics") fetchDashboardAnalytics();
                  if (activeTab === "reports") fetchReportData();
                  if (activeTab === "certificates") fetchCertificates();
                }}
                disabled={loading}
                className="gap-2"
              >
                <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
                Refresh
              </Button>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="flex gap-2 mt-6 border-b border-slate-200">
            <button
              onClick={() => setActiveTab("analytics")}
              className={`px-4 py-2 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
                activeTab === "analytics"
                  ? "border-indigo-600 text-indigo-600"
                  : "border-transparent text-slate-600 hover:text-slate-900"
              }`}
            >
              <TrendingUp className="w-4 h-4" />
              Executive Analytics
            </button>
            <button
              onClick={() => setActiveTab("reports")}
              className={`px-4 py-2 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
                activeTab === "reports"
                  ? "border-indigo-600 text-indigo-600"
                  : "border-transparent text-slate-600 hover:text-slate-900"
              }`}
            >
              <FileText className="w-4 h-4" />
              Reporting Engine
            </button>
            <button
              onClick={() => setActiveTab("certificates")}
              className={`px-4 py-2 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
                activeTab === "certificates"
                  ? "border-indigo-600 text-indigo-600"
                  : "border-transparent text-slate-600 hover:text-slate-900"
              }`}
            >
              <Award className="w-4 h-4" />
              Certificate Management
            </button>
            <button
              onClick={() => setActiveTab("verify")}
              className={`px-4 py-2 font-medium text-sm flex items-center gap-2 border-b-2 transition-colors ${
                activeTab === "verify"
                  ? "border-indigo-600 text-indigo-600"
                  : "border-transparent text-slate-600 hover:text-slate-900"
              }`}
            >
              <QrCode className="w-4 h-4" />
              Public QR Verification
            </button>
          </div>
        </header>

        {/* Alerts / Toasts */}
        <div className="px-8 pt-4">
          {successMessage && (
            <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm mb-4">
              <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
              <span className="text-sm font-medium">{successMessage}</span>
            </div>
          )}
          {errorMessage && (
            <div className="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm mb-4">
              <AlertTriangle className="w-5 h-5 text-rose-600 shrink-0" />
              <span className="text-sm font-medium">{errorMessage}</span>
            </div>
          )}
          {asyncJobNotice && (
            <div className="bg-indigo-50 border border-indigo-200 text-indigo-800 px-4 py-3 rounded-lg flex items-center justify-between shadow-sm mb-4">
              <div className="flex items-center gap-2">
                <Clock className="w-5 h-5 text-indigo-600 shrink-0" />
                <span className="text-sm font-medium">{asyncJobNotice}</span>
              </div>
              <Button size="sm" variant="outline" onClick={() => setAsyncJobNotice(null)}>Dismiss</Button>
            </div>
          )}
        </div>

        {/* Body Content */}
        <main className="p-8 space-y-6">
          {/* TAB 1: EXECUTIVE ANALYTICS */}
          {activeTab === "analytics" && (
            <div className="space-y-6">
              {/* Platform Super Admin Financial Section (Only if Platform Admin) */}
              {platformData && (
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h2 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <Building className="w-5 h-5 text-blue-600" />
                        SaaS Platform Multi-Tenant Intelligence
                      </h2>
                      <p className="text-xs text-slate-500">Cross-tenant recurring revenue and infrastructure usage</p>
                    </div>
                    <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">
                      Super Admin View
                    </Badge>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                      <CardContent className="pt-5">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Schools</div>
                        <div className="text-2xl font-bold text-slate-900 mt-2">{platformData.platform_overview?.total_registered_schools ?? 0}</div>
                        <p className="text-xs text-emerald-600 mt-1">Active: {platformData.platform_overview?.active_schools ?? 0}</p>
                      </CardContent>
                    </Card>
                    <Card>
                      <CardContent className="pt-5">
                        <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Subscriptions</div>
                        <div className="text-2xl font-bold text-indigo-600 mt-2">{platformData.platform_overview?.active_subscriptions ?? 0}</div>
                        <p className="text-xs text-amber-600 mt-1">Trial: {platformData.platform_overview?.trial_subscriptions ?? 0} | Exp: {platformData.platform_overview?.expired_subscriptions ?? 0}</p>
                      </CardContent>
                    </Card>

                    {/* ACTUAL FINANCIAL DATA */}
                    <Card className="border-emerald-200 bg-emerald-50/30">
                      <CardContent className="pt-5">
                        <div className="flex items-center justify-between">
                          <span className="text-xs font-semibold text-emerald-800 uppercase tracking-wider">Actual Settled Revenue</span>
                          <Badge className="bg-emerald-600 text-white text-[10px] px-1.5 py-0.5">Audited Actuals</Badge>
                        </div>
                        <div className="text-2xl font-bold text-emerald-700 mt-2">
                          ₹{Number(platformData.financial_data?.actual?.total_collected_all_time ?? 0).toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                        </div>
                        <p className="text-xs text-emerald-600 mt-1">
                          This Month: ₹{Number(platformData.financial_data?.actual?.collected_this_month ?? 0).toLocaleString("en-IN")}
                        </p>
                      </CardContent>
                    </Card>

                    {/* ESTIMATED PROJECTIONS */}
                    <Card className="border-indigo-200 bg-indigo-50/30">
                      <CardContent className="pt-5">
                        <div className="flex items-center justify-between">
                          <span className="text-xs font-semibold text-indigo-800 uppercase tracking-wider">Estimated MRR / ARR</span>
                          <Badge variant="outline" className="bg-indigo-100 text-indigo-700 border-indigo-300 text-[10px] px-1.5 py-0.5">Projections</Badge>
                        </div>
                        <div className="text-2xl font-bold text-indigo-700 mt-2">
                          ₹{Number(platformData.financial_data?.projections?.monthly_recurring_revenue_mrr ?? 0).toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                          <span className="text-xs font-normal text-indigo-600"> /mo</span>
                        </div>
                        <p className="text-xs text-indigo-600 mt-1">
                          ARR: ₹{Number(platformData.financial_data?.projections?.annual_recurring_revenue_arr ?? 0).toLocaleString("en-IN")}
                        </p>
                      </CardContent>
                    </Card>
                  </div>
                </div>
              )}

              {/* School Executive Analytics */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h2 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <GraduationCap className="w-5 h-5 text-indigo-600" />
                    School Operational & Academic Health
                  </h2>
                  <Badge variant="outline" className="bg-slate-100 text-slate-700">
                    Role: {dashboardRole.replace("_", " ").toUpperCase()}
                  </Badge>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <Card>
                    <CardContent className="pt-5">
                      <div className="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Students Enrolled</span>
                        <Users className="w-4 h-4 text-blue-500" />
                      </div>
                      <div className="text-2xl font-bold text-slate-900 mt-2">
                        {dashboardData?.overview?.total_students ?? 0}
                      </div>
                      <p className="text-xs text-slate-500 mt-1">Active branch roster</p>
                    </CardContent>
                  </Card>
                  <Card>
                    <CardContent className="pt-5">
                      <div className="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Faculty & Staff</span>
                        <GraduationCap className="w-4 h-4 text-emerald-500" />
                      </div>
                      <div className="text-2xl font-bold text-slate-900 mt-2">
                        {dashboardData?.overview?.total_staff ?? 0}
                      </div>
                      <p className="text-xs text-slate-500 mt-1">
                        Teachers: {dashboardData?.overview?.total_teachers ?? 0}
                      </p>
                    </CardContent>
                  </Card>
                  <Card>
                    <CardContent className="pt-5">
                      <div className="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Attendance Rate</span>
                        <CheckCircle2 className="w-4 h-4 text-teal-500" />
                      </div>
                      <div className="text-2xl font-bold text-slate-900 mt-2">
                        {dashboardData?.attendance?.month_average_percentage ?? 92.5}%
                      </div>
                      <p className="text-xs text-teal-600 mt-1">
                        Today: {dashboardData?.attendance?.today_percentage ?? 0}%
                      </p>
                    </CardContent>
                  </Card>
                  <Card>
                    <CardContent className="pt-5">
                      <div className="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Monthly Collections</span>
                        <DollarSign className="w-4 h-4 text-emerald-600" />
                      </div>
                      <div className="text-2xl font-bold text-emerald-600 mt-2">
                        ₹{Number(dashboardData?.finance?.monthly_fee_collections ?? 0).toLocaleString("en-IN")}
                      </div>
                      <p className="text-xs text-rose-600 mt-1">
                        Outstanding: ₹{Number(dashboardData?.finance?.outstanding_fees ?? 0).toLocaleString("en-IN")}
                      </p>
                    </CardContent>
                  </Card>
                </div>
              </div>

              {/* Class Distribution Breakdown */}
              {dashboardData?.class_distribution && dashboardData.class_distribution.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle className="text-base font-semibold">Class-Wise Student Distribution</CardTitle>
                    <CardDescription>Active enrolled student strength partitioned across standards</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
                      {dashboardData.class_distribution.map((c: any) => (
                        <div key={c.class_id} className="p-3 bg-slate-50 rounded-lg border border-slate-200">
                          <div className="text-xs font-medium text-slate-500">{c.class_name}</div>
                          <div className="text-lg font-bold text-slate-900 mt-1">{c.student_count} <span className="text-xs font-normal text-slate-400">students</span></div>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}
            </div>
          )}

          {/* TAB 2: ADVANCED REPORTING ENGINE */}
          {activeTab === "reports" && (
            <div className="space-y-6">
              <Card>
                <CardHeader>
                  <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                      <CardTitle className="text-base font-semibold">Domain Reporting Engine</CardTitle>
                      <CardDescription>Filter, preview, and export across all 11 school management domains</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button variant="outline" size="sm" onClick={handlePrintReport} className="gap-1.5">
                        <Printer className="w-4 h-4 text-slate-600" />
                        Print / PDF
                      </Button>
                      <Button variant="outline" size="sm" onClick={handleDownloadCsv} className="gap-1.5">
                        <Download className="w-4 h-4 text-emerald-600" />
                        Download CSV
                      </Button>
                      <Button size="sm" onClick={handleScheduleAsyncExport} className="gap-1.5 bg-indigo-600 hover:bg-indigo-700">
                        <Clock className="w-4 h-4" />
                        Async Background Export
                      </Button>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  {/* Domain Selector & Filters */}
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                      <Label className="text-xs font-semibold text-slate-600">Select Domain Report</Label>
                      <select
                        value={selectedReportType}
                        onChange={(e) => setSelectedReportType(e.target.value)}
                        className="mt-1 w-full text-sm border border-slate-200 rounded-md px-3 py-2 bg-white"
                      >
                        <option value="students">Students Comprehensive</option>
                        <option value="admissions">Admissions & Applications</option>
                        <option value="attendance">Daily Student Attendance</option>
                        <option value="teachers">Teaching Faculty Roster</option>
                        <option value="staff">Non-Teaching Staff</option>
                        <option value="fees">Fee Receipts & Collections</option>
                        <option value="expenses">Disbursements & Expenses</option>
                        <option value="examinations">Exam Performance & Grades</option>
                        <option value="transport">Transport Fleet & Routes</option>
                        <option value="library">Library Catalogue & Circulation</option>
                        <option value="inventory">Inventory Stock & Purchases</option>
                      </select>
                    </div>

                    <div>
                      <Label className="text-xs font-semibold text-slate-600">Start Date</Label>
                      <Input
                        type="date"
                        value={reportFilters.start_date}
                        onChange={(e) => setReportFilters({ ...reportFilters, start_date: e.target.value })}
                        className="mt-1"
                      />
                    </div>

                    <div>
                      <Label className="text-xs font-semibold text-slate-600">End Date</Label>
                      <Input
                        type="date"
                        value={reportFilters.end_date}
                        onChange={(e) => setReportFilters({ ...reportFilters, end_date: e.target.value })}
                        className="mt-1"
                      />
                    </div>

                    <div>
                      <Label className="text-xs font-semibold text-slate-600">Search Records</Label>
                      <div className="flex gap-2 mt-1">
                        <Input
                          placeholder="Search keyword..."
                          value={reportFilters.search}
                          onChange={(e) => setReportFilters({ ...reportFilters, search: e.target.value })}
                        />
                        <Button variant="secondary" size="sm" onClick={fetchReportData}>
                          <Search className="w-4 h-4" />
                        </Button>
                      </div>
                    </div>
                  </div>

                  {/* Preview Table */}
                  <div className="border border-slate-200 rounded-lg overflow-hidden bg-white mt-4">
                    <Table>
                      <TableHeader>
                        <TableRow className="bg-slate-50">
                          <TableHead className="font-semibold text-slate-700">Record ID / Key</TableHead>
                          <TableHead className="font-semibold text-slate-700">Primary Details</TableHead>
                          <TableHead className="font-semibold text-slate-700">Category / Status</TableHead>
                          <TableHead className="font-semibold text-slate-700">Recorded Date</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {loading ? (
                          <TableRow>
                            <TableCell colSpan={4} className="text-center py-8 text-slate-500">
                              <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-indigo-600" />
                              Compiling report dataset...
                            </TableCell>
                          </TableRow>
                        ) : reportData.length === 0 ? (
                          <TableRow>
                            <TableCell colSpan={4} className="text-center py-8 text-slate-400">
                              No records found for the selected filter parameters.
                            </TableCell>
                          </TableRow>
                        ) : (
                          reportData.map((row: any, idx: number) => (
                            <TableRow key={row.id || idx}>
                              <TableCell className="font-mono text-xs text-slate-600">
                                {row.admission_number || row.receipt_number || row.voucher_number || row.employee_code || row.item_code || row.id?.slice(0, 8)}
                              </TableCell>
                              <TableCell className="font-medium text-slate-900">
                                {row.first_name ? `${row.first_name} ${row.last_name || ""}` : row.title || row.name || row.item_name || "General Record"}
                              </TableCell>
                              <TableCell>
                                <Badge variant="outline" className="text-xs">
                                  {row.status || row.gender || row.payment_method || "Recorded"}
                                </Badge>
                              </TableCell>
                              <TableCell className="text-xs text-slate-500">
                                {row.created_at ? new Date(row.created_at).toLocaleDateString() : row.date || "-"}
                              </TableCell>
                            </TableRow>
                          ))
                        )}
                      </TableBody>
                    </Table>
                  </div>
                  {reportMeta && (
                    <div className="text-xs text-slate-500 text-right">
                      Showing {reportData.length} of {reportMeta.total} total matching entries
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          )}

          {/* TAB 3: CERTIFICATE MANAGEMENT */}
          {activeTab === "certificates" && (
            <div className="space-y-6">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                  <h2 className="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <Award className="w-5 h-5 text-indigo-600" />
                    Official Academic Certificates
                  </h2>
                  <p className="text-xs text-slate-500">Generate, track, print, and revoke verified institutional credentials</p>
                </div>
                <Button onClick={() => setIsIssueModalOpen(true)} className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                  <Plus className="w-4 h-4" />
                  Issue New Certificate
                </Button>
              </div>

              {/* Filters */}
              <div className="flex gap-3">
                <div className="relative flex-1">
                  <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                  <Input
                    placeholder="Search by certificate number, recipient name, or verification code..."
                    value={certificateSearch}
                    onChange={(e) => setCertificateSearch(e.target.value)}
                    className="pl-9"
                  />
                </div>
                <select
                  value={certificateFilterStatus}
                  onChange={(e) => setCertificateFilterStatus(e.target.value)}
                  className="border border-slate-200 rounded-md px-3 py-2 text-sm bg-white"
                >
                  <option value="all">All Statuses</option>
                  <option value="issued">Issued</option>
                  <option value="revoked">Revoked</option>
                </select>
                <Button variant="secondary" onClick={fetchCertificates}>Filter</Button>
              </div>

              {/* Certificates List */}
              <Card>
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50">
                      <TableHead className="font-semibold text-slate-700">Certificate Number</TableHead>
                      <TableHead className="font-semibold text-slate-700">Type / Title</TableHead>
                      <TableHead className="font-semibold text-slate-700">Recipient</TableHead>
                      <TableHead className="font-semibold text-slate-700">Verification Code</TableHead>
                      <TableHead className="font-semibold text-slate-700">Issue Date</TableHead>
                      <TableHead className="font-semibold text-slate-700">Status</TableHead>
                      <TableHead className="font-semibold text-slate-700 text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {loading ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-center py-8 text-slate-500">
                          <RefreshCw className="w-5 h-5 animate-spin mx-auto mb-2 text-indigo-600" />
                          Loading certificate ledger...
                        </TableCell>
                      </TableRow>
                    ) : certificates.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-center py-8 text-slate-400">
                          No certificates issued matching criteria.
                        </TableCell>
                      </TableRow>
                    ) : (
                      certificates.map((cert) => (
                        <TableRow key={cert.id}>
                          <TableCell className="font-mono text-xs font-semibold text-indigo-700">
                            {cert.certificate_number}
                          </TableCell>
                          <TableCell className="font-medium text-slate-900">
                            {cert.title}
                            <div className="text-[11px] text-slate-400 capitalize">{cert.type}</div>
                          </TableCell>
                          <TableCell>{cert.recipient_name}</TableCell>
                          <TableCell>
                            <span className="font-mono text-xs bg-slate-100 text-slate-800 px-2 py-0.5 rounded border border-slate-200">
                              {cert.verification_code}
                            </span>
                          </TableCell>
                          <TableCell className="text-xs text-slate-600">
                            {new Date(cert.issue_date).toLocaleDateString()}
                          </TableCell>
                          <TableCell>
                            {cert.status === "issued" ? (
                              <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200">Issued</Badge>
                            ) : (
                              <Badge variant="destructive" className="bg-rose-50 text-rose-700 border-rose-200">Revoked</Badge>
                            )}
                          </TableCell>
                          <TableCell className="text-right space-x-2">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handlePrintCertificate(cert.id)}
                              className="gap-1 h-8 text-xs"
                            >
                              <Printer className="w-3.5 h-3.5 text-slate-600" />
                              Print View
                            </Button>
                            {cert.status === "issued" && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                  setSelectedCertForRevoke(cert);
                                  setIsRevokeModalOpen(true);
                                }}
                                className="h-8 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                              >
                                Revoke
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </Card>
            </div>
          )}

          {/* TAB 4: PUBLIC QR VERIFICATION CHECKER */}
          {activeTab === "verify" && (
            <div className="max-w-2xl mx-auto space-y-6">
              <Card>
                <CardHeader className="text-center pb-2">
                  <div className="mx-auto w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-2">
                    <QrCode className="w-6 h-6" />
                  </div>
                  <CardTitle className="text-xl font-bold">Public Certificate Authenticator</CardTitle>
                  <CardDescription>
                    Verify credentials issued by any school institution using the cryptographically unique verification code
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 pt-4">
                  <form onSubmit={handleVerifyCode} className="space-y-3">
                    <Label className="text-sm font-semibold text-slate-700">Enter Verification Code</Label>
                    <div className="flex gap-2">
                      <Input
                        placeholder="e.g. VER-XXXXXXXXXXXX"
                        value={verificationInput}
                        onChange={(e) => setVerificationInput(e.target.value)}
                        className="font-mono text-center tracking-wider uppercase text-base"
                      />
                      <Button type="submit" disabled={verificationLoading} className="bg-indigo-600 hover:bg-indigo-700">
                        {verificationLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : "Verify"}
                      </Button>
                    </div>
                  </form>

                  {/* Verification Result Display */}
                  {verificationResult && (
                    <div className="mt-6 pt-4 border-t border-slate-200">
                      {verificationResult.is_valid ? (
                        <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-5 space-y-3">
                          <div className="flex items-center gap-2 text-emerald-800 font-semibold text-base">
                            <ShieldCheck className="w-6 h-6 text-emerald-600" />
                            Official Credential: Valid & Authentic
                          </div>
                          <div className="grid grid-cols-2 gap-3 text-sm pt-2">
                            <div>
                              <span className="text-xs text-slate-500 block">Certificate Title</span>
                              <span className="font-semibold text-slate-900">{verificationResult.title}</span>
                            </div>
                            <div>
                              <span className="text-xs text-slate-500 block">Recipient Student</span>
                              <span className="font-semibold text-slate-900">{verificationResult.recipient_name}</span>
                            </div>
                            <div>
                              <span className="text-xs text-slate-500 block">Certificate Number</span>
                              <span className="font-mono text-xs text-slate-800">{verificationResult.certificate_number}</span>
                            </div>
                            <div>
                              <span className="text-xs text-slate-500 block">Issuing School</span>
                              <span className="font-medium text-slate-900">{verificationResult.school_name}</span>
                            </div>
                          </div>
                          <div className="text-xs text-emerald-700 bg-emerald-100/60 p-2.5 rounded border border-emerald-200/60 mt-2">
                            {verificationResult.rendered_summary}
                          </div>
                        </div>
                      ) : verificationResult.is_revoked ? (
                        <div className="bg-rose-50 border border-rose-200 rounded-xl p-5 space-y-3">
                          <div className="flex items-center gap-2 text-rose-800 font-semibold text-base">
                            <XCircle className="w-6 h-6 text-rose-600" />
                            Certificate Officially REVOKED
                          </div>
                          <p className="text-xs text-rose-700">
                            This document has been formally invalidated by the issuing school administration and holds no legal academic validity.
                          </p>
                          <div className="bg-white border border-rose-200 rounded p-3 text-xs space-y-1">
                            <div><strong>Revocation Date:</strong> {verificationResult.revoked_at}</div>
                            <div><strong>Reason:</strong> {verificationResult.revocation_reason}</div>
                          </div>
                        </div>
                      ) : (
                        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-center gap-3">
                          <AlertTriangle className="w-6 h-6 text-amber-600 shrink-0" />
                          <div>
                            <div className="font-semibold text-amber-800 text-sm">Unrecognized Credential</div>
                            <div className="text-xs text-amber-700 mt-0.5">{verificationResult.message}</div>
                          </div>
                        </div>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          )}
        </main>
      </div>

      {/* MODAL 1: ISSUE CERTIFICATE */}
      <Modal
        isOpen={isIssueModalOpen}
        onClose={() => setIsIssueModalOpen(false)}
        title="Issue Official Academic Certificate"
      >
        <form onSubmit={handleIssueCertificate} className="space-y-4">
          <div>
            <Label className="text-xs font-semibold">Student UUID</Label>
            <Input
              placeholder="Enter Student UUID..."
              value={issueForm.student_id}
              onChange={(e) => setIssueForm({ ...issueForm, student_id: e.target.value })}
              required
              className="mt-1 font-mono text-xs"
            />
          </div>

          <div>
            <Label className="text-xs font-semibold">Certificate Type</Label>
            <select
              value={issueForm.type}
              onChange={(e) => setIssueForm({ ...issueForm, type: e.target.value })}
              className="mt-1 w-full text-sm border border-slate-200 rounded-md px-3 py-2 bg-white"
            >
              <option value="bonafide">Bonafide Certificate</option>
              <option value="transfer">Transfer Certificate (TC)</option>
              <option value="character">Character Certificate</option>
              <option value="study">Study Certificate</option>
              <option value="fee_clearance">Fee Clearance Certificate</option>
              <option value="custom">Custom Certificate</option>
            </select>
          </div>

          <div>
            <Label className="text-xs font-semibold">General Conduct</Label>
            <Input
              value={issueForm.conduct}
              onChange={(e) => setIssueForm({ ...issueForm, conduct: e.target.value })}
              className="mt-1"
            />
          </div>

          <div>
            <Label className="text-xs font-semibold">Reason for Leaving / Issue Purpose</Label>
            <Input
              value={issueForm.reason}
              onChange={(e) => setIssueForm({ ...issueForm, reason: e.target.value })}
              className="mt-1"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button type="button" variant="outline" onClick={() => setIsIssueModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
              Generate & Issue
            </Button>
          </div>
        </form>
      </Modal>

      {/* MODAL 2: REVOKE CERTIFICATE */}
      <Modal
        isOpen={isRevokeModalOpen}
        onClose={() => setIsRevokeModalOpen(false)}
        title="Revoke Certificate Credential"
      >
        <form onSubmit={handleRevokeCertificate} className="space-y-4">
          <div className="p-3 bg-rose-50 border border-rose-200 rounded text-xs text-rose-800 space-y-1">
            <div><strong>Certificate:</strong> {selectedCertForRevoke?.certificate_number}</div>
            <div><strong>Recipient:</strong> {selectedCertForRevoke?.recipient_name}</div>
            <p className="mt-1 text-rose-700">
              Revocation is permanent and will flag the public QR code verification as invalidated.
            </p>
          </div>

          <div>
            <Label className="text-xs font-semibold">Mandatory Reason for Revocation</Label>
            <Input
              placeholder="e.g. Duplicate issuance, clerical error, fee default..."
              value={revocationReason}
              onChange={(e) => setRevocationReason(e.target.value)}
              required
              className="mt-1"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
            <Button type="button" variant="outline" onClick={() => setIsRevokeModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="destructive">
              Confirm Revocation
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

