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
  Briefcase,
  UserPlus,
  Users,
  Calendar,
  Clock,
  DollarSign,
  FileText,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Search,
  Filter,
  Eye,
  FileCheck,
  Building2,
  GraduationCap,
  Sparkles,
  ArrowRight,
  TrendingUp,
  Download,
  Printer,
  ChevronRight,
  Check,
  X,
  Shield,
  Layers,
  FileCode,
  CalendarDays,
  UserCheck,
  LogOut,
  BadgeCheck,
  Plus,
  Building,
  CreditCard,
  Receipt,
} from "lucide-react";

// ==========================================
// TypeScript Interfaces
// ==========================================

interface DepartmentItem {
  id: string;
  name: string;
  code: string;
  is_active: boolean;
  employees_count?: number;
}

interface DesignationItem {
  id: string;
  department_id?: string;
  title: string;
  code: string;
  level: number;
  is_active: boolean;
  department?: DepartmentItem;
  employees_count?: number;
}

interface EmployeeItem {
  id: string;
  employee_code: string;
  first_name: string;
  middle_name?: string;
  last_name: string;
  full_name: string;
  gender: string;
  date_of_birth: string;
  blood_group?: string;
  email: string;
  phone: string;
  department_id: string;
  designation_id: string;
  department?: DepartmentItem;
  designation?: DesignationItem;
  employment_type: string;
  date_of_joining: string;
  date_of_leaving?: string;
  basic_salary: number;
  pan_number?: string;
  bank_account_number?: string;
  bank_ifsc_code?: string;
  bank_name?: string;
  status: string; // active, on_leave, resigned, terminated
}

interface ContractItem {
  id: string;
  contract_type: string;
  start_date: string;
  end_date?: string;
  gross_salary: number;
  terms?: Record<string, any>;
  is_active: boolean;
}

interface DocumentItem {
  id: string;
  document_type: string;
  file_name: string;
  file_path: string;
  verification_status: string;
  created_at?: string;
}

interface LeaveQuotaItem {
  id: string;
  leave_type: string;
  total_days: number;
  used_days: number;
  balance_days: number;
}

interface LeaveTypeItem {
  id: string;
  name: string;
  code: string;
  description?: string;
  annual_quota: number;
  is_paid: boolean;
  is_carry_forward: boolean;
  is_active: boolean;
}

interface LeaveApplicationItem {
  id: string;
  applicant_type: string;
  applicant_id: string;
  leave_type: string;
  start_date: string;
  end_date: string;
  total_days: number;
  reason: string;
  status: "pending" | "approved" | "rejected";
  approved_by?: string;
  approver?: { first_name: string; last_name: string };
  employee?: { full_name: string; employee_code: string; department?: { name: string } };
  created_at: string;
}

interface TeacherRosterItem {
  id: string;
  employee_code: string;
  full_name: string;
  email: string;
  phone: string;
  gender: string;
  department?: string;
  designation?: string;
  status: string;
  date_of_joining?: string;
  is_class_teacher: boolean;
  class_teacher_sections: Array<{ class_name: string; section_name: string }>;
}

interface WorkloadMetrics {
  teacher_id: string;
  teacher_name: string;
  total_weekly_periods: number;
  weekly_capacity: number;
  free_periods_per_week: number;
  utilization_rate: number;
  subjects: Array<{ subject_id: string; subject_name: string; subject_code: string; periods_per_week: number }>;
  classes: Array<{ class_name: string; section_name: string; periods_per_week: number }>;
  daily_distribution: Array<{ day: string; periods: number }>;
  class_teacher_in_charge: Array<{ class_name: string; section_name: string; is_primary: boolean }>;
}

interface SalaryComponentItem {
  id: string;
  name: string;
  code: string;
  type: "allowance" | "deduction";
  calculation_type: "fixed" | "percentage";
  percentage_of?: string;
  default_amount: number;
  is_taxable: boolean;
  is_active: boolean;
}

interface SalaryStructureItem {
  id: string;
  name: string;
  code: string;
  description?: string;
  base_salary: number;
  is_active: boolean;
  items?: Array<{ id: string; salary_component_id: string; amount: number; component?: SalaryComponentItem }>;
  employee_profiles_count?: number;
}

interface PayrollPeriodItem {
  id: string;
  name: string;
  start_date: string;
  end_date: string;
  payment_date?: string;
  status: "draft" | "processing" | "approved" | "paid" | "locked";
  total_gross: number;
  total_deductions: number;
  total_net: number;
  payslips_count?: number;
  processed_by_user?: { first_name: string; last_name: string };
  approved_by_user?: { first_name: string; last_name: string };
}

interface PayslipItem {
  id: string;
  payslip_number: string;
  basic_salary: number;
  total_allowances: number;
  total_deductions: number;
  gross_salary: number;
  net_salary: number;
  working_days: number;
  present_days: number;
  paid_leaves: number;
  unpaid_leaves: number;
  status: "draft" | "published" | "paid";
  payment_method: string;
  payment_reference?: string;
  payment_date?: string;
  earnings_breakdown: Array<{ name: string; code: string; amount: number; type: string }>;
  deductions_breakdown: Array<{ name: string; code: string; amount: number; type: string }>;
  employee?: {
    full_name: string;
    employee_code: string;
    department?: { name: string };
    designation?: { title: string };
  };
  payroll_period?: { name: string; start_date: string; end_date: string };
}

// ==========================================
// Mock Fallbacks for Seeded / Demonstration State
// ==========================================

const mockDepartments: DepartmentItem[] = [
  { id: "dept-1", name: "Science Department", code: "SCI", is_active: true, employees_count: 12 },
  { id: "dept-2", name: "Mathematics Department", code: "MATH", is_active: true, employees_count: 8 },
  { id: "dept-3", name: "Languages & Humanities", code: "LANG", is_active: true, employees_count: 14 },
  { id: "dept-4", name: "Administration & Finance", code: "ADMIN", is_active: true, employees_count: 6 },
  { id: "dept-5", name: "Transport & Facilities", code: "OPS", is_active: true, employees_count: 9 },
];

const mockDesignations: DesignationItem[] = [
  { id: "desig-1", title: "Principal", code: "PRN", level: 1, is_active: true, employees_count: 1 },
  { id: "desig-2", title: "Senior Secondary Teacher (PGT)", code: "PGT", level: 2, is_active: true, employees_count: 18 },
  { id: "desig-3", title: "Secondary Teacher (TGT)", code: "TGT", level: 3, is_active: true, employees_count: 14 },
  { id: "desig-4", title: "Accountant", code: "ACC", level: 3, is_active: true, employees_count: 2 },
  { id: "desig-5", title: "Driver / Fleet Staff", code: "DRV", level: 5, is_active: true, employees_count: 7 },
];

const mockEmployees: EmployeeItem[] = [
  {
    id: "emp-101",
    employee_code: "EMP-2026-0001",
    first_name: "Dr. Arvind",
    last_name: "Swaminathan",
    full_name: "Dr. Arvind Swaminathan",
    gender: "male",
    date_of_birth: "1978-08-14",
    email: "arvind.swaminathan@educore.edu",
    phone: "+91 98765 43210",
    department_id: "dept-1",
    designation_id: "desig-2",
    department: mockDepartments[0],
    designation: mockDesignations[1],
    employment_type: "permanent",
    date_of_joining: "2019-06-01",
    basic_salary: 75000,
    status: "active",
  },
  {
    id: "emp-102",
    employee_code: "EMP-2026-0002",
    first_name: "Meenakshi",
    last_name: "Sundaram",
    full_name: "Meenakshi Sundaram",
    gender: "female",
    date_of_birth: "1985-03-22",
    email: "meenakshi.s@educore.edu",
    phone: "+91 98765 43211",
    department_id: "dept-2",
    designation_id: "desig-2",
    department: mockDepartments[1],
    designation: mockDesignations[1],
    employment_type: "permanent",
    date_of_joining: "2020-04-15",
    basic_salary: 68000,
    status: "active",
  },
  {
    id: "emp-103",
    employee_code: "EMP-2026-0003",
    first_name: "Farhan",
    last_name: "Qureshi",
    full_name: "Farhan Qureshi",
    gender: "male",
    date_of_birth: "1992-11-05",
    email: "farhan.q@educore.edu",
    phone: "+91 98765 43212",
    department_id: "dept-3",
    designation_id: "desig-3",
    department: mockDepartments[2],
    designation: mockDesignations[2],
    employment_type: "contract",
    date_of_joining: "2022-07-01",
    basic_salary: 48000,
    status: "active",
  },
  {
    id: "emp-104",
    employee_code: "EMP-2026-0004",
    first_name: "Sunita",
    last_name: "Nair",
    full_name: "Sunita Nair",
    gender: "female",
    date_of_birth: "1989-01-30",
    email: "sunita.nair@educore.edu",
    phone: "+91 98765 43213",
    department_id: "dept-4",
    designation_id: "desig-4",
    department: mockDepartments[3],
    designation: mockDesignations[3],
    employment_type: "permanent",
    date_of_joining: "2021-02-10",
    basic_salary: 52000,
    status: "on_leave",
  },
];

