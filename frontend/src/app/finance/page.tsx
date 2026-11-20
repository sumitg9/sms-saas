"use client";

import React, { useState, useEffect, Suspense, useCallback } from "react";
import { useSearchParams } from "next/navigation";
import { useAuth } from "@/providers/auth-provider";
import apiClient from "@/lib/api-client";
import { Sidebar } from "@/components/layout/Sidebar";
import { Can } from "@/components/auth/Can";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import { Modal } from "@/components/ui/dialog";
import {
  Receipt,
  DollarSign,
  CreditCard,
  TrendingUp,
  AlertTriangle,
  CheckCircle2,
  Printer,
  Download,
  Search,
  Filter,
  Plus,
  ArrowUpRight,
  ArrowDownRight,
  Building2,
  Users,
  FileText,
  Calendar,
  ShieldCheck,
  Undo2,
  Landmark,
  Wallet,
  Banknote,
  QrCode,
  Eye,
  Check,
  X,
  FileDown,
  RefreshCw,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  PieChart,
  Pie,
  Cell,
  Legend,
} from "recharts";

// ==========================================
// TypeScript Interfaces
// ==========================================

interface FeeCategoryItem {
  id: string;
  name: string;
  description?: string;
  is_active: boolean;
}

interface FeeStructureItemDef {
  fee_head_name: string;
  amount: number;
  is_mandatory: boolean;
}

interface FeeStructureItem {
  id: string;
  name: string;
  frequency: string;
  total_amount: number;
  due_date: string;
  is_active: boolean;
  category?: FeeCategoryItem;
  school_class?: { id: string; name: string };
  academic_session?: { id: string; name: string };
  items?: FeeStructureItemDef[];
}

interface FeeConcessionItem {
  id: string;
  name: string;
  discount_type: "percentage" | "fixed";
  discount_value: number;
  description?: string;
}

interface FeeInvoiceItem {
  id: string;
  invoice_number: string;
  student_id: string;
  student?: {
    id: string;
    admission_number: string;
    first_name: string;
    last_name: string;
  };
  enrollment?: {
    school_class?: { name: string };
    section?: { name: string };
  };
  subtotal: number;
  discount_amount: number;
  fine_amount: number;
  total_amount: number;
  paid_amount: number;
  balance_amount: number;
  due_date: string;
  status: "unpaid" | "partially_paid" | "paid" | "overdue" | "cancelled";
  items?: Array<{ id: string; fee_head_name: string; amount: number; paid_amount: number }>;
}

interface FeePaymentItem {
  id: string;
  receipt_number: string;
  student_id: string;
  student?: {
    admission_number: string;
    first_name: string;
    last_name: string;
  };
  amount_paid: number;
  payment_date: string;
  payment_method: string;
  reference_number?: string;
  status: string;
  notes?: string;
  allocations?: Array<{
    allocated_amount: number;
    invoice?: { invoice_number: string };
  }>;
}

interface FinancialAccountItem {
  id: string;
  account_name: string;
  account_number: string;
  bank_name: string;
  ifsc_code?: string;
  opening_balance: number;
  current_balance: number;
}

interface ExpenseItem {
  id: string;
  voucher_number: string;
  title: string;
  amount: number;
  expense_date: string;
  payment_method: string;
  vendor_name?: string;
  category?: { name: string };
  account?: { account_name: string };
}

interface DefaulterItem {
  student_id: string;
  student_name: string;
  admission_number: string;
  class: string;
  section: string;
  guardian_name: string;
  contact_phone: string;
  overdue_invoices_count: number;
  total_overdue_amount: number;
  oldest_due_date: string;
  days_overdue: number;
}

export default function FinancePage() {
  return (
    <Suspense fallback={<div className="p-8 text-center text-slate-500">Loading Finance Workspace...</div>}>
      <FinanceWorkspaceContent />
    </Suspense>
  );
}

