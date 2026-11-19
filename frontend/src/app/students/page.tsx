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
  Users,
  UserPlus,
  FileText,
  Upload,
  Download,
  Search,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Clock,
  QrCode,
  ArrowRight,
  TrendingUp,
  HeartPulse,
  FolderOpen,
  Eye,
  RefreshCw,
  Printer,
  ChevronRight,
  ShieldCheck,
  Building,
  UserCheck,
  History,
  FileCheck2,
  Layers,
  Sparkles,
  Phone,
  Mail,
  Home,
  Check,
} from "lucide-react";

// ==========================================
// Types
// ==========================================

interface GuardianItem {
  id: string;
  first_name: string;
  last_name: string;
  full_name?: string;
  relation_type: string;
  phone: string;
  email?: string;
  occupation?: string;
  pivot?: {
    is_primary: boolean;
    has_pickup_rights: boolean;
    receives_invoices: boolean;
    emergency_contact_priority: number;
  };
  students_count?: number;
}

interface EnrollmentItem {
  id: string;
  academic_session_id: string;
  class_id: string;
  section_id: string;
  roll_number?: string;
  enrollment_date: string;
  status: string;
  school_class?: { id: string; name: string; code: string };
  section?: { id: string; name: string; room_number?: string };
  academic_session?: { id: string; name: string; is_current: boolean };
}

interface StudentDocumentItem {
  id: string;
  document_type: string;
  file_name: string;
  file_path: string;
  file_size: number;
  verification_status: "pending" | "verified" | "rejected";
}

interface StudentMedicalItem {
  id: string;
  blood_group?: string;
  allergies?: string[];
  chronic_conditions?: string;
  medications?: string;
  emergency_notes?: string;
  doctor_name?: string;
  doctor_phone?: string;
}

interface StudentItem {
  id: string;
  admission_number: string;
  admission_date: string;
  first_name: string;
  middle_name?: string;
  last_name: string;
  full_name: string;
  gender: string;
  date_of_birth: string;
  age?: number;
  blood_group?: string;
  nationality?: string;
  religion?: string;
  caste_category?: string;
  national_id?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
  photo_url?: string;
  current_address?: string;
  permanent_address?: string;
  status: "active" | "inactive" | "transferred" | "withdrawn" | "graduated";
  guardians?: GuardianItem[];
  current_enrollment?: EnrollmentItem;
  enrollments?: EnrollmentItem[];
  documents?: StudentDocumentItem[];
  medical_record?: StudentMedicalItem;
}

interface AdmissionItem {
  id: string;
  application_number: string;
  applicant_first_name: string;
  applicant_last_name: string;
  applicant_full_name?: string;
  date_of_application: string;
  status: "applied" | "under_review" | "waitlisted" | "approved" | "rejected" | "enrolled";
  application_fee_paid: boolean;
  applied_class?: { id: string; name: string; code: string };
  academic_session?: { id: string; name: string };
  student?: { id: string; admission_number: string };
  inquiry_details?: {
    date_of_birth?: string;
    gender?: string;
    blood_group?: string;
    parent_name?: string;
    parent_phone?: string;
    parent_email?: string;
    parent_relation?: string;
    previous_school?: string;
    address?: string;
    waitlist_number?: number;
    rejection_reason?: string;
    status_history?: Array<{
      from?: string;
      to: string;
      timestamp: string;
      remarks?: string;
      waitlist_number?: number;
      rejection_reason?: string;
    }>;
  };
}

interface ClassOption {
  id: string;
  name: string;
  code: string;
  sections?: Array<{ id: string; name: string; room_number?: string }>;
}

interface AcademicSessionOption {
  id: string;
  name: string;
  is_current: boolean;
}

// ==========================================
// Inner Component with SearchParams
// ==========================================