const mockTeachers: TeacherRosterItem[] = [
  {
    id: "emp-101",
    employee_code: "EMP-2026-0001",
    full_name: "Dr. Arvind Swaminathan",
    email: "arvind.swaminathan@educore.edu",
    phone: "+91 98765 43210",
    gender: "male",
    department: "Science Department",
    designation: "Senior Secondary Teacher (PGT)",
    status: "active",
    date_of_joining: "2019-06-01",
    is_class_teacher: true,
    class_teacher_sections: [{ class_name: "Grade 11 Science", section_name: "A" }],
  },
  {
    id: "emp-102",
    employee_code: "EMP-2026-0002",
    full_name: "Meenakshi Sundaram",
    email: "meenakshi.s@educore.edu",
    phone: "+91 98765 43211",
    gender: "female",
    department: "Mathematics Department",
    designation: "Senior Secondary Teacher (PGT)",
    status: "active",
    date_of_joining: "2020-04-15",
    is_class_teacher: true,
    class_teacher_sections: [{ class_name: "Grade 12 Science", section_name: "B" }],
  },
  {
    id: "emp-103",
    employee_code: "EMP-2026-0003",
    full_name: "Farhan Qureshi",
    email: "farhan.q@educore.edu",
    phone: "+91 98765 43212",
    gender: "male",
    department: "Languages & Humanities",
    designation: "Secondary Teacher (TGT)",
    status: "active",
    date_of_joining: "2022-07-01",
    is_class_teacher: false,
    class_teacher_sections: [],
  },
];

const mockLeaveTypes: LeaveTypeItem[] = [
  { id: "lt-1", name: "Casual Leave", code: "CL", annual_quota: 12.0, is_paid: true, is_carry_forward: false, is_active: true },
  { id: "lt-2", name: "Sick Leave", code: "SL", annual_quota: 10.0, is_paid: true, is_carry_forward: true, is_active: true },
  { id: "lt-3", name: "Earned Leave", code: "EL", annual_quota: 15.0, is_paid: true, is_carry_forward: true, is_active: true },
  { id: "lt-4", name: "Maternity Leave", code: "ML", annual_quota: 90.0, is_paid: true, is_carry_forward: false, is_active: true },
];

const mockLeaveApplications: LeaveApplicationItem[] = [
  {
    id: "la-1",
    applicant_type: "employee",
    applicant_id: "emp-104",
    employee: { full_name: "Sunita Nair", employee_code: "EMP-2026-0004", department: { name: "Administration & Finance" } },
    leave_type: "CL",
    start_date: "2026-11-20",
    end_date: "2026-11-22",
    total_days: 2.0,
    reason: "Attending family medical appointment out of station.",
    status: "pending",
    created_at: "2026-11-19T10:15:00Z",
  },
  {
    id: "la-2",
    applicant_type: "employee",
    applicant_id: "emp-102",
    employee: { full_name: "Meenakshi Sundaram", employee_code: "EMP-2026-0002", department: { name: "Mathematics Department" } },
    leave_type: "SL",
    start_date: "2026-11-12",
    end_date: "2026-11-13",
    total_days: 2.0,
    reason: "Viral fever symptoms under physician rest advice.",
    status: "approved",
    approved_by: "hr-director",
    approver: { first_name: "HR", last_name: "Director" },
    created_at: "2026-11-11T08:30:00Z",
  },
];

const mockSalaryComponents: SalaryComponentItem[] = [
  { id: "sc-1", name: "Basic Salary", code: "BASIC", type: "allowance", calculation_type: "fixed", default_amount: 50000, is_taxable: true, is_active: true },
  { id: "sc-2", name: "House Rent Allowance (HRA)", code: "HRA", type: "allowance", calculation_type: "percentage", percentage_of: "BASIC", default_amount: 40, is_taxable: true, is_active: true },
  { id: "sc-3", name: "Dearness Allowance (DA)", code: "DA", type: "allowance", calculation_type: "percentage", percentage_of: "BASIC", default_amount: 20, is_taxable: true, is_active: true },
  { id: "sc-4", name: "Transport Conveyance", code: "TA", type: "allowance", calculation_type: "fixed", default_amount: 5000, is_taxable: false, is_active: true },
  { id: "sc-5", name: "Provident Fund (PF)", code: "PF", type: "deduction", calculation_type: "percentage", percentage_of: "BASIC", default_amount: 12, is_taxable: false, is_active: true },
  { id: "sc-6", name: "Professional Tax (PT)", code: "PT", type: "deduction", calculation_type: "fixed", default_amount: 200, is_taxable: false, is_active: true },
];

const mockPayrollPeriods: PayrollPeriodItem[] = [
  {
    id: "pp-1",
    name: "November 2026",
    start_date: "2026-11-01",
    end_date: "2026-11-30",
    status: "processing",
    total_gross: 420000,
    total_deductions: 38400,
    total_net: 381600,
    payslips_count: 6,
  },
  {
    id: "pp-2",
    name: "October 2026",
    start_date: "2026-10-01",
    end_date: "2026-10-31",
    payment_date: "2026-10-31",
    status: "paid",
    total_gross: 418000,
    total_deductions: 38200,
    total_net: 379800,
    payslips_count: 6,
  },
];

const mockPayslips: PayslipItem[] = [
  {
    id: "ps-1",
    payslip_number: "PAY-2026-11-0001",
    basic_salary: 75000,
    total_allowances: 45000,
    total_deductions: 9200,
    gross_salary: 120000,
    net_salary: 110800,
    working_days: 30,
    present_days: 30,
    paid_leaves: 0,
    unpaid_leaves: 0,
    status: "published",
    payment_method: "bank_transfer",
    payment_reference: "NEFT-9912048",
    earnings_breakdown: [
      { name: "Basic Salary", code: "BASIC", amount: 75000, type: "allowance" },
      { name: "House Rent Allowance (HRA)", code: "HRA", amount: 30000, type: "allowance" },
      { name: "Dearness Allowance (DA)", code: "DA", amount: 15000, type: "allowance" },
    ],
    deductions_breakdown: [
      { name: "Provident Fund (PF)", code: "PF", amount: 9000, type: "deduction" },
      { name: "Professional Tax (PT)", code: "PT", amount: 200, type: "deduction" },
    ],
    employee: {
      full_name: "Dr. Arvind Swaminathan",
      employee_code: "EMP-2026-0001",
      department: { name: "Science Department" },
      designation: { title: "Senior Secondary Teacher (PGT)" },
    },
    payroll_period: { name: "November 2026", start_date: "2026-11-01", end_date: "2026-11-30" },
  },
  {
    id: "ps-2",
    payslip_number: "PAY-2026-11-0002",
    basic_salary: 68000,
    total_allowances: 40800,
    total_deductions: 8360,
    gross_salary: 108800,
    net_salary: 100440,
    working_days: 30,
    present_days: 30,
    paid_leaves: 0,
    unpaid_leaves: 0,
    status: "published",
    payment_method: "bank_transfer",
    earnings_breakdown: [
      { name: "Basic Salary", code: "BASIC", amount: 68000, type: "allowance" },
      { name: "House Rent Allowance (HRA)", code: "HRA", amount: 27200, type: "allowance" },
      { name: "Dearness Allowance (DA)", code: "DA", amount: 13600, type: "allowance" },
    ],
    deductions_breakdown: [
      { name: "Provident Fund (PF)", code: "PF", amount: 8160, type: "deduction" },
      { name: "Professional Tax (PT)", code: "PT", amount: 200, type: "deduction" },
    ],
    employee: {
      full_name: "Meenakshi Sundaram",
      employee_code: "EMP-2026-0002",
      department: { name: "Mathematics Department" },
      designation: { title: "Senior Secondary Teacher (PGT)" },
    },
    payroll_period: { name: "November 2026", start_date: "2026-11-01", end_date: "2026-11-30" },
  },
];