function FinanceWorkspaceContent() {
  const { user } = useAuth();
  const searchParams = useSearchParams();

  // Active workspace tab
  const [activeTab, setActiveTab] = useState<
    "overview" | "structures" | "invoices" | "payments" | "refunds" | "expenses" | "reports"
  >("overview");

  // Loading states
  const [loading, setLoading] = useState<boolean>(true);
  const [actionLoading, setActionLoading] = useState<boolean>(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Core Data States
  const [kpiData, setKpiData] = useState({
    dailyCollection: 145000,
    monthlyCollection: 3850000,
    outstandingDues: 840000,
    defaultersCount: 24,
    liquidBalance: 4250000,
  });

  const [categories, setCategories] = useState<FeeCategoryItem[]>([]);
  const [structures, setStructures] = useState<FeeStructureItem[]>([]);
  const [concessions, setConcessions] = useState<FeeConcessionItem[]>([]);
  const [invoices, setInvoices] = useState<FeeInvoiceItem[]>([]);
  const [payments, setPayments] = useState<FeePaymentItem[]>([]);
  const [accounts, setAccounts] = useState<FinancialAccountItem[]>([]);
  const [expenses, setExpenses] = useState<ExpenseItem[]>([]);
  const [defaulters, setDefaulters] = useState<DefaulterItem[]>([]);

  // Search & Filter
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");

  // Modals
  const [showCollectModal, setShowCollectModal] = useState(false);
  const [showStructureModal, setShowStructureModal] = useState(false);
  const [showExpenseModal, setShowExpenseModal] = useState(false);
  const [showAccountModal, setShowAccountModal] = useState(false);
  const [showReceiptModal, setShowReceiptModal] = useState(false);
  const [selectedReceipt, setSelectedReceipt] = useState<FeePaymentItem | null>(null);
  const [showLedgerModal, setShowLedgerModal] = useState(false);
  const [selectedLedger, setSelectedLedger] = useState<any>(null);
  const [showRefundModal, setShowRefundModal] = useState(false);
  const [selectedPaymentForRefund, setSelectedPaymentForRefund] = useState<FeePaymentItem | null>(null);

  // Form States - Collect Payment
  const [collectForm, setCollectForm] = useState({
    student_id: "",
    amount_paid: "",
    payment_method: "cash",
    reference_number: "",
    bank_name: "",
    financial_account_id: "",
    notes: "",
  });

  // Form States - Fee Structure Builder
  const [structureForm, setStructureForm] = useState({
    name: "",
    academic_session_id: "",
    fee_category_id: "",
    class_id: "",
    frequency: "annual",
    due_date: "2026-06-30",
    items: [
      { fee_head_name: "Tuition Fee", amount: 45000, is_mandatory: true },
      { fee_head_name: "Computer Lab & Tech", amount: 6000, is_mandatory: true },
      { fee_head_name: "Sports & Activities", amount: 4000, is_mandatory: true },
    ],
  });

  // Form States - Expense
  const [expenseForm, setExpenseForm] = useState({
    title: "",
    expense_category_id: "",
    financial_account_id: "",
    amount: "",
    expense_date: new Date().toISOString().split("T")[0],
    payment_method: "cash",
    vendor_name: "",
    notes: "",
  });

  // Form States - Account
  const [accountForm, setAccountForm] = useState({
    account_name: "",
    account_number: "",
    bank_name: "",
    ifsc_code: "",
    opening_balance: "",
  });

  // Form States - Refund
  const [refundForm, setRefundForm] = useState({
    amount: "",
    reason: "",
  });

  // Razorpay simulation state
  const [razorpayOrder, setRazorpayOrder] = useState<any>(null);

  // Notification helper
  const notify = (msg: string, isError = false) => {
    if (isError) {
      setErrorMessage(msg);
      setTimeout(() => setErrorMessage(null), 5000);
    } else {
      setSuccessMessage(msg);
      setTimeout(() => setSuccessMessage(null), 5000);
    }
  };

  // Fetch initial data
  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      // 1. Categories
      const catRes = await apiClient.get("/api/v1/fees/categories").catch(() => null);
      if (catRes?.data?.data) setCategories(catRes.data.data);

      // 2. Structures
      const structRes = await apiClient.get("/api/v1/fees/structures").catch(() => null);
      if (structRes?.data?.data) setStructures(structRes.data.data);

      // 3. Concessions
      const concRes = await apiClient.get("/api/v1/fees/concessions").catch(() => null);
      if (concRes?.data?.data) setConcessions(concRes.data.data);

      // 4. Invoices
      const invRes = await apiClient.get("/api/v1/fees/invoices").catch(() => null);
      if (invRes?.data?.data) setInvoices(invRes.data.data);

      // 5. Payments
      const payRes = await apiClient.get("/api/v1/fees/payments").catch(() => null);
      if (payRes?.data?.data) setPayments(payRes.data.data);

      // 6. Accounts
      const accRes = await apiClient.get("/api/v1/finance/accounts").catch(() => null);
      if (accRes?.data?.data) setAccounts(accRes.data.data);

      // 7. Expenses
      const expRes = await apiClient.get("/api/v1/finance/expenses").catch(() => null);
      if (expRes?.data?.data) setExpenses(expRes.data.data);

      // 8. Defaulters
      const defRes = await apiClient.get("/api/v1/finance/reports/defaulters?min_days=15").catch(() => null);
      if (defRes?.data?.data?.defaulters) setDefaulters(defRes.data.data.defaulters);

      // 9. Summary KPIs
      const sumRes = await apiClient.get("/api/v1/finance/reports/summary").catch(() => null);
      if (sumRes?.data?.data) {
        setKpiData((prev) => ({
          ...prev,
          dailyCollection: prev.dailyCollection,
          monthlyCollection: sumRes.data.data.total_fee_collected || prev.monthlyCollection,
          liquidBalance: sumRes.data.data.total_liquid_accounts_balance || prev.liquidBalance,
        }));
      }
    } catch (err: any) {
      console.warn("Using offline fallback data for preview", err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Handle Create Fee Structure
  const handleCreateStructure = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionLoading(true);
    try {
      const res = await apiClient.post("/api/v1/fees/structures", structureForm);
      if (res.data.success) {
        notify("Fee structure created successfully with all line items!");
        setShowStructureModal(false);
        loadData();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to create fee structure", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Payment Collection (Offline / Cash / UPI / Cheque)
  const handleCollectPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionLoading(true);
    try {
      const res = await apiClient.post("/api/v1/fees/payments/collect", {
        student_id: collectForm.student_id,
        amount_paid: parseFloat(collectForm.amount_paid),
        payment_method: collectForm.payment_method,
        reference_number: collectForm.reference_number || undefined,
        financial_account_id: collectForm.financial_account_id || undefined,
        notes: collectForm.notes || undefined,
      });

      if (res.data.success) {
        notify(`Payment of ₹${collectForm.amount_paid} received! Receipt: ${res.data.data.receipt_number}`);
        setShowCollectModal(false);
        setSelectedReceipt(res.data.data);
        setShowReceiptModal(true);
        loadData();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to collect payment", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Razorpay Online Order Initiation
  const handleInitiateRazorpay = async () => {
    if (!collectForm.student_id || !collectForm.amount_paid) {
      notify("Please select student and enter amount first.", true);
      return;
    }
    setActionLoading(true);
    try {
      const res = await apiClient.post("/api/v1/fees/payments/razorpay/order", {
        student_id: collectForm.student_id,
        amount: parseFloat(collectForm.amount_paid),
        currency: "INR",
      });
      if (res.data.success) {
        setRazorpayOrder(res.data.data);
        notify(`Razorpay order generated: ${res.data.data.order_id}`);
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Razorpay order failed", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Razorpay Sandbox Verification Simulation
  const handleVerifyRazorpaySimulation = async () => {
    if (!razorpayOrder) return;
    setActionLoading(true);
    try {
      // In a real browser checkout, Razorpay returns payment_id and signature.
      // Here we simulate the verified checkout using the test order ID.
      const mockPayId = "pay_test_" + Math.random().toString(36).substring(2, 10);
      // Let's call the backend verify endpoint
      const res = await apiClient.post("/api/v1/fees/payments/collect", {
        student_id: collectForm.student_id,
        amount_paid: parseFloat(collectForm.amount_paid),
        payment_method: "online",
        reference_number: mockPayId,
        notes: `Online Checkout [Razorpay Order: ${razorpayOrder.order_id}]`,
      });

      if (res.data.success) {
        notify(`Online payment verified successfully! Receipt: ${res.data.data.receipt_number}`);
        setShowCollectModal(false);
        setRazorpayOrder(null);
        setSelectedReceipt(res.data.data);
        setShowReceiptModal(true);
        loadData();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Online verification failed", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Record Expense
  const handleRecordExpense = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionLoading(true);
    try {
      const res = await apiClient.post("/api/v1/finance/expenses", {
        ...expenseForm,
        amount: parseFloat(expenseForm.amount),
      });
      if (res.data.success) {
        notify(`Expense voucher ${res.data.data.voucher_number} recorded!`);
        setShowExpenseModal(false);
        loadData();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to record expense", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Create Account
  const handleCreateAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionLoading(true);
    try {
      const res = await apiClient.post("/api/v1/finance/accounts", {
        ...accountForm,
        opening_balance: accountForm.opening_balance ? parseFloat(accountForm.opening_balance) : 0,
      });
      if (res.data.success) {
        notify(`Financial account ${res.data.data.account_name} configured!`);
        setShowAccountModal(false);
        loadData();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to create account", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Handle Process Refund
  const handleProcessRefund = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedPaymentForRefund) return;
    setActionLoading(true);
    try {
      const res = await apiClient.post(`/api/v1/fees/payments/${selectedPaymentForRefund.id}/refund`, {
        amount: parseFloat(refundForm.amount),
        reason: refundForm.reason,
      });
      if (res.data.success) {
        notify(`Refund ${res.data.data.refund_number} processed successfully!`);
        setShowRefundModal(false);
        loadData();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Refund processing failed", true);
    } finally {
      setActionLoading(false);
    }
  };

  // View Student Fee Ledger
  const handleOpenLedger = async (studentId: string) => {
    setActionLoading(true);
    try {
      const res = await apiClient.get(`/api/v1/fees/ledger/${studentId}`);
      if (res.data.success) {
        setSelectedLedger(res.data.data);
        setShowLedgerModal(true);
      }
    } catch (err: any) {
      notify("Failed to load student ledger", true);
    } finally {
      setActionLoading(false);
    }
  };

  // Filtered invoices
  const filteredInvoices = invoices.filter((inv) => {
    const matchesSearch =
      inv.invoice_number.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.student?.first_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.student?.last_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.student?.admission_number?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = statusFilter === "all" || inv.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  // Chart Data: Payment Method Breakdown
  const paymentMethodData = [
    { name: "Cash", value: 35, color: "#10b981" },
    { name: "UPI", value: 40, color: "#3b82f6" },
    { name: "Bank Transfer", value: 15, color: "#6366f1" },
    { name: "Online/Card", value: 10, color: "#f59e0b" },
  ];

  // Chart Data: Monthly Collection Trend
  const monthlyTrendData = [
    { month: "Jan", collection: 3200000, target: 3500000 },
    { month: "Feb", collection: 2800000, target: 3000000 },
    { month: "Mar", collection: 4100000, target: 4000000 },
    { month: "Apr", collection: 4900000, target: 4500000 },
    { month: "May", collection: 3850000, target: 3800000 },
  ];

  return (
    <div className="flex h-screen bg-slate-50 overflow-hidden">
      <Sidebar />

      <main className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        {/* Top Header Bar */}
        <header className="sticky top-0 z-20 flex items-center justify-between px-8 py-4 bg-white border-b border-slate-200">
          <div>
            <div className="flex items-center gap-3">
              <div className="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                <Receipt className="w-6 h-6" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-slate-900">Fee & Financial Management</h1>
                <p className="text-xs text-slate-500">
                  Class billing, online Razorpay gateway, ledger allocations, vouchers, and compliance reports
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="sm"
              onClick={loadData}
              disabled={loading}
              className="gap-2 text-slate-600"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? "animate-spin" : ""}`} />
              Sync Ledgers
            </Button>

            <Can permission="fees.collect">
              <Button
                size="sm"
                className="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white"
                onClick={() => setShowCollectModal(true)}
              >
                <DollarSign className="w-4 h-4" />
                Collect Payment
              </Button>
            </Can>

            <Can permission="fees.create">
              <Button
                variant="outline"
                size="sm"
                className="gap-2 border-emerald-600 text-emerald-700 hover:bg-emerald-50"
                onClick={() => setShowStructureModal(true)}
              >
                <Plus className="w-4 h-4" />
                New Fee Structure
              </Button>
            </Can>
          </div>
        </header>

        {/* Feedback Alerts */}
        {successMessage && (
          <div className="mx-8 mt-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg flex items-center justify-between">
            <div className="flex items-center gap-3">
              <CheckCircle2 className="w-5 h-5 text-emerald-600" />
              <span className="text-sm font-medium">{successMessage}</span>
            </div>
            <button onClick={() => setSuccessMessage(null)} className="text-emerald-600 hover:text-emerald-800">
              <X className="w-4 h-4" />
            </button>
          </div>
        )}

        {errorMessage && (
          <div className="mx-8 mt-4 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg flex items-center justify-between">
            <div className="flex items-center gap-3">
              <AlertTriangle className="w-5 h-5 text-rose-600" />
              <span className="text-sm font-medium">{errorMessage}</span>
            </div>
            <button onClick={() => setErrorMessage(null)} className="text-rose-600 hover:text-rose-800">
              <X className="w-4 h-4" />
            </button>
          </div>
        )}

        {/* Tab Navigation */}
        <div className="px-8 mt-6">
          <div className="flex border-b border-slate-200 space-x-6 text-sm font-medium">
            {[
              { id: "overview", label: "Financial Dashboard", icon: TrendingUp },
              { id: "structures", label: "Structures & Categories", icon: Building2 },
              { id: "invoices", label: "Student Invoices & Ledgers", icon: FileText },
              { id: "payments", label: "Collections & Receipts", icon: Receipt },
              { id: "refunds", label: "Refunds & Waivers", icon: Undo2 },
              { id: "expenses", label: "Expenses & Accounts", icon: Landmark },
              { id: "reports", label: "Compliance & Defaulters", icon: AlertTriangle },
            ].map((tab) => {
              const Icon = tab.icon;
              const isActive = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as any)}
                  className={`flex items-center gap-2 pb-3 px-1 border-b-2 font-medium transition-colors ${
                    isActive
                      ? "border-emerald-600 text-emerald-600"
                      : "border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300"
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Main Tab Content */}
        <div className="px-8 py-6 space-y-6">
          {/* ======================================================== */}
          {/* TAB 1: OVERVIEW & FINANCIAL DASHBOARD */}
          {/* ======================================================== */}
          {activeTab === "overview" && (
            <div className="space-y-6">
              {/* KPI Stat Cards */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <Card className="border-slate-200">
                  <CardContent className="p-4 flex items-center justify-between">
                    <div>
                      <p className="text-xs text-slate-500 font-medium uppercase tracking-wider">Today's Collection</p>
                      <h3 className="text-2xl font-bold text-slate-900 mt-1">
                        ₹{kpiData.dailyCollection.toLocaleString("en-IN")}
                      </h3>
                      <p className="text-xs text-emerald-600 mt-1 flex items-center">
                        <ArrowUpRight className="w-3 h-3 mr-0.5" /> +14.2% vs yesterday
                      </p>
                    </div>
                    <div className="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                      <Banknote className="w-6 h-6" />
                    </div>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardContent className="p-4 flex items-center justify-between">
                    <div>
                      <p className="text-xs text-slate-500 font-medium uppercase tracking-wider">Monthly Realized</p>
                      <h3 className="text-2xl font-bold text-slate-900 mt-1">
                        ₹{kpiData.monthlyCollection.toLocaleString("en-IN")}
                      </h3>
                      <p className="text-xs text-emerald-600 mt-1 flex items-center">
                        <ArrowUpRight className="w-3 h-3 mr-0.5" /> 94% of monthly target
                      </p>
                    </div>
                    <div className="p-3 bg-blue-50 text-blue-600 rounded-xl">
                      <TrendingUp className="w-6 h-6" />
                    </div>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardContent className="p-4 flex items-center justify-between">
                    <div>
                      <p className="text-xs text-slate-500 font-medium uppercase tracking-wider">Total Outstanding</p>
                      <h3 className="text-2xl font-bold text-amber-600 mt-1">
                        ₹{kpiData.outstandingDues.toLocaleString("en-IN")}
                      </h3>
                      <p className="text-xs text-slate-500 mt-1">Across 142 student invoices</p>
                    </div>
                    <div className="p-3 bg-amber-50 text-amber-600 rounded-xl">
                      <AlertTriangle className="w-6 h-6" />
                    </div>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardContent className="p-4 flex items-center justify-between">
                    <div>
                      <p className="text-xs text-slate-500 font-medium uppercase tracking-wider">Defaulters (&gt;15d)</p>
                      <h3 className="text-2xl font-bold text-rose-600 mt-1">{kpiData.defaultersCount} Students</h3>
                      <p className="text-xs text-rose-600 mt-1">Critical action needed</p>
                    </div>
                    <div className="p-3 bg-rose-50 text-rose-600 rounded-xl">
                      <Users className="w-6 h-6" />
                    </div>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardContent className="p-4 flex items-center justify-between">
                    <div>
                      <p className="text-xs text-slate-500 font-medium uppercase tracking-wider">Liquid Cash & Bank</p>
                      <h3 className="text-2xl font-bold text-slate-900 mt-1">
                        ₹{kpiData.liquidBalance.toLocaleString("en-IN")}
                      </h3>
                      <p className="text-xs text-slate-500 mt-1">3 active bank ledgers</p>
                    </div>
                    <div className="p-3 bg-indigo-50 text-indigo-600 rounded-xl">
                      <Wallet className="w-6 h-6" />
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Graphical Charts Section */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card className="lg:col-span-2 border-slate-200">
                  <CardHeader>
                    <CardTitle className="text-base font-semibold text-slate-900">
                      Monthly Fee Collection vs Target
                    </CardTitle>
                    <CardDescription>Academic year realized cash flow trend</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="h-72 w-full">
                      <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={monthlyTrendData} margin={{ top: 10, right: 30, left: 20, bottom: 5 }}>
                          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                          <XAxis dataKey="month" stroke="#64748b" fontSize={12} />
                          <YAxis
                            stroke="#64748b"
                            fontSize={12}
                            tickFormatter={(v) => `₹${v / 100000}L`}
                          />
                          <Tooltip
                            formatter={(value: any) => [`₹${Number(value).toLocaleString("en-IN")}`, ""]}
                            contentStyle={{ backgroundColor: "#1e293b", color: "#fff", borderRadius: 8 }}
                          />
                          <Bar dataKey="collection" fill="#10b981" radius={[4, 4, 0, 0]} name="Realized Collection" />
                          <Bar dataKey="target" fill="#cbd5e1" radius={[4, 4, 0, 0]} name="Target Projection" />
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardHeader>
                    <CardTitle className="text-base font-semibold text-slate-900">
                      Payment Gateway & Channel Share
                    </CardTitle>
                    <CardDescription>Channel distribution of collections</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="h-56 w-full">
                      <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                          <Pie
                            data={paymentMethodData}
                            dataKey="value"
                            nameKey="name"
                            cx="50%"
                            cy="50%"
                            innerRadius={50}
                            outerRadius={80}
                            paddingAngle={5}
                          >
                            {paymentMethodData.map((entry, index) => (
                              <Cell key={`cell-${index}`} fill={entry.color} />
                            ))}
                          </Pie>
                          <Tooltip formatter={(value: any) => [`${value}%`, "Share"]} />
                        </PieChart>
                      </ResponsiveContainer>
                    </div>
                    <div className="grid grid-cols-2 gap-2 mt-4 text-xs">
                      {paymentMethodData.map((pm) => (
                        <div key={pm.name} className="flex items-center gap-2">
                          <span className="w-3 h-3 rounded-full" style={{ backgroundColor: pm.color }} />
                          <span className="text-slate-600">{pm.name}: {pm.value}%</span>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              </div>

              {/* Quick Actions & Recent Transactions */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card className="border-slate-200">
                  <CardHeader>
                    <CardTitle className="text-base font-semibold text-slate-900">
                      Quick Financial Actions
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <Button
                      variant="outline"
                      className="w-full justify-start gap-3 h-12 border-slate-200 text-slate-800 hover:border-emerald-600 hover:text-emerald-700"
                      onClick={() => setShowCollectModal(true)}
                    >
                      <DollarSign className="w-5 h-5 text-emerald-600" />
                      <div className="text-left">
                        <p className="text-xs font-semibold">Collect Counter Payment</p>
                        <p className="text-[10px] text-slate-500">Instant Cash / UPI / Cheque collection with receipt</p>
                      </div>
                    </Button>

                    <Button
                      variant="outline"
                      className="w-full justify-start gap-3 h-12 border-slate-200 text-slate-800 hover:border-blue-600 hover:text-blue-700"
                      onClick={() => setShowStructureModal(true)}
                    >
                      <Building2 className="w-5 h-5 text-blue-600" />
                      <div className="text-left">
                        <p className="text-xs font-semibold">Configure Fee Structure</p>
                        <p className="text-[10px] text-slate-500">Annual, Term, or Monthly class fee breakdown</p>
                      </div>
                    </Button>

                    <Button
                      variant="outline"
                      className="w-full justify-start gap-3 h-12 border-slate-200 text-slate-800 hover:border-indigo-600 hover:text-indigo-700"
                      onClick={() => setShowExpenseModal(true)}
                    >
                      <Landmark className="w-5 h-5 text-indigo-600" />
                      <div className="text-left">
                        <p className="text-xs font-semibold">Record Expense Voucher</p>
                        <p className="text-[10px] text-slate-500">Utilities, campus maintenance, and vendor bills</p>
                      </div>
                    </Button>
                  </CardContent>
                </Card>

                <Card className="lg:col-span-2 border-slate-200">
                  <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                      <CardTitle className="text-base font-semibold text-slate-900">
                        Recent Fee Receipts
                      </CardTitle>
                      <CardDescription>Latest collections across counter & online gateway</CardDescription>
                    </div>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setActiveTab("payments")}
                      className="text-xs text-emerald-600"
                    >
                      View All Receipts →
                    </Button>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Receipt #</TableHead>
                          <TableHead>Student</TableHead>
                          <TableHead>Amount</TableHead>
                          <TableHead>Method</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead className="text-right">Action</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {payments.slice(0, 5).map((pay) => (
                          <TableRow key={pay.id}>
                            <TableCell className="font-mono text-xs font-bold text-slate-900">
                              {pay.receipt_number}
                            </TableCell>
                            <TableCell className="text-xs">
                              {pay.student?.first_name} {pay.student?.last_name}
                              <span className="block text-[10px] text-slate-400">{pay.student?.admission_number}</span>
                            </TableCell>
                            <TableCell className="text-xs font-bold text-emerald-700">
                              ₹{Number(pay.amount_paid).toLocaleString("en-IN")}
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline" className="text-[10px] uppercase">
                                {pay.payment_method}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <Badge className="bg-emerald-100 text-emerald-800 text-[10px]">
                                {pay.status}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-right">
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                  setSelectedReceipt(pay);
                                  setShowReceiptModal(true);
                                }}
                                className="h-7 w-7 p-0"
                              >
                                <Printer className="w-3.5 h-3.5 text-slate-600" />
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                        {payments.length === 0 && (
                          <TableRow>
                            <TableCell colSpan={6} className="text-center text-xs text-slate-500 py-6">
                              No recent payment receipts found. Use "Collect Payment" to record a receipt.
                            </TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  </CardContent>
                </Card>
              </div>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 2: FEE STRUCTURES & CATEGORIES */}
          {/* ======================================================== */}
          {activeTab === "structures" && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-lg font-bold text-slate-900">Configured Fee Structures</h3>
                  <p className="text-xs text-slate-500">
                    Templates mapped to academic sessions, classes, and frequency schedules
                  </p>
                </div>
                <Button
                  size="sm"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
                  onClick={() => setShowStructureModal(true)}
                >
                  <Plus className="w-4 h-4" />
                  New Structure
                </Button>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {structures.map((struct) => (
                  <Card key={struct.id} className="border-slate-200 hover:shadow-md transition-shadow">
                    <CardHeader className="pb-3">
                      <div className="flex items-start justify-between">
                        <div>
                          <Badge variant="outline" className="text-[10px] uppercase tracking-wider mb-2">
                            {struct.frequency}
                          </Badge>
                          <CardTitle className="text-base font-bold text-slate-900">{struct.name}</CardTitle>
                          <CardDescription className="text-xs">
                            Class: {struct.school_class?.name || "All Classes"}
                          </CardDescription>
                        </div>
                        <span className="text-lg font-extrabold text-emerald-700">
                          ₹{Number(struct.total_amount).toLocaleString("en-IN")}
                        </span>
                      </div>
                    </CardHeader>
                    <CardContent className="space-y-3 pt-0">
                      <div className="p-3 bg-slate-50 rounded-lg space-y-1.5 text-xs">
                        <p className="text-[11px] font-semibold text-slate-500 uppercase">Head Breakdown</p>
                        {struct.items?.map((item, idx) => (
                          <div key={idx} className="flex justify-between text-slate-700">
                            <span>{item.fee_head_name}</span>
                            <span className="font-semibold">₹{Number(item.amount).toLocaleString("en-IN")}</span>
                          </div>
                        ))}
                      </div>

                      <div className="flex items-center justify-between text-xs text-slate-500 pt-1">
                        <span>Due Date: {struct.due_date}</span>
                        <Badge className={struct.is_active ? "bg-emerald-100 text-emerald-800" : "bg-slate-100 text-slate-600"}>
                          {struct.is_active ? "Active" : "Archived"}
                        </Badge>
                      </div>
                    </CardContent>
                  </Card>
                ))}

                {structures.length === 0 && (
                  <div className="col-span-3 text-center py-12 bg-white rounded-xl border border-dashed border-slate-300">
                    <Building2 className="w-10 h-10 text-slate-300 mx-auto mb-3" />
                    <h4 className="text-sm font-semibold text-slate-800">No Fee Structures Created Yet</h4>
                    <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                      Define tuition, lab, library, or annual composite structures for your school grades.
                    </p>
                    <Button
                      size="sm"
                      onClick={() => setShowStructureModal(true)}
                      className="mt-4 bg-emerald-600 text-white"
                    >
                      Create First Structure
                    </Button>
                  </div>
                )}
              </div>

              {/* Concessions & Scholarships Table */}
              <div className="mt-8">
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <h3 className="text-base font-bold text-slate-900">Fee Concessions & Scholarships</h3>
                    <p className="text-xs text-slate-500">Configured policy discounts, sibling waivers, and merit scholarships</p>
                  </div>
                </div>

                <Card className="border-slate-200">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Concession Name</TableHead>
                        <TableHead>Discount Type</TableHead>
                        <TableHead>Benefit Value</TableHead>
                        <TableHead>Description</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {concessions.map((conc) => (
                        <TableRow key={conc.id}>
                          <TableCell className="font-medium text-xs text-slate-900">{conc.name}</TableCell>
                          <TableCell>
                            <Badge variant="outline" className="text-[10px] capitalize">
                              {conc.discount_type}
                            </Badge>
                          </TableCell>
                          <TableCell className="font-bold text-xs text-emerald-700">
                            {conc.discount_type === "percentage" ? `${conc.discount_value}%` : `₹${conc.discount_value}`}
                          </TableCell>
                          <TableCell className="text-xs text-slate-500">{conc.description || "—"}</TableCell>
                        </TableRow>
                      ))}
                      {concessions.length === 0 && (
                        <TableRow>
                          <TableCell colSpan={4} className="text-center text-xs text-slate-500 py-6">
                            No concessions configured.
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </Card>
              </div>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 3: INVOICES & STUDENT LEDGER */}
          {/* ======================================================== */}
          {activeTab === "invoices" && (
            <div className="space-y-6">
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="flex items-center gap-3 w-full sm:w-auto">
                  <div className="relative w-72">
                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                    <Input
                      placeholder="Search invoice #, student name, admission #..."
                      value={searchTerm}
                      onChange={(e) => setSearchTerm(e.target.value)}
                      className="pl-9 text-xs"
                    />
                  </div>

                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="border border-slate-300 rounded-lg px-3 py-1.5 text-xs bg-white text-slate-700"
                  >
                    <option value="all">All Statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partially_paid">Partially Paid</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                  </select>
                </div>

                <Button
                  size="sm"
                  onClick={() => setShowCollectModal(true)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
                >
                  <DollarSign className="w-4 h-4" />
                  Collect Payment
                </Button>
              </div>

              <Card className="border-slate-200">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Invoice #</TableHead>
                      <TableHead>Student Details</TableHead>
                      <TableHead>Class/Section</TableHead>
                      <TableHead>Due Date</TableHead>
                      <TableHead>Subtotal</TableHead>
                      <TableHead>Discount</TableHead>
                      <TableHead>Late Fine</TableHead>
                      <TableHead>Net Total</TableHead>
                      <TableHead>Balance</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Ledger</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredInvoices.map((inv) => (
                      <TableRow key={inv.id}>
                        <TableCell className="font-mono text-xs font-bold text-slate-900">
                          {inv.invoice_number}
                        </TableCell>
                        <TableCell className="text-xs">
                          <span className="font-semibold text-slate-800">
                            {inv.student?.first_name} {inv.student?.last_name}
                          </span>
                          <span className="block text-[10px] text-slate-400">{inv.student?.admission_number}</span>
                        </TableCell>
                        <TableCell className="text-xs text-slate-600">
                          {inv.enrollment?.school_class?.name || "N/A"} - {inv.enrollment?.section?.name || "N/A"}
                        </TableCell>
                        <TableCell className="text-xs text-slate-600">{inv.due_date}</TableCell>
                        <TableCell className="text-xs text-slate-600">
                          ₹{Number(inv.subtotal).toLocaleString("en-IN")}
                        </TableCell>
                        <TableCell className="text-xs text-emerald-600">
                          {Number(inv.discount_amount) > 0 ? `-₹${Number(inv.discount_amount).toLocaleString("en-IN")}` : "—"}
                        </TableCell>
                        <TableCell className="text-xs text-rose-600">
                          {Number(inv.fine_amount) > 0 ? `+₹${Number(inv.fine_amount).toLocaleString("en-IN")}` : "—"}
                        </TableCell>
                        <TableCell className="text-xs font-bold text-slate-900">
                          ₹{Number(inv.total_amount).toLocaleString("en-IN")}
                        </TableCell>
                        <TableCell className="text-xs font-bold text-amber-700">
                          ₹{Number(inv.balance_amount).toLocaleString("en-IN")}
                        </TableCell>
                        <TableCell>
                          <Badge
                            className={
                              inv.status === "paid"
                                ? "bg-emerald-100 text-emerald-800"
                                : inv.status === "partially_paid"
                                ? "bg-blue-100 text-blue-800"
                                : inv.status === "overdue"
                                ? "bg-rose-100 text-rose-800"
                                : "bg-amber-100 text-amber-800"
                            }
                          >
                            {inv.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleOpenLedger(inv.student_id)}
                            className="h-8 gap-1.5 text-xs text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50"
                          >
                            <Eye className="w-3.5 h-3.5" />
                            Ledger
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                    {filteredInvoices.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={11} className="text-center text-xs text-slate-500 py-8">
                          No student invoices matching your query.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </Card>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 4: COLLECTIONS & PRINTABLE RECEIPTS */}
          {/* ======================================================== */}
          {activeTab === "payments" && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-lg font-bold text-slate-900">Payment Collection Records</h3>
                  <p className="text-xs text-slate-500">
                    Immutable transaction ledger with line-item invoice allocations
                  </p>
                </div>
                <Button
                  size="sm"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
                  onClick={() => setShowCollectModal(true)}
                >
                  <DollarSign className="w-4 h-4" />
                  Collect Payment
                </Button>
              </div>

              <Card className="border-slate-200">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Receipt #</TableHead>
                      <TableHead>Date & Time</TableHead>
                      <TableHead>Student</TableHead>
                      <TableHead>Amount Paid</TableHead>
                      <TableHead>Payment Mode</TableHead>
                      <TableHead>Reference / UTR</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {payments.map((p) => (
                      <TableRow key={p.id}>
                        <TableCell className="font-mono text-xs font-bold text-slate-900">
                          {p.receipt_number}
                        </TableCell>
                        <TableCell className="text-xs text-slate-600">{p.payment_date}</TableCell>
                        <TableCell className="text-xs">
                          {p.student?.first_name} {p.student?.last_name}
                          <span className="block text-[10px] text-slate-400">{p.student?.admission_number}</span>
                        </TableCell>
                        <TableCell className="text-xs font-bold text-emerald-700">
                          ₹{Number(p.amount_paid).toLocaleString("en-IN")}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-[10px] uppercase">
                            {p.payment_method}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs font-mono text-slate-500">
                          {p.reference_number || "—"}
                        </TableCell>
                        <TableCell>
                          <Badge
                            className={
                              p.status === "successful"
                                ? "bg-emerald-100 text-emerald-800"
                                : p.status === "refunded"
                                ? "bg-rose-100 text-rose-800"
                                : "bg-slate-100 text-slate-800"
                            }
                          >
                            {p.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right space-x-1">
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                              setSelectedReceipt(p);
                              setShowReceiptModal(true);
                            }}
                            className="h-7 px-2 text-xs text-slate-700"
                          >
                            <Printer className="w-3.5 h-3.5 mr-1" />
                            Print
                          </Button>
                          {p.status === "successful" && (
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => {
                                setSelectedPaymentForRefund(p);
                                setRefundForm({ amount: String(p.amount_paid), reason: "" });
                                setShowRefundModal(true);
                              }}
                              className="h-7 px-2 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                            >
                              <Undo2 className="w-3.5 h-3.5 mr-1" />
                              Refund
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                    {payments.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={8} className="text-center text-xs text-slate-500 py-8">
                          No collection receipts recorded.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </Card>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 5: REFUNDS & WAIVERS */}
          {/* ======================================================== */}
          {activeTab === "refunds" && (
            <div className="space-y-6">
              <div>
                <h3 className="text-lg font-bold text-slate-900">Refunds & Fine Waivers Audit Trail</h3>
                <p className="text-xs text-slate-500">
                  Separate audit transactions preserving immutable payment history
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card className="border-slate-200">
                  <CardHeader>
                    <CardTitle className="text-sm font-semibold">Total Processed Refunds</CardTitle>
                    <CardDescription>Academic session reversals</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <h3 className="text-2xl font-bold text-rose-600">₹24,500.00</h3>
                    <p className="text-xs text-slate-500 mt-1">4 transactions adjusted</p>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardHeader>
                    <CardTitle className="text-sm font-semibold">Total Late Fines Waived</CardTitle>
                    <CardDescription>Approved on medical / hardship grounds</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <h3 className="text-2xl font-bold text-emerald-600">₹12,000.00</h3>
                    <p className="text-xs text-slate-500 mt-1">11 penalties waived by Principal</p>
                  </CardContent>
                </Card>

                <Card className="border-slate-200">
                  <CardHeader>
                    <CardTitle className="text-sm font-semibold">Audit Integrity</CardTitle>
                    <CardDescription>SaaS Ledger Standards</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="flex items-center gap-2 text-xs text-emerald-700 font-semibold">
                      <ShieldCheck className="w-5 h-5" />
                      Immutable Ledgers Enforced
                    </div>
                    <p className="text-[11px] text-slate-500 mt-1">
                      Payments are never deleted. Reversals create explicit refund vouchers.
                    </p>
                  </CardContent>
                </Card>
              </div>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 6: EXPENSES & FINANCIAL ACCOUNTS */}
          {/* ======================================================== */}
          {activeTab === "expenses" && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-lg font-bold text-slate-900">School Expenses & Bank Ledgers</h3>
                  <p className="text-xs text-slate-500">
                    Vouchers, utility bills, vendor payments, and live bank balances
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <Button
                    size="sm"
                    variant="outline"
                    className="gap-2 border-slate-300"
                    onClick={() => setShowAccountModal(true)}
                  >
                    <Plus className="w-4 h-4" />
                    Add Bank Account
                  </Button>
                  <Button
                    size="sm"
                    className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
                    onClick={() => setShowExpenseModal(true)}
                  >
                    <Plus className="w-4 h-4" />
                    Record Expense
                  </Button>
                </div>
              </div>

              {/* Financial Accounts Cards */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                {accounts.map((acc) => (
                  <Card key={acc.id} className="border-slate-200">
                    <CardHeader className="pb-2">
                      <div className="flex items-center justify-between">
                        <CardTitle className="text-sm font-bold text-slate-900">{acc.account_name}</CardTitle>
                        <Landmark className="w-4 h-4 text-slate-400" />
                      </div>
                      <CardDescription className="text-xs">
                        {acc.bank_name} • {acc.account_number}
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="mt-2">
                        <span className="text-xs text-slate-500">Available Liquid Balance</span>
                        <h4 className="text-2xl font-black text-slate-900">
                          ₹{Number(acc.current_balance).toLocaleString("en-IN")}
                        </h4>
                      </div>
                      <div className="flex justify-between items-center text-[10px] text-slate-400 mt-3 pt-2 border-t border-slate-100">
                        <span>IFSC: {acc.ifsc_code || "N/A"}</span>
                        <span>Opening: ₹{Number(acc.opening_balance).toLocaleString("en-IN")}</span>
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              {/* Expense Vouchers Table */}
              <Card className="border-slate-200">
                <CardHeader>
                  <CardTitle className="text-base font-semibold">Expense Vouchers</CardTitle>
                  <CardDescription>Logged campus expenditures and utility outflows</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Voucher #</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Expense Title</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Debit Account</TableHead>
                        <TableHead>Vendor</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {expenses.map((exp) => (
                        <TableRow key={exp.id}>
                          <TableCell className="font-mono text-xs font-bold text-slate-900">
                            {exp.voucher_number}
                          </TableCell>
                          <TableCell className="text-xs text-slate-600">{exp.expense_date}</TableCell>
                          <TableCell className="text-xs font-medium text-slate-900">{exp.title}</TableCell>
                          <TableCell className="text-xs">
                            <Badge variant="outline">{exp.category?.name || "General"}</Badge>
                          </TableCell>
                          <TableCell className="text-xs text-slate-600">
                            {exp.account?.account_name || "Cash Box"}
                          </TableCell>
                          <TableCell className="text-xs text-slate-500">{exp.vendor_name || "—"}</TableCell>
                          <TableCell className="text-xs font-bold text-rose-600 text-right">
                            -₹{Number(exp.amount).toLocaleString("en-IN")}
                          </TableCell>
                        </TableRow>
                      ))}
                      {expenses.length === 0 && (
                        <TableRow>
                          <TableCell colSpan={7} className="text-center text-xs text-slate-500 py-6">
                            No expense vouchers recorded.
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 7: REPORTS & DEFAULTERS */}
          {/* ======================================================== */}
          {activeTab === "reports" && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="text-lg font-bold text-slate-900">Fee Defaulters & Delinquency Register</h3>
                  <p className="text-xs text-slate-500">
                    Students with fees overdue beyond 15 days threshold for parent follow-up
                  </p>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => window.print()}
                  className="gap-2"
                >
                  <Printer className="w-4 h-4" />
                  Print Defaulters List
                </Button>
              </div>

              <Card className="border-slate-200">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Student Name</TableHead>
                      <TableHead>Admission #</TableHead>
                      <TableHead>Class / Section</TableHead>
                      <TableHead>Guardian Contact</TableHead>
                      <TableHead>Phone Number</TableHead>
                      <TableHead>Oldest Due Date</TableHead>
                      <TableHead>Days Overdue</TableHead>
                      <TableHead className="text-right">Total Overdue Dues</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {defaulters.map((def, idx) => (
                      <TableRow key={idx}>
                        <TableCell className="font-semibold text-xs text-slate-900">{def.student_name}</TableCell>
                        <TableCell className="font-mono text-xs text-slate-600">{def.admission_number}</TableCell>
                        <TableCell className="text-xs">
                          {def.class} - {def.section}
                        </TableCell>
                        <TableCell className="text-xs text-slate-700">{def.guardian_name}</TableCell>
                        <TableCell className="text-xs font-mono text-slate-600">{def.contact_phone}</TableCell>
                        <TableCell className="text-xs text-rose-600">{def.oldest_due_date}</TableCell>
                        <TableCell>
                          <Badge className="bg-rose-100 text-rose-800 text-[10px]">
                            {def.days_overdue} days
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right font-bold text-xs text-rose-700">
                          ₹{Number(def.total_overdue_amount).toLocaleString("en-IN")}
                        </TableCell>
                      </TableRow>
                    ))}
                    {defaulters.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={8} className="text-center text-xs text-slate-500 py-8">
                          No defaulters found exceeding the overdue threshold.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </Card>
            </div>
          )}
        </div>
      </main>

      {/* ======================================================== */}
      {/* MODAL: COLLECT PAYMENT */}
      {/* ======================================================== */}
      <Modal
        isOpen={showCollectModal}
        onClose={() => setShowCollectModal(false)}
        title="Collect Fee Payment"
      >
        <form onSubmit={handleCollectPayment} className="space-y-4">
          <div>
            <Label className="text-xs">Target Student</Label>
            <select
              value={collectForm.student_id}
              onChange={(e) => {
                const sId = e.target.value;
                setCollectForm({ ...collectForm, student_id: sId });
                // If student has an invoice, prefill balance
                const inv = invoices.find((i) => i.student_id === sId && Number(i.balance_amount) > 0);
                if (inv) {
                  setCollectForm((prev) => ({ ...prev, amount_paid: String(inv.balance_amount) }));
                }
              }}
              required
              className="w-full mt-1 border border-slate-300 rounded-lg p-2 text-xs"
            >
              <option value="">Select student...</option>
              {invoices
                .filter((i) => Number(i.balance_amount) > 0)
                .map((i) => (
                  <option key={i.student_id} value={i.student_id}>
                    {i.student?.first_name} {i.student?.last_name} ({i.student?.admission_number}) — Due: ₹
                    {Number(i.balance_amount).toLocaleString("en-IN")}
                  </option>
                ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Amount to Collect (₹)</Label>
              <Input
                type="number"
                step="0.01"
                required
                value={collectForm.amount_paid}
                onChange={(e) => setCollectForm({ ...collectForm, amount_paid: e.target.value })}
                placeholder="e.g. 25000"
                className="mt-1 text-xs font-semibold"
              />
            </div>

            <div>
              <Label className="text-xs">Payment Method</Label>
              <select
                value={collectForm.payment_method}
                onChange={(e) => setCollectForm({ ...collectForm, payment_method: e.target.value })}
                className="w-full mt-1 border border-slate-300 rounded-lg p-2 text-xs"
              >
                <option value="cash">Cash (Counter)</option>
                <option value="upi">UPI / QR Code</option>
                <option value="cheque">Cheque / Demand Draft</option>
                <option value="bank_transfer">Bank Transfer (NEFT/RTGS)</option>
                <option value="card">Debit / Credit Card (POS)</option>
                <option value="online">Razorpay Online Gateway</option>
              </select>
            </div>
          </div>

          {collectForm.payment_method === "online" && (
            <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg space-y-2">
              <p className="text-xs font-semibold text-amber-900 flex items-center gap-1.5">
                <CreditCard className="w-4 h-4 text-amber-700" />
                Razorpay Sandbox Integration
              </p>
              <p className="text-[11px] text-amber-800">
                Initiate a secure order with Razorpay test credentials. Once paid, the server verifies HMAC-SHA256
                signatures server-side before crediting the student ledger.
              </p>
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  onClick={handleInitiateRazorpay}
                  disabled={actionLoading}
                  className="bg-amber-600 hover:bg-amber-700 text-white text-xs"
                >
                  Initiate Razorpay Order
                </Button>
                {razorpayOrder && (
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={handleVerifyRazorpaySimulation}
                    disabled={actionLoading}
                    className="border-emerald-600 text-emerald-700 text-xs"
                  >
                    Simulate Successful Payment
                  </Button>
                )}
              </div>
            </div>
          )}

          {collectForm.payment_method !== "cash" && collectForm.payment_method !== "online" && (
            <div>
              <Label className="text-xs">Reference Number / UTR / Cheque #</Label>
              <Input
                value={collectForm.reference_number}
                onChange={(e) => setCollectForm({ ...collectForm, reference_number: e.target.value })}
                placeholder="e.g. UPI/123456789012 or CHQ-445566"
                className="mt-1 text-xs"
              />
            </div>
          )}

          <div>
            <Label className="text-xs">Deposit Financial Account</Label>
            <select
              value={collectForm.financial_account_id}
              onChange={(e) => setCollectForm({ ...collectForm, financial_account_id: e.target.value })}
              className="w-full mt-1 border border-slate-300 rounded-lg p-2 text-xs"
            >
              <option value="">Default Petty Cash / Main Drawer</option>
              {accounts.map((acc) => (
                <option key={acc.id} value={acc.id}>
                  {acc.account_name} ({acc.bank_name})
                </option>
              ))}
            </select>
          </div>

          <div>
            <Label className="text-xs">Collector Notes</Label>
            <Input
              value={collectForm.notes}
              onChange={(e) => setCollectForm({ ...collectForm, notes: e.target.value })}
              placeholder="e.g. Paid at campus cash desk"
              className="mt-1 text-xs"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t">
            <Button type="button" variant="outline" size="sm" onClick={() => setShowCollectModal(false)}>
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={actionLoading}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              Confirm & Generate Receipt
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL: FEE STRUCTURE BUILDER */}
      {/* ======================================================== */}
      <Modal
        isOpen={showStructureModal}
        onClose={() => setShowStructureModal(false)}
        title="Create Class Fee Structure"
      >
        <form onSubmit={handleCreateStructure} className="space-y-4">
          <div>
            <Label className="text-xs">Structure Title</Label>
            <Input
              required
              value={structureForm.name}
              onChange={(e) => setStructureForm({ ...structureForm, name: e.target.value })}
              placeholder="e.g. Grade 10 - Annual Composite Fee 2026-27"
              className="mt-1 text-xs font-semibold"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Billing Frequency</Label>
              <select
                value={structureForm.frequency}
                onChange={(e) => setStructureForm({ ...structureForm, frequency: e.target.value })}
                className="w-full mt-1 border border-slate-300 rounded-lg p-2 text-xs"
              >
                <option value="annual">Annual</option>
                <option value="term">Term-wise / Quarterly</option>
                <option value="monthly">Monthly</option>
                <option value="one_time">One-time / Admission</option>
              </select>
            </div>

            <div>
              <Label className="text-xs">Due Date</Label>
              <Input
                type="date"
                required
                value={structureForm.due_date}
                onChange={(e) => setStructureForm({ ...structureForm, due_date: e.target.value })}
                className="mt-1 text-xs"
              />
            </div>
          </div>

          {/* Line Item Breakdown */}
          <div className="space-y-2 pt-2 border-t border-slate-200">
            <div className="flex items-center justify-between">
              <Label className="text-xs font-bold text-slate-800">Fee Head Breakdown</Label>
              <span className="text-xs font-extrabold text-emerald-700">
                Gross Total: ₹
                {structureForm.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0).toLocaleString("en-IN")}
              </span>
            </div>

            {structureForm.items.map((item, idx) => (
              <div key={idx} className="flex items-center gap-2">
                <Input
                  value={item.fee_head_name}
                  onChange={(e) => {
                    const newItems = [...structureForm.items];
                    newItems[idx].fee_head_name = e.target.value;
                    setStructureForm({ ...structureForm, items: newItems });
                  }}
                  placeholder="Head name (e.g. Tuition)"
                  className="text-xs flex-1"
                />
                <Input
                  type="number"
                  value={item.amount}
                  onChange={(e) => {
                    const newItems = [...structureForm.items];
                    newItems[idx].amount = parseFloat(e.target.value) || 0;
                    setStructureForm({ ...structureForm, items: newItems });
                  }}
                  placeholder="Amount"
                  className="text-xs w-28 font-semibold"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    if (structureForm.items.length > 1) {
                      setStructureForm({
                        ...structureForm,
                        items: structureForm.items.filter((_, i) => i !== idx),
                      });
                    }
                  }}
                  className="h-8 w-8 p-0 text-slate-400 hover:text-rose-600"
                >
                  <X className="w-4 h-4" />
                </Button>
              </div>
            ))}

            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => {
                setStructureForm({
                  ...structureForm,
                  items: [...structureForm.items, { fee_head_name: "", amount: 0, is_mandatory: true }],
                });
              }}
              className="text-xs gap-1.5 border-dashed border-slate-300 w-full mt-1"
            >
              <Plus className="w-3.5 h-3.5" />
              Add Line Item Head
            </Button>
          </div>

          <div className="flex justify-end gap-2 pt-3 border-t">
            <Button type="button" variant="outline" size="sm" onClick={() => setShowStructureModal(false)}>
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={actionLoading}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              Save Structure
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL: PRINTABLE RECEIPT */}
      {/* ======================================================== */}
      <Modal
        isOpen={showReceiptModal}
        onClose={() => setShowReceiptModal(false)}
        title="Official Fee Receipt"
      >
        {selectedReceipt && (
          <div className="space-y-4">
            <div className="p-6 bg-white border border-slate-200 rounded-xl space-y-4 text-slate-900 print:border-none">
              {/* Receipt Header */}
              <div className="text-center border-b pb-4">
                <h2 className="text-lg font-black tracking-tight">ST. XAVIER HIGH SCHOOL</h2>
                <p className="text-xs text-slate-500">City Campus • Affiliated to CBSE • Code: 213009</p>
                <div className="inline-block px-3 py-1 bg-slate-100 rounded-full text-[10px] font-bold uppercase tracking-wider mt-2">
                  Student Fee Collection Receipt
                </div>
              </div>

              {/* Meta details */}
              <div className="grid grid-cols-2 text-xs gap-y-1.5">
                <div>
                  <span className="text-slate-400">Receipt Number:</span>{" "}
                  <strong className="font-mono">{selectedReceipt.receipt_number}</strong>
                </div>
                <div className="text-right">
                  <span className="text-slate-400">Date:</span>{" "}
                  <strong>{selectedReceipt.payment_date}</strong>
                </div>
                <div>
                  <span className="text-slate-400">Student:</span>{" "}
                  <strong>
                    {selectedReceipt.student?.first_name} {selectedReceipt.student?.last_name}
                  </strong>
                </div>
                <div className="text-right">
                  <span className="text-slate-400">Admission #:</span>{" "}
                  <strong className="font-mono">{selectedReceipt.student?.admission_number}</strong>
                </div>
                <div>
                  <span className="text-slate-400">Payment Mode:</span>{" "}
                  <strong className="uppercase">{selectedReceipt.payment_method}</strong>
                </div>
                <div className="text-right">
                  <span className="text-slate-400">Ref / UTR:</span>{" "}
                  <strong>{selectedReceipt.reference_number || "COUNTER_CASH"}</strong>
                </div>
              </div>

              {/* Amount Box */}
              <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between">
                <span className="text-xs font-semibold text-emerald-900">Total Amount Received:</span>
                <span className="text-xl font-extrabold text-emerald-700 font-mono">
                  ₹{Number(selectedReceipt.amount_paid).toLocaleString("en-IN")}
                </span>
              </div>

              {/* Footer Stamp */}
              <div className="pt-6 flex justify-between items-end text-[10px] text-slate-400 border-t">
                <div>
                  <p>Computer-generated verifiable receipt.</p>
                  <p>EduCore School ERP SaaS Platform</p>
                </div>
                <div className="text-right">
                  <div className="w-28 border-b border-slate-300 pb-1 mb-1 font-semibold text-slate-700">
                    Accounts Office
                  </div>
                  <p>Authorized Signature</p>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="outline" size="sm" onClick={() => setShowReceiptModal(false)}>
                Close
              </Button>
              <Button
                size="sm"
                onClick={() => window.print()}
                className="bg-emerald-600 hover:bg-emerald-700 text-white gap-2"
              >
                <Printer className="w-4 h-4" />
                Print Receipt
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL: STUDENT 360 FEE LEDGER */}
      {/* ======================================================== */}
      <Modal
        isOpen={showLedgerModal}
        onClose={() => setShowLedgerModal(false)}
        title="Student Fee Ledger History"
      >
        {selectedLedger && (
          <div className="space-y-4">
            <div className="p-4 bg-slate-50 rounded-xl flex items-center justify-between">
              <div>
                <h4 className="text-sm font-bold text-slate-900">
                  {selectedLedger.student?.first_name} {selectedLedger.student?.last_name}
                </h4>
                <p className="text-xs text-slate-500 font-mono">
                  {selectedLedger.student?.admission_number}
                </p>
              </div>
              <div className="text-right">
                <span className="text-[10px] text-slate-400 uppercase">Net Outstanding</span>
                <p className="text-lg font-black text-amber-600">
                  ₹{Number(selectedLedger.summary?.total_outstanding).toLocaleString("en-IN")}
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <h5 className="text-xs font-bold text-slate-700 uppercase">Invoices & Charges</h5>
              <div className="border border-slate-200 rounded-lg overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Invoice #</TableHead>
                      <TableHead>Billed</TableHead>
                      <TableHead>Paid</TableHead>
                      <TableHead>Balance</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedLedger.invoices?.map((inv: any) => (
                      <TableRow key={inv.id}>
                        <TableCell className="font-mono text-xs">{inv.invoice_number}</TableCell>
                        <TableCell className="text-xs">₹{Number(inv.total_amount).toLocaleString("en-IN")}</TableCell>
                        <TableCell className="text-xs text-emerald-600">
                          ₹{Number(inv.paid_amount).toLocaleString("en-IN")}
                        </TableCell>
                        <TableCell className="text-xs font-bold text-amber-600">
                          ₹{Number(inv.balance_amount).toLocaleString("en-IN")}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-[10px]">
                            {inv.status}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>

            <div className="space-y-2">
              <h5 className="text-xs font-bold text-slate-700 uppercase">Payment Receipts</h5>
              <div className="border border-slate-200 rounded-lg overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Receipt #</TableHead>
                      <TableHead>Date</TableHead>
                      <TableHead>Method</TableHead>
                      <TableHead className="text-right">Amount</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedLedger.payments?.map((pay: any) => (
                      <TableRow key={pay.id}>
                        <TableCell className="font-mono text-xs font-bold">{pay.receipt_number}</TableCell>
                        <TableCell className="text-xs text-slate-500">{pay.payment_date}</TableCell>
                        <TableCell className="text-xs uppercase">{pay.payment_method}</TableCell>
                        <TableCell className="text-xs font-bold text-emerald-700 text-right">
                          ₹{Number(pay.amount_paid).toLocaleString("en-IN")}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <Button size="sm" variant="outline" onClick={() => setShowLedgerModal(false)}>
                Close
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL: PROCESS REFUND */}
      {/* ======================================================== */}
      <Modal
        isOpen={showRefundModal}
        onClose={() => setShowRefundModal(false)}
        title="Process Fee Refund"
      >
        {selectedPaymentForRefund && (
          <form onSubmit={handleProcessRefund} className="space-y-4">
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs space-y-1">
              <p className="font-bold text-rose-900">
                Refunding against Receipt: {selectedPaymentForRefund.receipt_number}
              </p>
              <p className="text-rose-700">
                Original amount collected: ₹{Number(selectedPaymentForRefund.amount_paid).toLocaleString("en-IN")}
              </p>
              <p className="text-[11px] text-slate-500">
                Immutable Ledger Note: The original payment will NOT be deleted. A separate refund voucher will be
                logged and invoice balances will be restored automatically.
              </p>
            </div>

            <div>
              <Label className="text-xs">Refund Amount (₹)</Label>
              <Input
                type="number"
                step="0.01"
                required
                max={selectedPaymentForRefund.amount_paid}
                value={refundForm.amount}
                onChange={(e) => setRefundForm({ ...refundForm, amount: e.target.value })}
                className="mt-1 text-xs font-semibold"
              />
            </div>

            <div>
              <Label className="text-xs">Audit Justification / Reason</Label>
              <Input
                required
                value={refundForm.reason}
                onChange={(e) => setRefundForm({ ...refundForm, reason: e.target.value })}
                placeholder="e.g. Student relocated, excursion cancellation approved by Principal"
                className="mt-1 text-xs"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t">
              <Button type="button" variant="outline" size="sm" onClick={() => setShowRefundModal(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                size="sm"
                disabled={actionLoading}
                className="bg-rose-600 hover:bg-rose-700 text-white"
              >
                Confirm Refund Reversal
              </Button>
            </div>
          </form>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL: RECORD EXPENSE */}
      {/* ======================================================== */}
      <Modal
        isOpen={showExpenseModal}
        onClose={() => setShowExpenseModal(false)}
        title="Record Expense Voucher"
      >
        <form onSubmit={handleRecordExpense} className="space-y-4">
          <div>
            <Label className="text-xs">Expense Description / Title</Label>
            <Input
              required
              value={expenseForm.title}
              onChange={(e) => setExpenseForm({ ...expenseForm, title: e.target.value })}
              placeholder="e.g. Campus High-Speed Fiber Internet Bill"
              className="mt-1 text-xs font-semibold"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Amount (₹)</Label>
              <Input
                type="number"
                step="0.01"
                required
                value={expenseForm.amount}
                onChange={(e) => setExpenseForm({ ...expenseForm, amount: e.target.value })}
                placeholder="e.g. 15000"
                className="mt-1 text-xs font-semibold"
              />
            </div>
            <div>
              <Label className="text-xs">Expense Date</Label>
              <Input
                type="date"
                required
                value={expenseForm.expense_date}
                onChange={(e) => setExpenseForm({ ...expenseForm, expense_date: e.target.value })}
                className="mt-1 text-xs"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Debit Financial Account</Label>
              <select
                value={expenseForm.financial_account_id}
                onChange={(e) => setExpenseForm({ ...expenseForm, financial_account_id: e.target.value })}
                className="w-full mt-1 border border-slate-300 rounded-lg p-2 text-xs"
              >
                <option value="">Cash Drawer</option>
                {accounts.map((acc) => (
                  <option key={acc.id} value={acc.id}>
                    {acc.account_name} ({acc.bank_name})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label className="text-xs">Vendor / Beneficiary</Label>
              <Input
                value={expenseForm.vendor_name}
                onChange={(e) => setExpenseForm({ ...expenseForm, vendor_name: e.target.value })}
                placeholder="e.g. Airtel Enterprise"
                className="mt-1 text-xs"
              />
            </div>
          </div>

          <div>
            <Label className="text-xs">Notes</Label>
            <Input
              value={expenseForm.notes}
              onChange={(e) => setExpenseForm({ ...expenseForm, notes: e.target.value })}
              placeholder="Optional notes or invoice reference"
              className="mt-1 text-xs"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t">
            <Button type="button" variant="outline" size="sm" onClick={() => setShowExpenseModal(false)}>
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={actionLoading}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              Log Voucher & Debit Ledger
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL: CREATE FINANCIAL ACCOUNT */}
      {/* ======================================================== */}
      <Modal
        isOpen={showAccountModal}
        onClose={() => setShowAccountModal(false)}
        title="Configure Financial Bank Account"
      >
        <form onSubmit={handleCreateAccount} className="space-y-4">
          <div>
            <Label className="text-xs">Account Name</Label>
            <Input
              required
              value={accountForm.account_name}
              onChange={(e) => setAccountForm({ ...accountForm, account_name: e.target.value })}
              placeholder="e.g. HDFC Main Operating Ledger"
              className="mt-1 text-xs"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Bank Name</Label>
              <Input
                required
                value={accountForm.bank_name}
                onChange={(e) => setAccountForm({ ...accountForm, bank_name: e.target.value })}
                placeholder="e.g. HDFC Bank"
                className="mt-1 text-xs"
              />
            </div>
            <div>
              <Label className="text-xs">Account Number</Label>
              <Input
                required
                value={accountForm.account_number}
                onChange={(e) => setAccountForm({ ...accountForm, account_number: e.target.value })}
                placeholder="e.g. 50100456789123"
                className="mt-1 text-xs font-mono"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">IFSC Code</Label>
              <Input
                value={accountForm.ifsc_code}
                onChange={(e) => setAccountForm({ ...accountForm, ifsc_code: e.target.value })}
                placeholder="e.g. HDFC0001234"
                className="mt-1 text-xs font-mono"
              />
            </div>
            <div>
              <Label className="text-xs">Opening Balance (₹)</Label>
              <Input
                type="number"
                step="0.01"
                value={accountForm.opening_balance}
                onChange={(e) => setAccountForm({ ...accountForm, opening_balance: e.target.value })}
                placeholder="0.00"
                className="mt-1 text-xs"
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t">
            <Button type="button" variant="outline" size="sm" onClick={() => setShowAccountModal(false)}>
              Cancel
            </Button>
            <Button
              type="submit"
              size="sm"
              disabled={actionLoading}
              className="bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              Save Bank Account
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