function StudentsContent() {
  const searchParams = useSearchParams();
  const initialTab = searchParams.get("tab") || "directory";

  const [activeTab, setActiveTab] = useState<string>(initialTab);
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Data states
  const [students, setStudents] = useState<StudentItem[]>([]);
  const [admissions, setAdmissions] = useState<AdmissionItem[]>([]);
  const [guardians, setGuardians] = useState<GuardianItem[]>([]);
  const [classes, setClasses] = useState<ClassOption[]>([]);
  const [sessions, setSessions] = useState<AcademicSessionOption[]>([]);
  const [pipelineCounts, setPipelineCounts] = useState<Record<string, number>>({});

  // Filter states
  const [searchQuery, setSearchQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [classFilter, setClassFilter] = useState("");
  const [admissionStatusFilter, setAdmissionStatusFilter] = useState("");

  // Modals state
  const [showNewAdmissionModal, setShowNewAdmissionModal] = useState(false);
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [showNewStudentModal, setShowNewStudentModal] = useState(false);
  const [showProfile360Modal, setShowProfile360Modal] = useState(false);
  const [showIdCardModal, setShowIdCardModal] = useState(false);
  const [showPromoteModal, setShowPromoteModal] = useState(false);
  const [showTransferModal, setShowTransferModal] = useState(false);
  const [showWithdrawModal, setShowWithdrawModal] = useState(false);
  const [showImportModal, setShowImportModal] = useState(false);

  // Selected Entities
  const [selectedAdmission, setSelectedAdmission] = useState<AdmissionItem | null>(null);
  const [selectedStudent, setSelectedStudent] = useState<StudentItem | null>(null);
  const [idCardData, setIdCardData] = useState<any>(null);

  // Forms states
  const [admissionForm, setAdmissionForm] = useState({
    applicant_first_name: "",
    applicant_last_name: "",
    applied_class_id: "",
    academic_session_id: "",
    date_of_birth: "2018-05-10",
    gender: "male",
    parent_name: "",
    parent_phone: "",
    parent_email: "",
    parent_relation: "father",
    previous_school: "",
    address: "",
  });

  const [reviewForm, setReviewForm] = useState({
    status: "under_review",
    remarks: "",
    waitlist_number: 1,
    rejection_reason: "",
  });

  const [confirmForm, setConfirmForm] = useState({
    section_id: "",
    roll_number: "",
    blood_group: "O+",
  });

  const [studentForm, setStudentForm] = useState({
    first_name: "",
    middle_name: "",
    last_name: "",
    date_of_birth: "2015-06-15",
    gender: "male",
    blood_group: "A+",
    caste_category: "General",
    class_id: "",
    section_id: "",
    roll_number: "",
    guardian_name: "",
    guardian_phone: "",
    guardian_relation: "father",
    current_address: "",
    allergies: "",
    chronic_conditions: "",
  });

  const [promoteForm, setPromoteForm] = useState({
    to_session_id: "",
    to_class_id: "",
    to_section_id: "",
    roll_number: "",
    remarks: "",
  });

  const [transferForm, setTransferForm] = useState({
    destination_school: "",
    reason_for_leaving: "Relocation / Course Completed",
    application_date: new Date().toISOString().split("T")[0],
  });

  const [withdrawForm, setWithdrawForm] = useState({
    reason: "",
    exit_date: new Date().toISOString().split("T")[0],
  });

  // Import states
  const [importRowsJson, setImportRowsJson] = useState("");
  const [importPreview, setImportPreview] = useState<any>(null);
  const [importStep, setImportStep] = useState<1 | 2 | 3>(1);

  // ==========================================
  // Fetchers
  // ==========================================

  const loadClassesAndSessions = useCallback(async () => {
    try {
      const [classRes, sessRes] = await Promise.all([
        apiClient.get("/academic/classes").catch(() => ({ data: { data: [] } })),
        apiClient.get("/academic-sessions").catch(() => ({ data: { data: [] } })),
      ]);
      setClasses(classRes.data?.data || []);
      setSessions(sessRes.data?.data || []);
    } catch (err) {
      console.error("Failed to load metadata", err);
    }
  }, []);

  const loadStudents = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (searchQuery) params.append("search", searchQuery);
      if (statusFilter) params.append("status", statusFilter);
      if (classFilter) params.append("class_id", classFilter);

      const res = await apiClient.get(`/students?${params.toString()}`);
      setStudents(res.data?.data || []);
    } catch (err: any) {
      setErrorMsg(err.response?.data?.message || "Failed to load student directory");
    } finally {
      setLoading(false);
    }
  }, [searchQuery, statusFilter, classFilter]);

  const loadAdmissions = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (admissionStatusFilter) params.append("status", admissionStatusFilter);
      if (searchQuery) params.append("search", searchQuery);

      const res = await apiClient.get(`/admissions?${params.toString()}`);
      setAdmissions(res.data?.data || []);
      setPipelineCounts(res.data?.meta?.pipeline_counts || {});
    } catch (err: any) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, [admissionStatusFilter, searchQuery]);

  const loadGuardians = useCallback(async () => {
    try {
      const res = await apiClient.get("/guardians");
      setGuardians(res.data?.data || []);
    } catch (err) {
      console.error(err);
    }
  }, []);

  useEffect(() => {
    loadClassesAndSessions();
  }, [loadClassesAndSessions]);

  useEffect(() => {
    if (activeTab === "directory") {
      loadStudents();
    } else if (activeTab === "admissions") {
      loadAdmissions();
    } else if (activeTab === "guardians") {
      loadGuardians();
    }
  }, [activeTab, loadStudents, loadAdmissions, loadGuardians]);

  // Clear messages
  const notify = (msg: string, isError = false) => {
    if (isError) {
      setErrorMsg(msg);
      setTimeout(() => setErrorMsg(null), 5000);
    } else {
      setSuccessMsg(msg);
      setTimeout(() => setSuccessMsg(null), 4000);
    }
  };

  // ==========================================
  // Admission Actions
  // ==========================================

  const handleCreateAdmission = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post("/admissions", admissionForm);
      notify("Application submitted successfully!");
      setShowNewAdmissionModal(false);
      loadAdmissions();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to submit application", true);
    }
  };

  const handleReviewStatus = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAdmission) return;
    try {
      await apiClient.patch(`/admissions/${selectedAdmission.id}/status`, reviewForm);
      notify(`Application status updated to ${reviewForm.status}`);
      setShowReviewModal(false);
      loadAdmissions();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to update review", true);
    }
  };

  const handleConfirmAdmission = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAdmission) return;
    try {
      await apiClient.post(`/admissions/${selectedAdmission.id}/confirm`, confirmForm);
      notify("Admission confirmed! Student enrolled into grade and section.");
      setShowConfirmModal(false);
      loadAdmissions();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to confirm admission", true);
    }
  };

  // ==========================================
  // Student Actions
  // ==========================================

  const handleCreateStudent = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const payload = {
        ...studentForm,
        allergies: studentForm.allergies ? studentForm.allergies.split(",").map((s) => s.trim()) : [],
      };
      await apiClient.post("/students", payload);
      notify("Student enrolled directly successfully!");
      setShowNewStudentModal(false);
      loadStudents();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to create student", true);
    }
  };

  const openStudent360 = async (student: StudentItem) => {
    try {
      const res = await apiClient.get(`/students/${student.id}`);
      setSelectedStudent(res.data?.data || student);
      setShowProfile360Modal(true);
    } catch (err) {
      setSelectedStudent(student);
      setShowProfile360Modal(true);
    }
  };

  const openIdCard = async (student: StudentItem) => {
    try {
      const res = await apiClient.get(`/students/${student.id}/id-card`);
      setIdCardData(res.data?.data);
      setSelectedStudent(student);
      setShowIdCardModal(true);
    } catch (err: any) {
      notify("Failed to generate digital ID card", true);
    }
  };

  const handlePromoteStudent = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedStudent) return;
    try {
      await apiClient.post(`/students/${selectedStudent.id}/promote`, promoteForm);
      notify(`Student ${selectedStudent.full_name} promoted successfully! Historical records preserved.`);
      setShowPromoteModal(false);
      loadStudents();
    } catch (err: any) {
      notify(err.response?.data?.message || "Promotion failed", true);
    }
  };

  const handleIssueTransfer = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedStudent) return;
    try {
      const res = await apiClient.post(`/students/${selectedStudent.id}/transfer`, transferForm);
      notify(`Transfer Certificate ${res.data?.data?.tc_number} issued successfully!`);
      setShowTransferModal(false);
      loadStudents();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to issue TC", true);
    }
  };

  const handleWithdrawStudent = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedStudent) return;
    try {
      await apiClient.post(`/students/${selectedStudent.id}/withdraw`, withdrawForm);
      notify("Student withdrawal recorded.");
      setShowWithdrawModal(false);
      loadStudents();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to record withdrawal", true);
    }
  };

  const handleExportRoster = async () => {
    try {
      const res = await apiClient.get("/students/export");
      const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(res.data?.data, null, 2));
      const downloadAnchor = document.createElement("a");
      downloadAnchor.setAttribute("href", dataStr);
      downloadAnchor.setAttribute("download", `student_roster_${new Date().toISOString().split("T")[0]}.json`);
      document.body.appendChild(downloadAnchor);
      downloadAnchor.click();
      downloadAnchor.remove();
      notify("Roster exported successfully!");
    } catch (err) {
      notify("Export failed", true);
    }
  };

  // ==========================================
  // Bulk Import
  // ==========================================

  const handleDownloadTemplate = async () => {
    try {
      const res = await apiClient.get("/students/import/template");
      const sample = res.data?.data?.sample_rows || [];
      const headers = Object.keys(sample[0] || {}).join(",");
      const row1 = Object.values(sample[0] || {}).map((v) => `"${v}"`).join(",");
      const csvContent = "data:text/csv;charset=utf-8," + encodeURIComponent(`${headers}\n${row1}`);
      const downloadAnchor = document.createElement("a");
      downloadAnchor.setAttribute("href", csvContent);
      downloadAnchor.setAttribute("download", "student_bulk_import_template.csv");
      document.body.appendChild(downloadAnchor);
      downloadAnchor.click();
      downloadAnchor.remove();
      notify("Import template downloaded!");
    } catch (err) {
      notify("Failed to download template", true);
    }
  };

  const handlePreviewImport = async () => {
    try {
      const rows = JSON.parse(importRowsJson);
      const res = await apiClient.post("/students/import/preview", { rows });
      setImportPreview(res.data?.data);
      setImportStep(2);
    } catch (err: any) {
      notify(err.response?.data?.message || "Invalid JSON array format", true);
    }
  };

  const handleExecuteImport = async () => {
    try {
      const rows = JSON.parse(importRowsJson);
      const res = await apiClient.post("/students/import", { rows });
      notify(`Import processed: ${res.data?.data?.processed_rows} students created.`);
      setShowImportModal(false);
      setImportStep(1);
      setImportRowsJson("");
      setImportPreview(null);
      loadStudents();
    } catch (err: any) {
      notify(err.response?.data?.message || "Import failed", true);
    }
  };

  return (
    <div className="flex h-screen bg-background">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Header */}
        <header className="border-b bg-card px-8 py-5 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-3">
              <Users className="w-7 h-7 text-primary" />
              Student 360 & Admissions Management
            </h1>
            <p className="text-sm text-muted-foreground">
              Complete student lifecycle: inquiry, application review, multi-guardian linking, academic history preservation, and digital ID credentials.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Button variant="outline" size="sm" onClick={handleExportRoster} className="flex items-center gap-2">
              <Download className="w-4 h-4" /> Export Roster
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setImportStep(1);
                setShowImportModal(true);
              }}
              className="flex items-center gap-2"
            >
              <Upload className="w-4 h-4" /> Bulk Import
            </Button>
            <Button
              size="sm"
              onClick={() => setShowNewStudentModal(true)}
              className="flex items-center gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
            >
              <UserPlus className="w-4 h-4" /> New Student
            </Button>
          </div>
        </header>

        {/* Global Notifications */}
        {errorMsg && (
          <div className="bg-destructive/15 text-destructive border border-destructive/30 px-6 py-3 flex items-center gap-3 text-sm animate-in fade-in">
            <AlertCircle className="w-5 h-5 flex-shrink-0" />
            <span>{errorMsg}</span>
          </div>
        )}
        {successMsg && (
          <div className="bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 px-6 py-3 flex items-center gap-3 text-sm animate-in fade-in">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Tab Navigation */}
        <div className="border-b bg-card/50 px-8 flex gap-6 text-sm font-medium">
          <button
            onClick={() => setActiveTab("directory")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "directory"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <Users className="w-4 h-4" /> Student Directory
          </button>
          <button
            onClick={() => setActiveTab("admissions")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "admissions"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <FileText className="w-4 h-4" /> Admissions Pipeline
            {pipelineCounts.total ? (
              <Badge variant="secondary" className="ml-1 px-1.5 py-0 text-xs">
                {pipelineCounts.total}
              </Badge>
            ) : null}
          </button>
          <button
            onClick={() => setActiveTab("guardians")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "guardians"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <Home className="w-4 h-4" /> Guardians & Siblings
          </button>
          <button
            onClick={() => setActiveTab("import_history")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "import_history"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <History className="w-4 h-4" /> Import System
          </button>
        </div>

        {/* Content Area */}
        <div className="flex-1 overflow-y-auto p-8">
          {/* ======================================================== */}
          {/* TAB 1: STUDENT DIRECTORY                                 */}
          {/* ======================================================== */}
          {activeTab === "directory" && (
            <div className="space-y-6">
              {/* Filter Bar */}
              <div className="flex flex-wrap items-center justify-between gap-4 bg-card p-4 rounded-xl border">
                <div className="flex flex-wrap items-center gap-3 flex-1">
                  <div className="relative min-w-[280px]">
                    <Search className="w-4 h-4 absolute left-3 top-3 text-muted-foreground" />
                    <Input
                      placeholder="Search name, admission #, Aadhaar..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="pl-9 h-10"
                    />
                  </div>
                  <select
                    value={classFilter}
                    onChange={(e) => setClassFilter(e.target.value)}
                    className="h-10 px-3 py-2 text-sm rounded-md border bg-background"
                  >
                    <option value="">All Grades / Classes</option>
                    {classes.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name} ({c.code})
                      </option>
                    ))}
                  </select>
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                    className="h-10 px-3 py-2 text-sm rounded-md border bg-background"
                  >
                    <option value="">All Enrollment Statuses</option>
                    <option value="active">Active</option>
                    <option value="withdrawn">Withdrawn</option>
                    <option value="transferred">Transferred</option>
                    <option value="graduated">Graduated</option>
                  </select>
                </div>
                <Button variant="ghost" size="sm" onClick={loadStudents} className="flex items-center gap-2">
                  <RefreshCw className="w-4 h-4" /> Refresh
                </Button>
              </div>

              {/* Students Table */}
              <Card>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-[120px]">Adm #</TableHead>
                        <TableHead>Student Name</TableHead>
                        <TableHead>Class / Section</TableHead>
                        <TableHead>Roll #</TableHead>
                        <TableHead>Gender & Age</TableHead>
                        <TableHead>Primary Guardian</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {loading ? (
                        <TableRow>
                          <TableCell colSpan={8} className="text-center py-12 text-muted-foreground">
                            Loading student directory...
                          </TableCell>
                        </TableRow>
                      ) : students.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={8} className="text-center py-12 text-muted-foreground">
                            No student records found matching filters.
                          </TableCell>
                        </TableRow>
                      ) : (
                        students.map((student) => {
                          const enrollment = student.current_enrollment;
                          const primaryG = student.guardians?.find((g) => g.pivot?.is_primary) || student.guardians?.[0];

                          return (
                            <TableRow key={student.id} className="hover:bg-muted/50">
                              <TableCell className="font-mono font-medium text-xs">
                                {student.admission_number}
                              </TableCell>
                              <TableCell>
                                <div className="font-semibold text-foreground flex items-center gap-2">
                                  {student.full_name}
                                  {student.blood_group && (
                                    <Badge variant="outline" className="text-[10px] px-1 py-0">
                                      {student.blood_group}
                                    </Badge>
                                  )}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                  Adm Date: {student.admission_date}
                                </div>
                              </TableCell>
                              <TableCell>
                                <div className="font-medium text-sm">
                                  {enrollment?.school_class?.name || "Unassigned"}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                  Sec {enrollment?.section?.name || "-"}
                                </div>
                              </TableCell>
                              <TableCell className="font-mono text-sm">
                                {enrollment?.roll_number || "—"}
                              </TableCell>
                              <TableCell className="text-sm capitalize">
                                {student.gender} {student.age ? `• ${student.age} yrs` : ""}
                              </TableCell>
                              <TableCell>
                                <div className="text-sm font-medium">
                                  {primaryG?.full_name || primaryG?.first_name ? `${primaryG.first_name} ${primaryG.last_name}` : "—"}
                                </div>
                                <div className="text-xs text-muted-foreground flex items-center gap-1">
                                  <Phone className="w-3 h-3" /> {student.emergency_contact_phone || primaryG?.phone || "—"}
                                </div>
                              </TableCell>
                              <TableCell>
                                <Badge
                                  className={
                                    student.status === "active"
                                      ? "bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
                                      : student.status === "withdrawn"
                                      ? "bg-amber-500/15 text-amber-600"
                                      : "bg-muted text-muted-foreground"
                                  }
                                >
                                  {student.status}
                                </Badge>
                              </TableCell>
                              <TableCell className="text-right">
                                <div className="flex items-center justify-end gap-1.5">
                                  <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openStudent360(student)}
                                    title="View 360 Profile"
                                    className="h-8 px-2"
                                  >
                                    <Eye className="w-3.5 h-3.5" />
                                  </Button>
                                  <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openIdCard(student)}
                                    title="Student ID & QR"
                                    className="h-8 px-2"
                                  >
                                    <QrCode className="w-3.5 h-3.5" />
                                  </Button>
                                  <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                      setSelectedStudent(student);
                                      setShowPromoteModal(true);
                                    }}
                                    title="Promote"
                                    className="h-8 px-2"
                                  >
                                    <TrendingUp className="w-3.5 h-3.5 text-blue-500" />
                                  </Button>
                                </div>
                              </TableCell>
                            </TableRow>
                          );
                        })
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 2: ADMISSIONS CRM PIPELINE                           */}
          {/* ======================================================== */}
          {activeTab === "admissions" && (
            <div className="space-y-6">
              {/* Metric Cards Banner */}
              <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <Card className="bg-card">
                  <CardContent className="p-4 flex flex-col justify-between">
                    <span className="text-xs text-muted-foreground font-medium uppercase tracking-wider">Total Inquiries</span>
                    <span className="text-2xl font-bold text-foreground mt-1">{pipelineCounts.total || 0}</span>
                  </CardContent>
                </Card>
                <Card className="bg-blue-500/5 border-blue-500/20">
                  <CardContent className="p-4 flex flex-col justify-between">
                    <span className="text-xs text-blue-600 font-medium uppercase tracking-wider">Applied</span>
                    <span className="text-2xl font-bold text-blue-600 mt-1">{pipelineCounts.applied || 0}</span>
                  </CardContent>
                </Card>
                <Card className="bg-purple-500/5 border-purple-500/20">
                  <CardContent className="p-4 flex flex-col justify-between">
                    <span className="text-xs text-purple-600 font-medium uppercase tracking-wider">Under Review</span>
                    <span className="text-2xl font-bold text-purple-600 mt-1">{pipelineCounts.under_review || 0}</span>
                  </CardContent>
                </Card>
                <Card className="bg-amber-500/5 border-amber-500/20">
                  <CardContent className="p-4 flex flex-col justify-between">
                    <span className="text-xs text-amber-600 font-medium uppercase tracking-wider">Waitlisted</span>
                    <span className="text-2xl font-bold text-amber-600 mt-1">{pipelineCounts.waitlisted || 0}</span>
                  </CardContent>
                </Card>
                <Card className="bg-emerald-500/5 border-emerald-500/20">
                  <CardContent className="p-4 flex flex-col justify-between">
                    <span className="text-xs text-emerald-600 font-medium uppercase tracking-wider">Approved</span>
                    <span className="text-2xl font-bold text-emerald-600 mt-1">{pipelineCounts.approved || 0}</span>
                  </CardContent>
                </Card>
                <Card className="bg-teal-500/5 border-teal-500/20">
                  <CardContent className="p-4 flex flex-col justify-between">
                    <span className="text-xs text-teal-600 font-medium uppercase tracking-wider">Enrolled</span>
                    <span className="text-2xl font-bold text-teal-600 mt-1">{pipelineCounts.enrolled || 0}</span>
                  </CardContent>
                </Card>
              </div>

              {/* Action and Filter Controls */}
              <div className="flex flex-wrap items-center justify-between gap-4 bg-card p-4 rounded-xl border">
                <div className="flex flex-wrap items-center gap-3">
                  <select
                    value={admissionStatusFilter}
                    onChange={(e) => setAdmissionStatusFilter(e.target.value)}
                    className="h-10 px-3 py-2 text-sm rounded-md border bg-background"
                  >
                    <option value="">All Pipeline Stages</option>
                    <option value="applied">Applied</option>
                    <option value="under_review">Under Review</option>
                    <option value="waitlisted">Waitlisted</option>
                    <option value="approved">Approved</option>
                    <option value="enrolled">Enrolled</option>
                    <option value="rejected">Rejected</option>
                  </select>
                </div>
                <Button
                  onClick={() => setShowNewAdmissionModal(true)}
                  className="flex items-center gap-2 bg-primary text-primary-foreground"
                >
                  <FileText className="w-4 h-4" /> New Admission Application
                </Button>
              </div>

              {/* Admissions Table */}
              <Card>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>App #</TableHead>
                        <TableHead>Applicant</TableHead>
                        <TableHead>Target Grade</TableHead>
                        <TableHead>Parent Contact</TableHead>
                        <TableHead>Date Applied</TableHead>
                        <TableHead>Pipeline Stage</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {admissions.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={7} className="text-center py-12 text-muted-foreground">
                            No admission applications found.
                          </TableCell>
                        </TableRow>
                      ) : (
                        admissions.map((adm) => {
                          const inquiry = adm.inquiry_details || {};
                          return (
                            <TableRow key={adm.id} className="hover:bg-muted/50">
                              <TableCell className="font-mono text-xs font-semibold">
                                {adm.application_number}
                              </TableCell>
                              <TableCell>
                                <div className="font-semibold text-foreground">
                                  {adm.applicant_first_name} {adm.applicant_last_name}
                                </div>
                                <div className="text-xs text-muted-foreground capitalize">
                                  {inquiry.gender || "Gender N/A"} • DOB: {inquiry.date_of_birth || "N/A"}
                                </div>
                              </TableCell>
                              <TableCell className="font-medium text-sm">
                                {adm.applied_class?.name || "General"}
                              </TableCell>
                              <TableCell>
                                <div className="text-sm font-medium">{inquiry.parent_name || "Parent"}</div>
                                <div className="text-xs text-muted-foreground">{inquiry.parent_phone || "—"}</div>
                              </TableCell>
                              <TableCell className="text-sm text-muted-foreground">
                                {adm.date_of_application}
                              </TableCell>
                              <TableCell>
                                <Badge
                                  className={
                                    adm.status === "enrolled"
                                      ? "bg-teal-500/15 text-teal-600"
                                      : adm.status === "approved"
                                      ? "bg-emerald-500/15 text-emerald-600"
                                      : adm.status === "waitlisted"
                                      ? "bg-amber-500/15 text-amber-600"
                                      : adm.status === "under_review"
                                      ? "bg-purple-500/15 text-purple-600"
                                      : adm.status === "rejected"
                                      ? "bg-destructive/15 text-destructive"
                                      : "bg-blue-500/15 text-blue-600"
                                  }
                                >
                                  {adm.status === "waitlisted" && inquiry.waitlist_number
                                    ? `Waitlist #${inquiry.waitlist_number}`
                                    : adm.status}
                                </Badge>
                              </TableCell>
                              <TableCell className="text-right">
                                <div className="flex items-center justify-end gap-2">
                                  {adm.status !== "enrolled" && (
                                    <Button
                                      variant="outline"
                                      size="sm"
                                      onClick={() => {
                                        setSelectedAdmission(adm);
                                        setReviewForm({
                                          status: adm.status,
                                          remarks: "",
                                          waitlist_number: inquiry.waitlist_number || 1,
                                          rejection_reason: "",
                                        });
                                        setShowReviewModal(true);
                                      }}
                                    >
                                      Review
                                    </Button>
                                  )}
                                  {adm.status === "approved" && (
                                    <Button
                                      size="sm"
                                      onClick={() => {
                                        setSelectedAdmission(adm);
                                        setShowConfirmModal(true);
                                      }}
                                      className="bg-emerald-600 text-white hover:bg-emerald-700"
                                    >
                                      Confirm & Enroll
                                    </Button>
                                  )}
                                  {adm.status === "enrolled" && (
                                    <Badge variant="outline" className="text-xs text-teal-600 border-teal-500/30">
                                      Enrolled
                                    </Badge>
                                  )}
                                </div>
                              </TableCell>
                            </TableRow>
                          );
                        })
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          )}

          {/* ======================================================== */}
          {/* TAB 3: GUARDIANS & SIBLINGS                              */}
          {/* ======================================================== */}
          {activeTab === "guardians" && (
            <div className="space-y-6">
              <div className="bg-card p-6 rounded-xl border flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                  <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                    <Home className="w-5 h-5 text-primary" />
                    Multi-Child Sibling Management
                  </h3>
                  <p className="text-sm text-muted-foreground mt-1">
                    Every parent account can be mapped to multiple student profiles. In the Parent Portal, guardians switch seamlessly between children without re-authenticating.
                  </p>
                </div>
              </div>

              <Card>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Guardian Name</TableHead>
                        <TableHead>Relation</TableHead>
                        <TableHead>Contact Phone</TableHead>
                        <TableHead>Email Address</TableHead>
                        <TableHead>Occupation</TableHead>
                        <TableHead className="text-center">Linked Children (Siblings)</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {guardians.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={6} className="text-center py-12 text-muted-foreground">
                            No guardian profiles registered yet.
                          </TableCell>
                        </TableRow>
                      ) : (
                        guardians.map((g) => (
                          <TableRow key={g.id}>
                            <TableCell className="font-semibold text-foreground">
                              {g.first_name} {g.last_name}
                            </TableCell>
                            <TableCell className="capitalize text-sm">{g.relation_type}</TableCell>
                            <TableCell className="font-mono text-sm">{g.phone}</TableCell>
                            <TableCell className="text-sm text-muted-foreground">{g.email || "—"}</TableCell>
                            <TableCell className="text-sm">{g.occupation || "—"}</TableCell>
                            <TableCell className="text-center">
                              <Badge variant="secondary" className="px-2 py-1 font-semibold">
                                {g.students_count || 1} child(ren)
                              </Badge>
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
          {/* TAB 4: BULK IMPORT SYSTEM                                */}
          {/* ======================================================== */}
          {activeTab === "import_history" && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <Card className="md:col-span-1">
                  <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                      <Download className="w-4 h-4 text-primary" /> Downloadable Templates
                    </CardTitle>
                    <CardDescription>
                      Download clean Excel/CSV templates with pre-configured headers and sample rows.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <Button onClick={handleDownloadTemplate} variant="outline" className="w-full flex items-center gap-2">
                      <Download className="w-4 h-4" /> Download CSV Template
                    </Button>
                    <div className="text-xs text-muted-foreground bg-muted p-3 rounded-lg space-y-1">
                      <p className="font-semibold text-foreground">Supported Column Schema:</p>
                      <p>first_name, last_name, admission_number, admission_date, date_of_birth, gender, class_name, section_name, guardian_name, guardian_phone</p>
                    </div>
                  </CardContent>
                </Card>

                <Card className="md:col-span-2">
                  <CardHeader>
                    <CardTitle className="text-base flex items-center gap-2">
                      <Upload className="w-4 h-4 text-primary" /> Pre-Flight Import Verification Engine
                    </CardTitle>
                    <CardDescription>
                      Validate duplicate detection, verify branch grade and section matches, and generate row-by-row error diagnostics before persistence.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                      Use the Quick Bulk Import modal to upload JSON or CSV batches with transactional rollback.
                    </p>
                    <Button onClick={() => setShowImportModal(true)} className="flex items-center gap-2 bg-primary text-primary-foreground">
                      <Upload className="w-4 h-4" /> Launch Import Wizard
                    </Button>
                  </CardContent>
                </Card>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ======================================================== */}
      {/* MODAL 1: NEW ONLINE/INTERNAL ADMISSION APPLICATION       */}
      {/* ======================================================== */}
      <Modal
        isOpen={showNewAdmissionModal}
        onClose={() => setShowNewAdmissionModal(false)}
        title="Submit Admission Application"
        description="Submit prospective student inquiry. Generates unique application tracking number ADM-YYYY-XXXX."
      >
        <form onSubmit={handleCreateAdmission} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>Applicant First Name *</Label>
              <Input
                required
                value={admissionForm.applicant_first_name}
                onChange={(e) => setAdmissionForm({ ...admissionForm, applicant_first_name: e.target.value })}
              />
            </div>
            <div>
              <Label>Applicant Last Name *</Label>
              <Input
                required
                value={admissionForm.applicant_last_name}
                onChange={(e) => setAdmissionForm({ ...admissionForm, applicant_last_name: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>Applied Grade / Class *</Label>
              <select
                required
                value={admissionForm.applied_class_id}
                onChange={(e) => setAdmissionForm({ ...admissionForm, applied_class_id: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="">Select Grade</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.code})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Academic Session</Label>
              <select
                value={admissionForm.academic_session_id}
                onChange={(e) => setAdmissionForm({ ...admissionForm, academic_session_id: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="">Active Session</option>
                {sessions.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} {s.is_current ? "(Current)" : ""}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>Date of Birth *</Label>
              <Input
                type="date"
                required
                value={admissionForm.date_of_birth}
                onChange={(e) => setAdmissionForm({ ...admissionForm, date_of_birth: e.target.value })}
              />
            </div>
            <div>
              <Label>Gender *</Label>
              <select
                value={admissionForm.gender}
                onChange={(e) => setAdmissionForm({ ...admissionForm, gender: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 border-t pt-3">
            <div>
              <Label>Parent / Guardian Name *</Label>
              <Input
                required
                value={admissionForm.parent_name}
                onChange={(e) => setAdmissionForm({ ...admissionForm, parent_name: e.target.value })}
              />
            </div>
            <div>
              <Label>Parent Contact Phone *</Label>
              <Input
                required
                placeholder="+91..."
                value={admissionForm.parent_phone}
                onChange={(e) => setAdmissionForm({ ...admissionForm, parent_phone: e.target.value })}
              />
            </div>
          </div>

          <div>
            <Label>Previous School / Background</Label>
            <Input
              placeholder="e.g. St. Xavier High School"
              value={admissionForm.previous_school}
              onChange={(e) => setAdmissionForm({ ...admissionForm, previous_school: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowNewAdmissionModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-primary text-primary-foreground">
              Submit Application
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 2: REVIEW APPLICATION WORKFLOW                     */}
      {/* ======================================================== */}
      <Modal
        isOpen={showReviewModal}
        onClose={() => setShowReviewModal(false)}
        title={`Review Application: ${selectedAdmission?.application_number}`}
        description={`Applicant: ${selectedAdmission?.applicant_first_name} ${selectedAdmission?.applicant_last_name}`}
      >
        <form onSubmit={handleReviewStatus} className="space-y-4">
          <div>
            <Label>Pipeline Status</Label>
            <select
              value={reviewForm.status}
              onChange={(e) => setReviewForm({ ...reviewForm, status: e.target.value })}
              className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
            >
              <option value="under_review">Under Review</option>
              <option value="waitlisted">Waitlisted</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          {reviewForm.status === "waitlisted" && (
            <div>
              <Label>Waitlist Priority Number</Label>
              <Input
                type="number"
                min="1"
                value={reviewForm.waitlist_number}
                onChange={(e) => setReviewForm({ ...reviewForm, waitlist_number: parseInt(e.target.value) || 1 })}
              />
            </div>
          )}

          {reviewForm.status === "rejected" && (
            <div>
              <Label>Rejection Reason</Label>
              <Input
                required
                placeholder="e.g. Entrance criteria not met, Age limit..."
                value={reviewForm.rejection_reason}
                onChange={(e) => setReviewForm({ ...reviewForm, rejection_reason: e.target.value })}
              />
            </div>
          )}

          <div>
            <Label>Review Notes / Remarks</Label>
            <Input
              placeholder="Internal administrative comments..."
              value={reviewForm.remarks}
              onChange={(e) => setReviewForm({ ...reviewForm, remarks: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowReviewModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-primary text-primary-foreground">
              Save Decision
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 3: CONFIRM ADMISSION & ALLOCATE SECTION            */}
      {/* ======================================================== */}
      <Modal
        isOpen={showConfirmModal}
        onClose={() => setShowConfirmModal(false)}
        title="Confirm Admission & Enroll"
        description="Creates student master record, auto-generates STU-YYYY-XXXX admission number, and assigns section."
      >
        <form onSubmit={handleConfirmAdmission} className="space-y-4">
          <div>
            <Label>Select Section *</Label>
            <select
              required
              value={confirmForm.section_id}
              onChange={(e) => setConfirmForm({ ...confirmForm, section_id: e.target.value })}
              className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
            >
              <option value="">Select Section</option>
              {classes
                .find((c) => c.id === selectedAdmission?.applied_class?.id)
                ?.sections?.map((s) => (
                  <option key={s.id} value={s.id}>
                    Section {s.name} (Room {s.room_number || "TBD"})
                  </option>
                ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>Roll Number (Optional)</Label>
              <Input
                placeholder="e.g. 10A-01"
                value={confirmForm.roll_number}
                onChange={(e) => setConfirmForm({ ...confirmForm, roll_number: e.target.value })}
              />
            </div>
            <div>
              <Label>Blood Group</Label>
              <select
                value={confirmForm.blood_group}
                onChange={(e) => setConfirmForm({ ...confirmForm, blood_group: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowConfirmModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-emerald-600 text-white hover:bg-emerald-700">
              Confirm & Issue Admission
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 4: DIRECT STUDENT REGISTRATION                    */}
      {/* ======================================================== */}
      <Modal
        isOpen={showNewStudentModal}
        onClose={() => setShowNewStudentModal(false)}
        title="Direct Student Enrollment"
        description="Direct student admission into active academic session with demographic and guardian linkage."
      >
        <form onSubmit={handleCreateStudent} className="space-y-4">
          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label>First Name *</Label>
              <Input
                required
                value={studentForm.first_name}
                onChange={(e) => setStudentForm({ ...studentForm, first_name: e.target.value })}
              />
            </div>
            <div>
              <Label>Middle Name</Label>
              <Input
                value={studentForm.middle_name}
                onChange={(e) => setStudentForm({ ...studentForm, middle_name: e.target.value })}
              />
            </div>
            <div>
              <Label>Last Name *</Label>
              <Input
                required
                value={studentForm.last_name}
                onChange={(e) => setStudentForm({ ...studentForm, last_name: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label>Date of Birth *</Label>
              <Input
                type="date"
                required
                value={studentForm.date_of_birth}
                onChange={(e) => setStudentForm({ ...studentForm, date_of_birth: e.target.value })}
              />
            </div>
            <div>
              <Label>Gender *</Label>
              <select
                value={studentForm.gender}
                onChange={(e) => setStudentForm({ ...studentForm, gender: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <Label>Blood Group</Label>
              <select
                value={studentForm.blood_group}
                onChange={(e) => setStudentForm({ ...studentForm, blood_group: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="A+">A+</option>
                <option value="B+">B+</option>
                <option value="O+">O+</option>
                <option value="AB+">AB+</option>
              </select>
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3 border-t pt-3">
            <div>
              <Label>Grade / Class *</Label>
              <select
                required
                value={studentForm.class_id}
                onChange={(e) => setStudentForm({ ...studentForm, class_id: e.target.value, section_id: "" })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="">Select Grade</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Section *</Label>
              <select
                required
                value={studentForm.section_id}
                onChange={(e) => setStudentForm({ ...studentForm, section_id: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="">Select Section</option>
                {classes
                  .find((c) => c.id === studentForm.class_id)
                  ?.sections?.map((s) => (
                    <option key={s.id} value={s.id}>
                      Section {s.name}
                    </option>
                  ))}
              </select>
            </div>
            <div>
              <Label>Roll Number</Label>
              <Input
                placeholder="e.g. 10A-05"
                value={studentForm.roll_number}
                onChange={(e) => setStudentForm({ ...studentForm, roll_number: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 border-t pt-3">
            <div>
              <Label>Primary Guardian Name *</Label>
              <Input
                required
                placeholder="e.g. Rajesh Sharma"
                value={studentForm.guardian_name}
                onChange={(e) => setStudentForm({ ...studentForm, guardian_name: e.target.value })}
              />
            </div>
            <div>
              <Label>Guardian Phone *</Label>
              <Input
                required
                placeholder="+91..."
                value={studentForm.guardian_phone}
                onChange={(e) => setStudentForm({ ...studentForm, guardian_phone: e.target.value })}
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowNewStudentModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-primary text-primary-foreground">
              Enroll Student
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 5: STUDENT 360 PROFILE VIEW                        */}
      {/* ======================================================== */}
      <Modal
        isOpen={showProfile360Modal}
        onClose={() => setShowProfile360Modal(false)}
        title={selectedStudent ? `Student 360: ${selectedStudent.full_name}` : "Student Profile"}
        description={`Admission Number: ${selectedStudent?.admission_number} • Enrolled: ${selectedStudent?.admission_date}`}
      >
        {selectedStudent && (
          <div className="space-y-6">
            {/* Top Identity Header */}
            <div className="flex items-center gap-4 bg-muted/50 p-4 rounded-xl border">
              <div className="w-14 h-14 rounded-full bg-primary/20 text-primary flex items-center justify-center font-bold text-xl">
                {selectedStudent.first_name[0]}
                {selectedStudent.last_name[0]}
              </div>
              <div>
                <h3 className="font-bold text-lg text-foreground">{selectedStudent.full_name}</h3>
                <p className="text-xs text-muted-foreground flex items-center gap-2 mt-0.5">
                  <span className="capitalize">{selectedStudent.gender}</span> • DOB: {selectedStudent.date_of_birth}
                  {selectedStudent.blood_group && (
                    <Badge variant="outline" className="text-[10px] px-1.5 py-0">
                      Blood {selectedStudent.blood_group}
                    </Badge>
                  )}
                  <Badge variant="secondary" className="text-[10px] uppercase">
                    {selectedStudent.caste_category || "General"}
                  </Badge>
                </p>
              </div>
            </div>

            {/* Academic History Timeline */}
            <div>
              <h4 className="text-sm font-semibold flex items-center gap-2 mb-2 text-foreground">
                <History className="w-4 h-4 text-primary" /> Academic Progression Timeline (Preserved)
              </h4>
              <div className="border rounded-lg overflow-hidden">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/30">
                      <TableHead>Academic Year</TableHead>
                      <TableHead>Grade & Section</TableHead>
                      <TableHead>Roll Number</TableHead>
                      <TableHead>Progression Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedStudent.enrollments?.map((enr) => (
                      <TableRow key={enr.id}>
                        <TableCell className="font-medium text-xs">
                          {enr.academic_session?.name || "Current Year"}
                        </TableCell>
                        <TableCell className="text-sm font-medium">
                          {enr.school_class?.name} - Section {enr.section?.name}
                        </TableCell>
                        <TableCell className="font-mono text-xs">{enr.roll_number || "—"}</TableCell>
                        <TableCell>
                          <Badge variant={enr.status === "enrolled" ? "default" : "secondary"}>
                            {enr.status}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    )) || (
                      <TableRow>
                        <TableCell colSpan={4} className="text-center py-4 text-xs text-muted-foreground">
                          No historical progression found.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </div>
            </div>

            {/* Guardians & Family Linkage */}
            <div>
              <h4 className="text-sm font-semibold flex items-center gap-2 mb-2 text-foreground">
                <Home className="w-4 h-4 text-primary" /> Linked Guardians & Emergency Contacts
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {selectedStudent.guardians?.map((g) => (
                  <div key={g.id} className="p-3 border rounded-lg bg-card text-xs space-y-1">
                    <div className="font-bold flex items-center justify-between text-foreground">
                      <span>{g.first_name} {g.last_name}</span>
                      <Badge variant="outline" className="text-[10px] capitalize">
                        {g.relation_type}
                      </Badge>
                    </div>
                    <div className="text-muted-foreground flex items-center gap-1">
                      <Phone className="w-3 h-3" /> {g.phone}
                    </div>
                    {g.email && (
                      <div className="text-muted-foreground flex items-center gap-1">
                        <Mail className="w-3 h-3" /> {g.email}
                      </div>
                    )}
                  </div>
                )) || <p className="text-xs text-muted-foreground">No guardians linked.</p>}
              </div>
            </div>

            {/* Quick Lifecycle Action Buttons */}
            <div className="border-t pt-4 flex flex-wrap gap-2 justify-end">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setShowProfile360Modal(false);
                  setShowTransferModal(true);
                }}
                className="flex items-center gap-1.5"
              >
                <FileCheck2 className="w-3.5 h-3.5" /> Transfer Certificate
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setShowProfile360Modal(false);
                  setShowWithdrawModal(true);
                }}
                className="flex items-center gap-1.5 text-amber-600"
              >
                <AlertTriangle className="w-3.5 h-3.5" /> Withdraw Student
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 6: DIGITAL STUDENT ID CARD WITH QR CODE            */}
      {/* ======================================================== */}
      <Modal
        isOpen={showIdCardModal}
        onClose={() => setShowIdCardModal(false)}
        title="Student Digital ID Card"
        description="Printable institutional credential with verifiable digital QR payload."
      >
        {idCardData && (
          <div className="space-y-4">
            {/* Visual ID Card Preview */}
            <div className="bg-gradient-to-br from-slate-900 to-indigo-950 text-white p-6 rounded-2xl shadow-xl border border-indigo-500/20 max-w-sm mx-auto">
              <div className="border-b border-white/10 pb-3 flex items-center justify-between">
                <div>
                  <h4 className="font-extrabold text-xs uppercase tracking-widest text-indigo-400">
                    {idCardData.id_card?.school_name}
                  </h4>
                  <p className="text-[10px] text-white/60">{idCardData.id_card?.branch_name}</p>
                </div>
                <Building className="w-5 h-5 text-indigo-400" />
              </div>

              <div className="py-4 flex gap-4 items-center">
                <div className="w-16 h-16 rounded-xl bg-white/10 flex items-center justify-center font-bold text-2xl text-indigo-300 border border-white/20">
                  {idCardData.id_card?.full_name?.[0]}
                </div>
                <div>
                  <h3 className="font-bold text-base">{idCardData.id_card?.full_name}</h3>
                  <p className="text-xs text-indigo-300 font-mono mt-0.5">{idCardData.id_card?.admission_number}</p>
                  <p className="text-xs text-white/70">
                    {idCardData.id_card?.class_name} • Sec {idCardData.id_card?.section_name} • Roll #{idCardData.id_card?.roll_number}
                  </p>
                </div>
              </div>

              <div className="bg-white/5 p-3 rounded-xl border border-white/10 flex items-center justify-between mt-2">
                <div className="text-[11px] space-y-1">
                  <p className="text-white/60">DOB: <span className="text-white font-medium">{idCardData.id_card?.date_of_birth}</span></p>
                  <p className="text-white/60">Blood: <span className="text-white font-medium">{idCardData.id_card?.blood_group}</span></p>
                  <p className="text-white/60">Emergency: <span className="text-white font-medium">{idCardData.id_card?.emergency_contact}</span></p>
                </div>
                {/* Visual QR Indicator */}
                <div className="w-14 h-14 bg-white rounded-lg p-1 flex items-center justify-center">
                  <QrCode className="w-12 h-12 text-slate-900" />
                </div>
              </div>

              <p className="text-[9px] text-center text-white/40 mt-3 font-mono">
                {idCardData.qr_verification?.qr_data_string}
              </p>
            </div>

            <div className="flex justify-end gap-3 pt-2">
              <Button variant="outline" size="sm" onClick={() => window.print()} className="flex items-center gap-1.5">
                <Printer className="w-4 h-4" /> Print Card
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 7: ACADEMIC PROMOTION MODAL                        */}
      {/* ======================================================== */}
      <Modal
        isOpen={showPromoteModal}
        onClose={() => setShowPromoteModal(false)}
        title={selectedStudent ? `Promote Student: ${selectedStudent.full_name}` : "Promote Student"}
        description="Advances student into next academic session. Preserves historical session enrollment intact without overwriting."
      >
        <form onSubmit={handlePromoteStudent} className="space-y-4">
          <div>
            <Label>Target Academic Session *</Label>
            <select
              required
              value={promoteForm.to_session_id}
              onChange={(e) => setPromoteForm({ ...promoteForm, to_session_id: e.target.value })}
              className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
            >
              <option value="">Select Target Year</option>
              {sessions.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} {s.is_current ? "(Current)" : ""}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>Target Grade / Class *</Label>
              <select
                required
                value={promoteForm.to_class_id}
                onChange={(e) => setPromoteForm({ ...promoteForm, to_class_id: e.target.value, to_section_id: "" })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="">Select Grade</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <Label>Target Section *</Label>
              <select
                required
                value={promoteForm.to_section_id}
                onChange={(e) => setPromoteForm({ ...promoteForm, to_section_id: e.target.value })}
                className="w-full h-10 px-3 py-2 text-sm rounded-md border bg-background"
              >
                <option value="">Select Section</option>
                {classes
                  .find((c) => c.id === promoteForm.to_class_id)
                  ?.sections?.map((s) => (
                    <option key={s.id} value={s.id}>
                      Section {s.name}
                    </option>
                  ))}
              </select>
            </div>
          </div>

          <div>
            <Label>New Roll Number</Label>
            <Input
              placeholder="e.g. 11A-01"
              value={promoteForm.roll_number}
              onChange={(e) => setPromoteForm({ ...promoteForm, roll_number: e.target.value })}
            />
          </div>

          <div>
            <Label>Promotion Remarks</Label>
            <Input
              placeholder="e.g. Promoted to Grade 11 with 92% marks"
              value={promoteForm.remarks}
              onChange={(e) => setPromoteForm({ ...promoteForm, remarks: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowPromoteModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-primary text-primary-foreground">
              Confirm Promotion
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 8: ISSUE TRANSFER CERTIFICATE (TC)                */}
      {/* ======================================================== */}
      <Modal
        isOpen={showTransferModal}
        onClose={() => setShowTransferModal(false)}
        title="Issue Transfer Certificate (TC)"
        description="Generates official TC with unique serial number and clearance status."
      >
        <form onSubmit={handleIssueTransfer} className="space-y-4">
          <div>
            <Label>Destination School Name *</Label>
            <Input
              required
              placeholder="e.g. Delhi Public School"
              value={transferForm.destination_school}
              onChange={(e) => setTransferForm({ ...transferForm, destination_school: e.target.value })}
            />
          </div>

          <div>
            <Label>Reason for Leaving *</Label>
            <Input
              required
              value={transferForm.reason_for_leaving}
              onChange={(e) => setTransferForm({ ...transferForm, reason_for_leaving: e.target.value })}
            />
          </div>

          <div>
            <Label>Date of Issue</Label>
            <Input
              type="date"
              value={transferForm.application_date}
              onChange={(e) => setTransferForm({ ...transferForm, application_date: e.target.value })}
            />
          </div>

          <div className="bg-muted/40 p-3 rounded-lg text-xs space-y-1">
            <p className="font-semibold text-foreground">Automatic Institutional Clearance Checklist:</p>
            <p className="text-muted-foreground">✓ Library books returned • ✓ Fee dues cleared • ✓ Lab clearances verified</p>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowTransferModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-primary text-primary-foreground">
              Issue Transfer Certificate
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 9: STUDENT WITHDRAWAL                              */}
      {/* ======================================================== */}
      <Modal
        isOpen={showWithdrawModal}
        onClose={() => setShowWithdrawModal(false)}
        title="Student Withdrawal"
        description="Marks student enrollment as withdrawn and archives active records."
      >
        <form onSubmit={handleWithdrawStudent} className="space-y-4">
          <div>
            <Label>Withdrawal Reason *</Label>
            <Input
              required
              placeholder="e.g. Medical reasons, Family relocation..."
              value={withdrawForm.reason}
              onChange={(e) => setWithdrawForm({ ...withdrawForm, reason: e.target.value })}
            />
          </div>

          <div>
            <Label>Effective Exit Date</Label>
            <Input
              type="date"
              value={withdrawForm.exit_date}
              onChange={(e) => setWithdrawForm({ ...withdrawForm, exit_date: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={() => setShowWithdrawModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-destructive text-destructive-foreground">
              Confirm Withdrawal
            </Button>
          </div>
        </form>
      </Modal>

      {/* ======================================================== */}
      {/* MODAL 10: BULK IMPORT WIZARD                             */}
      {/* ======================================================== */}
      <Modal
        isOpen={showImportModal}
        onClose={() => setShowImportModal(false)}
        title="Bulk Student Import Wizard"
        description="Validate and ingest student rosters with duplicate checking and background job tracking."
      >
        <div className="space-y-4">
          {importStep === 1 && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <Label>Paste JSON / CSV Array of Student Records</Label>
                <Button variant="link" size="sm" onClick={handleDownloadTemplate} className="h-auto p-0 text-xs">
                  Download Sample CSV
                </Button>
              </div>

              <textarea
                rows={10}
                className="w-full p-3 font-mono text-xs border rounded-lg bg-background"
                placeholder={JSON.stringify(
                  [
                    {
                      first_name: "Aarav",
                      last_name: "Sharma",
                      admission_number: "",
                      date_of_birth: "2016-08-15",
                      gender: "male",
                      class_name: "Grade 10",
                      section_name: "A",
                      guardian_name: "Rajesh Sharma",
                      guardian_phone: "+919876543210",
                    },
                  ],
                  null,
                  2
                )}
                value={importRowsJson}
                onChange={(e) => setImportRowsJson(e.target.value)}
              />

              <div className="flex justify-end gap-3 pt-3 border-t">
                <Button variant="outline" onClick={() => setShowImportModal(false)}>
                  Cancel
                </Button>
                <Button onClick={handlePreviewImport} disabled={!importRowsJson.trim()} className="bg-primary text-primary-foreground">
                  Run Pre-Flight Preview
                </Button>
              </div>
            </div>
          )}

          {importStep === 2 && importPreview && (
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-3 text-center">
                <div className="p-3 bg-muted rounded-lg">
                  <p className="text-xs text-muted-foreground">Total Rows</p>
                  <p className="text-xl font-bold">{importPreview.total_rows}</p>
                </div>
                <div className="p-3 bg-emerald-500/10 text-emerald-600 rounded-lg">
                  <p className="text-xs">Valid Rows</p>
                  <p className="text-xl font-bold">{importPreview.valid_rows}</p>
                </div>
                <div className="p-3 bg-destructive/10 text-destructive rounded-lg">
                  <p className="text-xs">Errors / Collisions</p>
                  <p className="text-xl font-bold">{importPreview.invalid_rows}</p>
                </div>
              </div>

              {importPreview.errors?.length > 0 && (
                <div className="bg-destructive/10 text-destructive border border-destructive/20 p-3 rounded-lg text-xs space-y-1 max-h-36 overflow-y-auto">
                  <p className="font-semibold">Validation Violations Found:</p>
                  {importPreview.errors.map((err: string, i: number) => (
                    <p key={i}>• {err}</p>
                  ))}
                </div>
              )}

              <div className="flex justify-between items-center pt-3 border-t">
                <Button variant="outline" onClick={() => setImportStep(1)}>
                  Back to Editor
                </Button>
                <Button
                  onClick={handleExecuteImport}
                  disabled={importPreview.valid_rows === 0}
                  className="bg-emerald-600 text-white hover:bg-emerald-700"
                >
                  Confirm & Ingest {importPreview.valid_rows} Records
                </Button>
              </div>
            </div>
          )}
        </div>
      </Modal>
    </div>
  );
}

// Wrapper for Next.js App Router Suspense
export default function StudentsPage() {
  return (
    <Suspense
      fallback={
        <div className="flex h-screen items-center justify-center">
          <p className="text-sm text-muted-foreground">Loading Students & Admissions...</p>
        </div>
      }
    >
      <StudentsContent />
    </Suspense>
  );
}