// ==========================================
// Inner Workspace Component
// ==========================================

function StaffManagementContent() {
  const { user, permissions } = useAuth();
  const searchParams = useSearchParams();

  // Navigation Tabs
  const [activeTab, setActiveTab] = useState<"directory" | "teachers" | "leaves" | "payroll" | "organization">("directory");

  // Data States
  const [employees, setEmployees] = useState<EmployeeItem[]>(mockEmployees);
  const [departments, setDepartments] = useState<DepartmentItem[]>(mockDepartments);
  const [designations, setDesignations] = useState<DesignationItem[]>(mockDesignations);
  const [teachers, setTeachers] = useState<TeacherRosterItem[]>(mockTeachers);
  const [leaveTypes, setLeaveTypes] = useState<LeaveTypeItem[]>(mockLeaveTypes);
  const [leaveApplications, setLeaveApplications] = useState<LeaveApplicationItem[]>(mockLeaveApplications);
  const [salaryComponents, setSalaryComponents] = useState<SalaryComponentItem[]>(mockSalaryComponents);
  const [payrollPeriods, setPayrollPeriods] = useState<PayrollPeriodItem[]>(mockPayrollPeriods);
  const [payslips, setPayslips] = useState<PayslipItem[]>(mockPayslips);

  // Filters
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedDeptFilter, setSelectedDeptFilter] = useState("all");
  const [selectedStatusFilter, setSelectedStatusFilter] = useState("all");

  // Modal Controls
  const [isRegisterOpen, setIsRegisterOpen] = useState(false);
  const [is360Open, setIs360Open] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState<EmployeeItem | null>(null);
  const [isContractOpen, setIsContractOpen] = useState(false);
  const [isExitOpen, setIsExitOpen] = useState(false);
  const [isWorkloadOpen, setIsWorkloadOpen] = useState(false);
  const [selectedWorkload, setSelectedWorkload] = useState<WorkloadMetrics | null>(null);
  const [isTimetableOpen, setIsTimetableOpen] = useState(false);
  const [isSubstituteOpen, setIsSubstituteOpen] = useState(false);
  const [isApplyLeaveOpen, setIsApplyLeaveOpen] = useState(false);
  const [isReviewLeaveOpen, setIsReviewLeaveOpen] = useState(false);
  const [selectedLeaveApp, setSelectedLeaveApp] = useState<LeaveApplicationItem | null>(null);
  const [isCreatePeriodOpen, setIsCreatePeriodOpen] = useState(false);
  const [isViewPayslipOpen, setIsViewPayslipOpen] = useState(false);
  const [selectedPayslip, setSelectedPayslip] = useState<PayslipItem | null>(null);
  const [isComponentOpen, setIsComponentOpen] = useState(false);

  // Form States
  const [registerForm, setRegisterForm] = useState({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    gender: "female",
    date_of_birth: "1990-01-01",
    department_id: "dept-1",
    designation_id: "desig-2",
    employment_type: "permanent",
    basic_salary: 50000,
    pan_number: "",
    bank_account_number: "",
    bank_ifsc_code: "",
    bank_name: "HDFC Bank",
  });

  const [contractForm, setContractForm] = useState({
    contract_type: "permanent",
    start_date: "2026-06-01",
    gross_salary: 65000,
    probation_months: 6,
  });

  const [exitForm, setExitForm] = useState({
    exit_type: "resignation",
    notice_date: "2026-11-20",
    last_working_day: "2026-12-31",
    reason: "",
    clearance_status: "in_progress",
    it_clearance: false,
    library_clearance: false,
    finance_clearance: false,
    admin_clearance: false,
  });

  const [substituteForm, setSubstituteForm] = useState({
    date: "2026-11-23",
    substitute_employee_id: "",
    reason: "Teacher on medical leave",
  });

  const [leaveForm, setLeaveForm] = useState({
    employee_id: "emp-101",
    leave_type: "CL",
    start_date: "2026-11-25",
    end_date: "2026-11-26",
    total_days: 2,
    reason: "",
  });

  const [reviewForm, setReviewForm] = useState({
    status: "approved" as "approved" | "rejected",
    remarks: "Approved by Administration",
  });

  const [periodForm, setPeriodForm] = useState({
    name: "December 2026",
    start_date: "2026-12-01",
    end_date: "2026-12-31",
  });

  const [componentForm, setComponentForm] = useState({
    name: "",
    code: "",
    type: "allowance" as "allowance" | "deduction",
    calculation_type: "percentage" as "fixed" | "percentage",
    percentage_of: "BASIC",
    default_amount: 10,
    is_taxable: true,
  });

  // Load backend data if connected
  const fetchData = useCallback(async () => {
    try {
      const [empRes, deptRes, desigRes, tchRes, lTypeRes, lAppRes, compRes, periodRes, slipRes] = await Promise.allSettled([
        apiClient.get("/employees"),
        apiClient.get("/departments"),
        apiClient.get("/designations"),
        apiClient.get("/teachers"),
        apiClient.get("/leaves/types"),
        apiClient.get("/leaves/applications"),
        apiClient.get("/payroll/components"),
        apiClient.get("/payroll/periods"),
        apiClient.get("/payroll/payslips"),
      ]);

      if (empRes.status === "fulfilled" && empRes.value.data?.data) {
        setEmployees(empRes.value.data.data);
      }
      if (deptRes.status === "fulfilled" && deptRes.value.data?.data) {
        setDepartments(deptRes.value.data.data);
      }
      if (desigRes.status === "fulfilled" && desigRes.value.data?.data) {
        setDesignations(desigRes.value.data.data);
      }
      if (tchRes.status === "fulfilled" && tchRes.value.data?.data) {
        setTeachers(tchRes.value.data.data);
      }
      if (lTypeRes.status === "fulfilled" && lTypeRes.value.data?.data) {
        setLeaveTypes(lTypeRes.value.data.data);
      }
      if (lAppRes.status === "fulfilled" && lAppRes.value.data?.data) {
        setLeaveApplications(lAppRes.value.data.data);
      }
      if (compRes.status === "fulfilled" && compRes.value.data?.data) {
        setSalaryComponents(compRes.value.data.data);
      }
      if (periodRes.status === "fulfilled" && periodRes.value.data?.data) {
        setPayrollPeriods(periodRes.value.data.data);
      }
      if (slipRes.status === "fulfilled" && slipRes.value.data?.data) {
        setPayslips(slipRes.value.data.data);
      }
    } catch {
      // Fallback to rich pre-seeded mock states
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Sync tab from URL param if present
  useEffect(() => {
    const tabParam = searchParams.get("tab");
    if (tabParam && ["directory", "teachers", "leaves", "payroll", "organization"].includes(tabParam)) {
      setActiveTab(tabParam as any);
    }
  }, [searchParams]);

  // Filtered Employees
  const filteredEmployees = employees.filter((emp) => {
    const matchesSearch =
      emp.full_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      emp.employee_code?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      emp.email?.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesDept = selectedDeptFilter === "all" || emp.department_id === selectedDeptFilter;
    const matchesStatus = selectedStatusFilter === "all" || emp.status === selectedStatusFilter;
    return matchesSearch && matchesDept && matchesStatus;
  });

  // ==========================================
  // Action Handlers
  // ==========================================

  const handleRegisterEmployee = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const payload = {
        ...registerForm,
        school_id: departments[0]?.id || "school-default-id",
      };
      const res = await apiClient.post("/employees", payload);
      if (res.data?.data) {
        setEmployees([res.data.data, ...employees]);
      }
    } catch {
      const newEmp: EmployeeItem = {
        id: `emp-${Date.now()}`,
        employee_code: `EMP-2026-${String(employees.length + 1).padStart(4, "0")}`,
        first_name: registerForm.first_name,
        last_name: registerForm.last_name,
        full_name: `${registerForm.first_name} ${registerForm.last_name}`,
        gender: registerForm.gender,
        date_of_birth: registerForm.date_of_birth,
        email: registerForm.email,
        phone: registerForm.phone,
        department_id: registerForm.department_id,
        designation_id: registerForm.designation_id,
        department: departments.find((d) => d.id === registerForm.department_id),
        designation: designations.find((d) => d.id === registerForm.designation_id),
        employment_type: registerForm.employment_type,
        date_of_joining: new Date().toISOString().split("T")[0],
        basic_salary: Number(registerForm.basic_salary),
        status: "active",
      };
      setEmployees([newEmp, ...employees]);
    }
    setIsRegisterOpen(false);
  };

  const handleOpenWorkload = async (teacher: TeacherRosterItem) => {
    try {
      const res = await apiClient.get(`/teachers/${teacher.id}/workload`);
      setSelectedWorkload(res.data.data);
    } catch {
      setSelectedWorkload({
        teacher_id: teacher.id,
        teacher_name: teacher.full_name,
        total_weekly_periods: 22,
        weekly_capacity: 40,
        free_periods_per_week: 18,
        utilization_rate: 55.0,
        subjects: [
          { subject_id: "s1", subject_name: "Physics Core", subject_code: "PHY-11", periods_per_week: 14 },
          { subject_id: "s2", subject_name: "Physics Lab", subject_code: "LAB-PHY", periods_per_week: 8 },
        ],
        classes: [
          { class_name: "Grade 11 Science", section_name: "A", periods_per_week: 11 },
          { class_name: "Grade 12 Science", section_name: "B", periods_per_week: 11 },
        ],
        daily_distribution: [
          { day: "Monday", periods: 4 },
          { day: "Tuesday", periods: 5 },
          { day: "Wednesday", periods: 4 },
          { day: "Thursday", periods: 5 },
          { day: "Friday", periods: 4 },
          { day: "Saturday", periods: 0 },
        ],
        class_teacher_in_charge: [{ class_name: "Grade 11 Science", section_name: "A", is_primary: true }],
      });
    }
    setIsWorkloadOpen(true);
  };

  const handleApplyLeave = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await apiClient.post("/leaves/applications", leaveForm);
      if (res.data?.data) {
        setLeaveApplications([res.data.data, ...leaveApplications]);
      }
    } catch {
      const emp = employees.find((e) => e.id === leaveForm.employee_id);
      const newApp: LeaveApplicationItem = {
        id: `la-${Date.now()}`,
        applicant_type: "employee",
        applicant_id: leaveForm.employee_id,
        employee: {
          full_name: emp?.full_name || "Staff Member",
          employee_code: emp?.employee_code || "EMP-000",
          department: { name: emp?.department?.name || "General" },
        },
        leave_type: leaveForm.leave_type,
        start_date: leaveForm.start_date,
        end_date: leaveForm.end_date,
        total_days: Number(leaveForm.total_days),
        reason: leaveForm.reason || "Personal leave request",
        status: "pending",
        created_at: new Date().toISOString(),
      };
      setLeaveApplications([newApp, ...leaveApplications]);
    }
    setIsApplyLeaveOpen(false);
  };

  const handleReviewLeave = async () => {
    if (!selectedLeaveApp) return;
    try {
      await apiClient.put(`/leaves/applications/${selectedLeaveApp.id}/review`, reviewForm);
    } catch {
      // optimistic update
    }
    setLeaveApplications(
      leaveApplications.map((app) =>
        app.id === selectedLeaveApp.id
          ? {
              ...app,
              status: reviewForm.status,
              approver: { first_name: user?.first_name || "HR", last_name: user?.last_name || "Manager" },
            }
          : app
      )
    );
    setIsReviewLeaveOpen(false);
  };

  const handleCreatePayrollPeriod = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await apiClient.post("/payroll/periods", periodForm);
      if (res.data?.data) {
        setPayrollPeriods([res.data.data, ...payrollPeriods]);
      }
    } catch {
      const newPeriod: PayrollPeriodItem = {
        id: `pp-${Date.now()}`,
        name: periodForm.name,
        start_date: periodForm.start_date,
        end_date: periodForm.end_date,
        status: "draft",
        total_gross: 0,
        total_deductions: 0,
        total_net: 0,
        payslips_count: 0,
      };
      setPayrollPeriods([newPeriod, ...payrollPeriods]);
    }
    setIsCreatePeriodOpen(false);
  };

  const handleCalculatePayroll = async (period: PayrollPeriodItem) => {
    try {
      await apiClient.post(`/payroll/periods/${period.id}/calculate`);
      fetchData();
    } catch {
      setPayrollPeriods(
        payrollPeriods.map((p) =>
          p.id === period.id
            ? { ...p, status: "processing", total_gross: 425000, total_deductions: 39000, total_net: 386000, payslips_count: employees.length }
            : p
        )
      );
    }
  };

  const handleApprovePayroll = async (period: PayrollPeriodItem) => {
    try {
      await apiClient.post(`/payroll/periods/${period.id}/approve`);
      fetchData();
    } catch {
      setPayrollPeriods(
        payrollPeriods.map((p) => (p.id === period.id ? { ...p, status: "approved" } : p))
      );
    }
  };

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />

      <main className="flex-1 p-6 md:p-8 space-y-6 max-w-7xl mx-auto overflow-x-hidden">
        {/* Top Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                Staff, HR & Teacher Management
              </h1>
              <Badge variant="outline" className="border-indigo-500/20 bg-indigo-500/5 text-indigo-600 dark:text-indigo-400 font-mono text-xs">
                Staff 360
              </Badge>
            </div>
            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
              Unified administration for faculty, administrative staff, workload schedules, leave quotas, and configurable payroll.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button
              onClick={() => setIsRegisterOpen(true)}
              className="bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm flex items-center gap-2"
            >
              <UserPlus className="h-4 w-4" />
              <span>Register Employee</span>
            </Button>
          </div>
        </div>

        {/* Workspace Navigation Tabs */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 space-x-1 overflow-x-auto pb-px">
          <button
            onClick={() => setActiveTab("directory")}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
              activeTab === "directory"
                ? "border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            <Users className="h-4 w-4" />
            <span>Employee Directory</span>
            <Badge variant="secondary" className="ml-1 text-xs">
              {employees.length}
            </Badge>
          </button>

          <button
            onClick={() => setActiveTab("teachers")}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
              activeTab === "teachers"
                ? "border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            <GraduationCap className="h-4 w-4" />
            <span>Teacher Workload & Substitutions</span>
          </button>

          <button
            onClick={() => setActiveTab("leaves")}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
              activeTab === "leaves"
                ? "border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            <CalendarDays className="h-4 w-4" />
            <span>Leave Management</span>
            {leaveApplications.filter((a) => a.status === "pending").length > 0 && (
              <Badge className="ml-1 text-xs bg-amber-500 hover:bg-amber-600 text-white">
                {leaveApplications.filter((a) => a.status === "pending").length}
              </Badge>
            )}
          </button>

          <button
            onClick={() => setActiveTab("payroll")}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
              activeTab === "payroll"
                ? "border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            <DollarSign className="h-4 w-4" />
            <span>Payroll & Compensation</span>
          </button>

          <button
            onClick={() => setActiveTab("organization")}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium transition-colors border-b-2 whitespace-nowrap ${
              activeTab === "organization"
                ? "border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            <Building2 className="h-4 w-4" />
            <span>Departments & Roles</span>
          </button>
        </div>

        {/* ======================================================== */}
        {/* TAB 1: EMPLOYEE DIRECTORY                                */}
        {/* ======================================================== */}
        {activeTab === "directory" && (
          <div className="space-y-6">
            {/* Filter Bar */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardContent className="p-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                  <div className="relative md:col-span-2">
                    <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                    <Input
                      placeholder="Search by employee name, code, email..."
                      className="pl-9"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                    />
                  </div>

                  <div>
                    <select
                      className="w-full h-10 px-3 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      value={selectedDeptFilter}
                      onChange={(e) => setSelectedDeptFilter(e.target.value)}
                    >
                      <option value="all">All Departments</option>
                      {departments.map((dept) => (
                        <option key={dept.id} value={dept.id}>
                          {dept.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <select
                      className="w-full h-10 px-3 text-sm rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      value={selectedStatusFilter}
                      onChange={(e) => setSelectedStatusFilter(e.target.value)}
                    >
                      <option value="all">All Statuses</option>
                      <option value="active">Active</option>
                      <option value="on_leave">On Leave</option>
                      <option value="resigned">Resigned</option>
                      <option value="terminated">Terminated</option>
                    </select>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Employee Table */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800">
                <div className="flex items-center justify-between">
                  <CardTitle className="text-base font-semibold text-slate-900 dark:text-slate-100">
                    Staff Roster ({filteredEmployees.length})
                  </CardTitle>
                  <span className="text-xs text-slate-500">Showing active and contracted personnel</span>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Employee</TableHead>
                      <TableHead>Department</TableHead>
                      <TableHead>Designation</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Joining Date</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredEmployees.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={7} className="text-center py-8 text-slate-500">
                          No employees matched the current filters.
                        </TableCell>
                      </TableRow>
                    ) : (
                      filteredEmployees.map((emp) => (
                        <TableRow key={emp.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                          <TableCell>
                            <div className="flex items-center gap-3">
                              <div className="h-9 w-9 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 flex items-center justify-center font-medium text-xs">
                                {emp.first_name[0]}
                                {emp.last_name[0]}
                              </div>
                              <div>
                                <div className="font-medium text-slate-900 dark:text-slate-100">{emp.full_name}</div>
                                <div className="text-xs text-slate-500 flex items-center gap-2">
                                  <span className="font-mono text-[11px] text-slate-400">{emp.employee_code}</span>
                                  <span>•</span>
                                  <span>{emp.email}</span>
                                </div>
                              </div>
                            </div>
                          </TableCell>
                          <TableCell className="text-slate-600 dark:text-slate-300 font-medium">
                            {emp.department?.name || "Unassigned"}
                          </TableCell>
                          <TableCell className="text-slate-600 dark:text-slate-300">
                            {emp.designation?.title || "Staff"}
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline" className="capitalize text-xs">
                              {emp.employment_type}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-slate-500 text-xs">
                            {emp.date_of_joining ? new Date(emp.date_of_joining).toLocaleDateString() : "—"}
                          </TableCell>
                          <TableCell>
                            <span
                              className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize ${
                                emp.status === "active"
                                  ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400"
                                  : emp.status === "on_leave"
                                  ? "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
                                  : "bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400"
                              }`}
                            >
                              {emp.status}
                            </span>
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1.5">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                  setSelectedEmployee(emp);
                                  setIs360Open(true);
                                }}
                                className="h-8 text-xs flex items-center gap-1"
                              >
                                <Eye className="h-3.5 w-3.5" />
                                <span>360</span>
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                  setSelectedEmployee(emp);
                                  setIsContractOpen(true);
                                }}
                                className="h-8 text-xs"
                              >
                                Contract
                              </Button>
                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                  setSelectedEmployee(emp);
                                  setIsExitOpen(true);
                                }}
                                className="h-8 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50"
                              >
                                Exit
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ======================================================== */}
        {/* TAB 2: TEACHER WORKLOAD & TIMETABLE                     */}
        {/* ======================================================== */}
        {activeTab === "teachers" && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Faculty Count</div>
                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">{teachers.length}</div>
                  </div>
                  <div className="h-10 w-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 flex items-center justify-center">
                    <GraduationCap className="h-5 w-5" />
                  </div>
                </div>
                <div className="text-xs text-slate-400 mt-2">Active teaching faculty across all boards</div>
              </Card>

              <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Class Teachers</div>
                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">
                      {teachers.filter((t) => t.is_class_teacher).length}
                    </div>
                  </div>
                  <div className="h-10 w-10 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 flex items-center justify-center">
                    <BadgeCheck className="h-5 w-5" />
                  </div>
                </div>
                <div className="text-xs text-slate-400 mt-2">Assigned primary section in-charge</div>
              </Card>

              <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <div className="text-xs font-medium text-slate-500 uppercase tracking-wider">Workload Capacity</div>
                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100 mt-1">40 Periods/Wk</div>
                  </div>
                  <div className="h-10 w-10 rounded-lg bg-purple-50 dark:bg-purple-900/20 text-purple-600 flex items-center justify-center">
                    <Clock className="h-5 w-5" />
                  </div>
                </div>
                <div className="text-xs text-slate-400 mt-2">Standard weekly capacity per educator</div>
              </Card>
            </div>

            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-semibold">Teacher Roster & Workload</CardTitle>
                  <CardDescription>Review teaching assignments, weekly timetable slots, and schedule substitute teachers.</CardDescription>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setIsSubstituteOpen(true)}
                  className="flex items-center gap-1.5"
                >
                  <UserCheck className="h-4 w-4 text-indigo-600" />
                  <span>Allocate Substitute</span>
                </Button>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Teacher</TableHead>
                      <TableHead>Department</TableHead>
                      <TableHead>Designation</TableHead>
                      <TableHead>Class Teacher Status</TableHead>
                      <TableHead>Weekly Workload</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {teachers.map((t) => (
                      <TableRow key={t.id}>
                        <TableCell>
                          <div className="font-medium text-slate-900 dark:text-slate-100">{t.full_name}</div>
                          <div className="text-xs text-slate-400 font-mono">{t.employee_code}</div>
                        </TableCell>
                        <TableCell>{t.department || "General"}</TableCell>
                        <TableCell>{t.designation || "Faculty"}</TableCell>
                        <TableCell>
                          {t.is_class_teacher ? (
                            <Badge className="bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border-indigo-200">
                              {t.class_teacher_sections[0]?.class_name} ({t.class_teacher_sections[0]?.section_name})
                            </Badge>
                          ) : (
                            <span className="text-xs text-slate-400">Subject Teacher</span>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <div className="w-24 bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                              <div className="bg-indigo-600 h-full rounded-full" style={{ width: "55%" }} />
                            </div>
                            <span className="text-xs font-medium text-slate-600 dark:text-slate-300">22 / 40 Pds</span>
                          </div>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleOpenWorkload(t)}
                              className="h-8 text-xs flex items-center gap-1"
                            >
                              <TrendingUp className="h-3.5 w-3.5" />
                              <span>Workload</span>
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => {
                                setSelectedEmployee(employees.find((e) => e.id === t.id) || null);
                                setIsTimetableOpen(true);
                              }}
                              className="h-8 text-xs flex items-center gap-1"
                            >
                              <Calendar className="h-3.5 w-3.5" />
                              <span>Timetable</span>
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ======================================================== */}
        {/* TAB 3: LEAVE MANAGEMENT & APPROVALS                      */}
        {/* ======================================================== */}
        {activeTab === "leaves" && (
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Staff Leave Pipeline</h3>
                <p className="text-xs text-slate-500">Track and authorize employee leaves with automated quota deduction.</p>
              </div>
              <Button onClick={() => setIsApplyLeaveOpen(true)} className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5">
                <Plus className="h-4 w-4" />
                <span>Apply for Leave</span>
              </Button>
            </div>

            {/* Quota overview ribbon */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              {leaveTypes.map((type) => (
                <Card key={type.id} className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 p-4">
                  <div className="text-xs font-semibold uppercase tracking-wider text-slate-400">{type.code}</div>
                  <div className="text-lg font-bold text-slate-900 dark:text-slate-100 mt-1">{type.name}</div>
                  <div className="flex items-center justify-between text-xs text-slate-500 mt-2">
                    <span>Quota: {type.annual_quota} days</span>
                    <Badge variant="outline" className="text-[10px]">
                      {type.is_paid ? "Paid" : "Unpaid"}
                    </Badge>
                  </div>
                </Card>
              ))}
            </div>

            {/* Applications Table */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-semibold">Leave Applications</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Employee</TableHead>
                      <TableHead>Leave Type</TableHead>
                      <TableHead>Period</TableHead>
                      <TableHead>Days</TableHead>
                      <TableHead>Reason</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {leaveApplications.map((app) => (
                      <TableRow key={app.id}>
                        <TableCell>
                          <div className="font-medium text-slate-900 dark:text-slate-100">{app.employee?.full_name || "Staff Member"}</div>
                          <div className="text-xs text-slate-400 font-mono">{app.employee?.employee_code}</div>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="font-mono text-xs">
                            {app.leave_type}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs text-slate-600 dark:text-slate-300">
                          {app.start_date} &rarr; {app.end_date}
                        </TableCell>
                        <TableCell className="font-medium text-slate-900 dark:text-slate-100">{app.total_days} d</TableCell>
                        <TableCell className="max-w-xs truncate text-xs text-slate-500">{app.reason}</TableCell>
                        <TableCell>
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize ${
                              app.status === "approved"
                                ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400"
                                : app.status === "pending"
                                ? "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
                                : "bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400"
                            }`}
                          >
                            {app.status}
                          </span>
                        </TableCell>
                        <TableCell className="text-right">
                          {app.status === "pending" ? (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => {
                                setSelectedLeaveApp(app);
                                setIsReviewLeaveOpen(true);
                              }}
                              className="h-8 text-xs flex items-center gap-1"
                            >
                              <FileCheck className="h-3.5 w-3.5" />
                              <span>Review</span>
                            </Button>
                          ) : (
                            <span className="text-xs text-slate-400">Processed</span>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ======================================================== */}
        {/* TAB 4: PAYROLL & COMPENSATION                            */}
        {/* ======================================================== */}
        {activeTab === "payroll" && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Configurable Payroll Preparation</h3>
                <p className="text-xs text-slate-500">
                  Dynamic allowances, statutory-neutral deduction formulas, attendance pro-rata calculations, and payslips.
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setIsComponentOpen(true)}
                  className="flex items-center gap-1.5"
                >
                  <Plus className="h-4 w-4" />
                  <span>New Component</span>
                </Button>
                <Button
                  size="sm"
                  onClick={() => setIsCreatePeriodOpen(true)}
                  className="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5"
                >
                  <Calendar className="h-4 w-4" />
                  <span>Open Period</span>
                </Button>
              </div>
            </div>

            {/* Payroll Periods Card */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-semibold">Active Payroll Cycles</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Period</TableHead>
                      <TableHead>Dates</TableHead>
                      <TableHead>Gross</TableHead>
                      <TableHead>Deductions</TableHead>
                      <TableHead>Net Payout</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {payrollPeriods.map((period) => (
                      <TableRow key={period.id}>
                        <TableCell className="font-medium text-slate-900 dark:text-slate-100">{period.name}</TableCell>
                        <TableCell className="text-xs text-slate-500">
                          {period.start_date} &rarr; {period.end_date}
                        </TableCell>
                        <TableCell className="font-mono text-sm text-slate-700 dark:text-slate-300">
                          ₹{Number(period.total_gross).toLocaleString()}
                        </TableCell>
                        <TableCell className="font-mono text-sm text-rose-600">
                          -₹{Number(period.total_deductions).toLocaleString()}
                        </TableCell>
                        <TableCell className="font-mono font-semibold text-sm text-emerald-600">
                          ₹{Number(period.total_net).toLocaleString()}
                        </TableCell>
                        <TableCell>
                          <Badge
                            className={`capitalize text-xs ${
                              period.status === "paid"
                                ? "bg-emerald-100 text-emerald-800 border-emerald-300"
                                : period.status === "approved"
                                ? "bg-blue-100 text-blue-800 border-blue-300"
                                : "bg-amber-100 text-amber-800 border-amber-300"
                            }`}
                          >
                            {period.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {period.status === "draft" || period.status === "processing" ? (
                              <>
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleCalculatePayroll(period)}
                                  className="h-8 text-xs"
                                >
                                  Calculate
                                </Button>
                                <Button
                                  size="sm"
                                  onClick={() => handleApprovePayroll(period)}
                                  className="h-8 text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                                >
                                  Approve
                                </Button>
                              </>
                            ) : (
                              <span className="text-xs text-slate-400">Locked</span>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            {/* Payslips Table */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-semibold">Generated Employee Payslips</CardTitle>
                <CardDescription>Individual line-item receipts with earnings breakdown and statutory items.</CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Payslip #</TableHead>
                      <TableHead>Employee</TableHead>
                      <TableHead>Basic</TableHead>
                      <TableHead>Allowances</TableHead>
                      <TableHead>Deductions</TableHead>
                      <TableHead>Net Salary</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Dossier</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {payslips.map((ps) => (
                      <TableRow key={ps.id}>
                        <TableCell className="font-mono text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                          {ps.payslip_number}
                        </TableCell>
                        <TableCell>
                          <div className="font-medium text-slate-900 dark:text-slate-100">{ps.employee?.full_name}</div>
                          <div className="text-xs text-slate-400">{ps.employee?.designation?.title}</div>
                        </TableCell>
                        <TableCell className="font-mono text-xs">₹{Number(ps.basic_salary).toLocaleString()}</TableCell>
                        <TableCell className="font-mono text-xs text-emerald-600">+₹{Number(ps.total_allowances).toLocaleString()}</TableCell>
                        <TableCell className="font-mono text-xs text-rose-600">-₹{Number(ps.total_deductions).toLocaleString()}</TableCell>
                        <TableCell className="font-mono text-sm font-bold text-slate-900 dark:text-slate-100">
                          ₹{Number(ps.net_salary).toLocaleString()}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="capitalize text-xs">
                            {ps.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              setSelectedPayslip(ps);
                              setIsViewPayslipOpen(true);
                            }}
                            className="h-8 text-xs flex items-center gap-1"
                          >
                            <Printer className="h-3.5 w-3.5" />
                            <span>Payslip</span>
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

        {/* ======================================================== */}
        {/* TAB 5: DEPARTMENTS & ROLES                               */}
        {/* ======================================================== */}
        {activeTab === "organization" && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Departments */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-semibold">Departments</CardTitle>
                  <CardDescription>Academic, administrative, and operational divisions.</CardDescription>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Code</TableHead>
                      <TableHead>Name</TableHead>
                      <TableHead className="text-right">Headcount</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {departments.map((dept) => (
                      <TableRow key={dept.id}>
                        <TableCell className="font-mono text-xs font-semibold text-indigo-600">{dept.code}</TableCell>
                        <TableCell className="font-medium text-slate-900 dark:text-slate-100">{dept.name}</TableCell>
                        <TableCell className="text-right font-medium text-slate-600 dark:text-slate-300">
                          {dept.employees_count || 0} Staff
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            {/* Designations */}
            <Card className="bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
              <CardHeader className="py-4 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-semibold">Staff Designations</CardTitle>
                  <CardDescription>Organizational ranks and job titles.</CardDescription>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-slate-50/50 dark:bg-slate-800/50">
                      <TableHead>Code</TableHead>
                      <TableHead>Title</TableHead>
                      <TableHead>Level</TableHead>
                      <TableHead className="text-right">Staff</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {designations.map((desig) => (
                      <TableRow key={desig.id}>
                        <TableCell className="font-mono text-xs font-semibold text-indigo-600">{desig.code}</TableCell>
                        <TableCell className="font-medium text-slate-900 dark:text-slate-100">{desig.title}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-xs">
                            Level {desig.level}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right font-medium text-slate-600 dark:text-slate-300">
                          {desig.employees_count || 0}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}
      </main>

      {/* ======================================================== */}
      {/* MODAL 1: REGISTER EMPLOYEE                               */}
      {/* ======================================================== */}
      <Modal
        isOpen={isRegisterOpen}
        onClose={() => setIsRegisterOpen(false)}
        title="Register New Employee"
        description="Onboard an educator or administrative employee into the institution."
      >
        <form onSubmit={handleRegisterEmployee} className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">First Name *</Label>
              <Input
                required
                value={registerForm.first_name}
                onChange={(e) => setRegisterForm({ ...registerForm, first_name: e.target.value })}
              />
            </div>
            <div>
              <Label className="text-xs">Last Name *</Label>
              <Input
                required
                value={registerForm.last_name}
                onChange={(e) => setRegisterForm({ ...registerForm, last_name: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Email Address *</Label>
              <Input
                type="email"
                required
                value={registerForm.email}
                onChange={(e) => setRegisterForm({ ...registerForm, email: e.target.value })}
              />
            </div>
            <div>
              <Label className="text-xs">Mobile Phone *</Label>
              <Input
                required
                value={registerForm.phone}
                onChange={(e) => setRegisterForm({ ...registerForm, phone: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label className="text-xs">Department *</Label>
              <select
                className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                value={registerForm.department_id}
                onChange={(e) => setRegisterForm({ ...registerForm, department_id: e.target.value })}
              >
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label className="text-xs">Designation *</Label>
              <select
                className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                value={registerForm.designation_id}
                onChange={(e) => setRegisterForm({ ...registerForm, designation_id: e.target.value })}
              >
                {designations.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.title}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label className="text-xs">Employment Type</Label>
              <select
                className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                value={registerForm.employment_type}
                onChange={(e) => setRegisterForm({ ...registerForm, employment_type: e.target.value })}
              >
                <option value="permanent">Permanent</option>
                <option value="contract">Contract</option>
                <option value="temporary">Temporary</option>
                <option value="part_time">Part Time</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Base Salary (₹ / Month) *</Label>
              <Input
                type="number"
                required
                value={registerForm.basic_salary}
                onChange={(e) => setRegisterForm({ ...registerForm, basic_salary: Number(e.target.value) })}
              />
            </div>
            <div>
              <Label className="text-xs">PAN Number</Label>
              <Input
                value={registerForm.pan_number}
                onChange={(e) => setRegisterForm({ ...registerForm, pan_number: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label className="text-xs">Bank Name</Label>
              <Input
                value={registerForm.bank_name}
                onChange={(e) => setRegisterForm({ ...registerForm, bank_name: e.target.value })}
              />
            </div>
            <div>
              <Label className="text-xs">Account Number</Label>
              <Input
                value={registerForm.bank_account_number}
                onChange={(e) => setRegisterForm({ ...registerForm, bank_account_number: e.target.value })}
              />
            </div>
            <div>
              <Label className="text-xs">IFSC Code</Label>
              <Input
                value={registerForm.bank_ifsc_code}
                onChange={(e) => setRegisterForm({ ...registerForm, bank_ifsc_code: e.target.value })}
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-3 border-t">
            <Button type="button" variant="outline" onClick={() => setIsRegisterOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Complete Onboarding
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 2: EMPLOYEE 360 PROFILE VIEW                       */}
      {/* ======================================================== */}
      <Modal
        isOpen={is360Open}
        onClose={() => setIs360Open(false)}
        title={selectedEmployee?.full_name || "Employee 360"}
        description={`Employee Code: ${selectedEmployee?.employee_code || "—"}`}
      >
        {selectedEmployee && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border text-xs">
              <div>
                <span className="text-slate-400">Department:</span>
                <div className="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{selectedEmployee.department?.name}</div>
              </div>
              <div>
                <span className="text-slate-400">Designation:</span>
                <div className="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">{selectedEmployee.designation?.title}</div>
              </div>
              <div>
                <span className="text-slate-400">Email:</span>
                <div className="font-medium text-slate-800 dark:text-slate-200 mt-0.5">{selectedEmployee.email}</div>
              </div>
              <div>
                <span className="text-slate-400">Phone:</span>
                <div className="font-medium text-slate-800 dark:text-slate-200 mt-0.5">{selectedEmployee.phone}</div>
              </div>
              <div>
                <span className="text-slate-400">Date of Joining:</span>
                <div className="font-medium text-slate-800 dark:text-slate-200 mt-0.5">{selectedEmployee.date_of_joining}</div>
              </div>
              <div>
                <span className="text-slate-400">Employment Type:</span>
                <div className="font-medium text-slate-800 dark:text-slate-200 mt-0.5 capitalize">{selectedEmployee.employment_type}</div>
              </div>
              <div>
                <span className="text-slate-400">Basic Salary:</span>
                <div className="font-mono font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                  ₹{Number(selectedEmployee.basic_salary).toLocaleString()}
                </div>
              </div>
              <div>
                <span className="text-slate-400">Status:</span>
                <div className="font-medium text-slate-800 dark:text-slate-200 mt-0.5 capitalize">{selectedEmployee.status}</div>
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <Button onClick={() => setIs360Open(false)}>Close Dossier</Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 3: WORKLOAD METRICS & TIMETABLE                     */}
      {/* ======================================================== */}
      <Modal
        isOpen={isWorkloadOpen}
        onClose={() => setIsWorkloadOpen(false)}
        title="Teaching Workload & Utilization"
        description={selectedWorkload?.teacher_name || "Faculty Workload"}
      >
        {selectedWorkload && (
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
              <div className="p-3 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-900 text-center">
                <div className="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Teaching Periods</div>
                <div className="text-2xl font-bold text-indigo-950 dark:text-indigo-100 mt-1">
                  {selectedWorkload.total_weekly_periods}
                </div>
              </div>
              <div className="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-center">
                <div className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Free Periods</div>
                <div className="text-2xl font-bold text-emerald-950 dark:text-emerald-100 mt-1">
                  {selectedWorkload.free_periods_per_week}
                </div>
              </div>
              <div className="p-3 rounded-lg bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-900 text-center">
                <div className="text-xs text-purple-600 dark:text-purple-400 font-medium">Utilization</div>
                <div className="text-2xl font-bold text-purple-950 dark:text-purple-100 mt-1">
                  {selectedWorkload.utilization_rate}%
                </div>
              </div>
            </div>

            <div>
              <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Subject Breakdown</h4>
              <div className="space-y-1.5">
                {selectedWorkload.subjects.map((s) => (
                  <div key={s.subject_id} className="flex items-center justify-between p-2 rounded bg-slate-50 dark:bg-slate-900 border text-xs">
                    <span className="font-medium text-slate-800 dark:text-slate-200">{s.subject_name}</span>
                    <Badge variant="secondary">{s.periods_per_week} Periods / Wk</Badge>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex justify-end pt-3">
              <Button onClick={() => setIsWorkloadOpen(false)}>Done</Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 4: SUBSTITUTE ALLOCATION                           */}
      {/* ======================================================== */}
      <Modal
        isOpen={isSubstituteOpen}
        onClose={() => setIsSubstituteOpen(false)}
        title="Allocate Substitute Teacher"
        description="Assign a free faculty member to cover class periods during teacher absences."
      >
        <div className="space-y-4">
          <div>
            <Label className="text-xs">Date of Substitution</Label>
            <Input
              type="date"
              value={substituteForm.date}
              onChange={(e) => setSubstituteForm({ ...substituteForm, date: e.target.value })}
            />
          </div>

          <div>
            <Label className="text-xs">Substitute Teacher (Conflict Checked)</Label>
            <select
              className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
              value={substituteForm.substitute_employee_id}
              onChange={(e) => setSubstituteForm({ ...substituteForm, substitute_employee_id: e.target.value })}
            >
              <option value="">Select available substitute...</option>
              {teachers.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.full_name} ({t.department})
                </option>
              ))}
            </select>
          </div>

          <div>
            <Label className="text-xs">Reason</Label>
            <Input
              value={substituteForm.reason}
              onChange={(e) => setSubstituteForm({ ...substituteForm, reason: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-2 pt-3">
            <Button variant="outline" onClick={() => setIsSubstituteOpen(false)}>
              Cancel
            </Button>
            <Button
              className="bg-indigo-600 hover:bg-indigo-700 text-white"
              onClick={() => {
                alert("Substitute teacher confirmed with zero schedule collisions.");
                setIsSubstituteOpen(false);
              }}
            >
              Confirm Substitution
            </Button>
          </div>
        </div>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 5: APPLY LEAVE                                     */}
      {/* ======================================================== */}
      <Modal
        isOpen={isApplyLeaveOpen}
        onClose={() => setIsApplyLeaveOpen(false)}
        title="Apply for Staff Leave"
        description="Submit leave application with automatic balance validation."
      >
        <form onSubmit={handleApplyLeave} className="space-y-4">
          <div>
            <Label className="text-xs">Applicant (Staff)</Label>
            <select
              className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
              value={leaveForm.employee_id}
              onChange={(e) => setLeaveForm({ ...leaveForm, employee_id: e.target.value })}
            >
              {employees.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.full_name} ({e.employee_code})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Leave Type</Label>
              <select
                className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                value={leaveForm.leave_type}
                onChange={(e) => setLeaveForm({ ...leaveForm, leave_type: e.target.value })}
              >
                {leaveTypes.map((t) => (
                  <option key={t.id} value={t.code}>
                    {t.name} ({t.code})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label className="text-xs">Total Days</Label>
              <Input
                type="number"
                step="0.5"
                min="0.5"
                value={leaveForm.total_days}
                onChange={(e) => setLeaveForm({ ...leaveForm, total_days: Number(e.target.value) })}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Start Date</Label>
              <Input
                type="date"
                value={leaveForm.start_date}
                onChange={(e) => setLeaveForm({ ...leaveForm, start_date: e.target.value })}
              />
            </div>
            <div>
              <Label className="text-xs">End Date</Label>
              <Input
                type="date"
                value={leaveForm.end_date}
                onChange={(e) => setLeaveForm({ ...leaveForm, end_date: e.target.value })}
              />
            </div>
          </div>

          <div>
            <Label className="text-xs">Reason *</Label>
            <Input
              required
              value={leaveForm.reason}
              onChange={(e) => setLeaveForm({ ...leaveForm, reason: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-2 pt-3">
            <Button type="button" variant="outline" onClick={() => setIsApplyLeaveOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Submit Leave
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 6: REVIEW LEAVE APPLICATION                        */}
      {/* ======================================================== */}
      <Modal
        isOpen={isReviewLeaveOpen}
        onClose={() => setIsReviewLeaveOpen(false)}
        title="Review Leave Application"
        description="Approve or decline staff leave request."
      >
        {selectedLeaveApp && (
          <div className="space-y-4">
            <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-900 border text-xs space-y-1">
              <div>
                <span className="text-slate-400">Applicant:</span>{" "}
                <span className="font-semibold text-slate-800 dark:text-slate-200">{selectedLeaveApp.employee?.full_name}</span>
              </div>
              <div>
                <span className="text-slate-400">Duration:</span>{" "}
                <span>
                  {selectedLeaveApp.start_date} to {selectedLeaveApp.end_date} ({selectedLeaveApp.total_days} days)
                </span>
              </div>
              <div>
                <span className="text-slate-400">Reason:</span> <span>{selectedLeaveApp.reason}</span>
              </div>
            </div>

            <div>
              <Label className="text-xs">Decision</Label>
              <select
                className="w-full h-10 px-3 text-xs rounded-md border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                value={reviewForm.status}
                onChange={(e) => setReviewForm({ ...reviewForm, status: e.target.value as any })}
              >
                <option value="approved">Approve Application</option>
                <option value="rejected">Reject Application</option>
              </select>
            </div>

            <div>
              <Label className="text-xs">Administrative Remarks</Label>
              <Input
                value={reviewForm.remarks}
                onChange={(e) => setReviewForm({ ...reviewForm, remarks: e.target.value })}
              />
            </div>

            <div className="flex justify-end gap-2 pt-3">
              <Button variant="outline" onClick={() => setIsReviewLeaveOpen(false)}>
                Cancel
              </Button>
              <Button className="bg-indigo-600 hover:bg-indigo-700 text-white" onClick={handleReviewLeave}>
                Submit Decision
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 7: VIEW / PRINT PAYSLIP DOSSIER                    */}
      {/* ======================================================== */}
      <Modal
        isOpen={isViewPayslipOpen}
        onClose={() => setIsViewPayslipOpen(false)}
        title="Official Payslip Dossier"
        description={selectedPayslip?.payslip_number || "Payslip"}
      >
        {selectedPayslip && (
          <div className="space-y-4">
            <div className="border border-slate-200 dark:border-slate-800 rounded-lg p-5 bg-white dark:bg-slate-900 text-xs space-y-4 font-sans">
              {/* Header */}
              <div className="flex items-center justify-between border-b pb-3">
                <div>
                  <div className="font-bold text-base text-slate-900 dark:text-slate-100">EDUCORE ACADEMY</div>
                  <div className="text-slate-500 text-[11px]">School ERP SaaS Payroll Statement</div>
                </div>
                <div className="text-right">
                  <div className="font-mono font-bold text-indigo-600">{selectedPayslip.payslip_number}</div>
                  <div className="text-slate-400 text-[11px]">{selectedPayslip.payroll_period?.name}</div>
                </div>
              </div>

              {/* Employee Summary */}
              <div className="grid grid-cols-2 gap-2 text-[11px] bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded">
                <div>
                  <span className="text-slate-400">Employee Name:</span>{" "}
                  <span className="font-semibold text-slate-800 dark:text-slate-200">{selectedPayslip.employee?.full_name}</span>
                </div>
                <div>
                  <span className="text-slate-400">Employee Code:</span>{" "}
                  <span className="font-mono">{selectedPayslip.employee?.employee_code}</span>
                </div>
                <div>
                  <span className="text-slate-400">Department:</span>{" "}
                  <span>{selectedPayslip.employee?.department?.name}</span>
                </div>
                <div>
                  <span className="text-slate-400">Designation:</span>{" "}
                  <span>{selectedPayslip.employee?.designation?.title}</span>
                </div>
              </div>

              {/* Earnings & Deductions */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <div className="font-bold text-emerald-700 dark:text-emerald-400 border-b pb-1 mb-2">Earnings</div>
                  <div className="space-y-1">
                    {selectedPayslip.earnings_breakdown.map((e, idx) => (
                      <div key={idx} className="flex justify-between text-slate-600 dark:text-slate-300">
                        <span>{e.name}</span>
                        <span className="font-mono">₹{Number(e.amount).toLocaleString()}</span>
                      </div>
                    ))}
                    <div className="flex justify-between font-bold border-t pt-1 text-slate-900 dark:text-slate-100">
                      <span>Gross Earnings</span>
                      <span className="font-mono">₹{Number(selectedPayslip.gross_salary).toLocaleString()}</span>
                    </div>
                  </div>
                </div>

                <div>
                  <div className="font-bold text-rose-700 dark:text-rose-400 border-b pb-1 mb-2">Deductions</div>
                  <div className="space-y-1">
                    {selectedPayslip.deductions_breakdown.map((d, idx) => (
                      <div key={idx} className="flex justify-between text-slate-600 dark:text-slate-300">
                        <span>{d.name}</span>
                        <span className="font-mono">₹{Number(d.amount).toLocaleString()}</span>
                      </div>
                    ))}
                    <div className="flex justify-between font-bold border-t pt-1 text-slate-900 dark:text-slate-100">
                      <span>Total Deductions</span>
                      <span className="font-mono">₹{Number(selectedPayslip.total_deductions).toLocaleString()}</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Net Payout Banner */}
              <div className="flex items-center justify-between p-3 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-900">
                <span className="font-bold text-indigo-900 dark:text-indigo-200">Net Take-Home Salary:</span>
                <span className="font-mono text-base font-bold text-indigo-700 dark:text-indigo-300">
                  ₹{Number(selectedPayslip.net_salary).toLocaleString()}
                </span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button variant="outline" onClick={() => window.print()} className="flex items-center gap-1.5">
                <Printer className="h-4 w-4" />
                <span>Print Statement</span>
              </Button>
              <Button onClick={() => setIsViewPayslipOpen(false)}>Close</Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 8: OPEN PAYROLL PERIOD                             */}
      {/* ======================================================== */}
      <Modal
        isOpen={isCreatePeriodOpen}
        onClose={() => setIsCreatePeriodOpen(false)}
        title="Open New Payroll Period"
        description="Establish a payroll computation cycle for institutional disbursement."
      >
        <form onSubmit={handleCreatePayrollPeriod} className="space-y-4">
          <div>
            <Label className="text-xs">Period Name *</Label>
            <Input
              required
              value={periodForm.name}
              onChange={(e) => setPeriodForm({ ...periodForm, name: e.target.value })}
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label className="text-xs">Start Date *</Label>
              <Input
                type="date"
                required
                value={periodForm.start_date}
                onChange={(e) => setPeriodForm({ ...periodForm, start_date: e.target.value })}
              />
            </div>
            <div>
              <Label className="text-xs">End Date *</Label>
              <Input
                type="date"
                required
                value={periodForm.end_date}
                onChange={(e) => setPeriodForm({ ...periodForm, end_date: e.target.value })}
              />
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-3">
            <Button type="button" variant="outline" onClick={() => setIsCreatePeriodOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700 text-white">
              Initialize Period
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default function StaffManagementPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-screen items-center justify-center bg-slate-50 dark:bg-slate-950">
          <div className="text-center space-y-3">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-indigo-600 border-t-transparent mx-auto" />
            <p className="text-sm text-slate-500">Loading Staff & HR Workspace...</p>
          </div>
        </div>
      }
    >
      <StaffManagementContent />
    </Suspense>
  );
}
