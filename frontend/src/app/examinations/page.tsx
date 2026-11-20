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
  BookOpenCheck,
  Calendar,
  FileText,
  Upload,
  Download,
  Search,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Clock,
  Printer,
  Plus,
  RefreshCw,
  Trophy,
  Award,
  BarChart3,
  Check,
  X,
  FileSpreadsheet,
  Settings2,
  Eye,
  Sliders,
  Sparkles,
  ChevronRight,
  ShieldCheck,
  GraduationCap,
  Layers,
  School,
  UserCheck,
} from "lucide-react";
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
  Legend,
  Cell,
} from "recharts";

// ==========================================
// TypeScript Interfaces
// ==========================================

interface ExamTypeItem {
  id: string;
  name: string;
  code: string;
  description?: string;
  is_active: boolean;
}

interface GradingRangeItem {
  id?: string;
  grade_name: string;
  min_percentage: number;
  max_percentage: number;
  grade_point?: number;
  description?: string;
}

interface GradingSchemeItem {
  id: string;
  name: string;
  board_type: string;
  calculation_type: string;
  description?: string;
  is_active: boolean;
  ranges?: GradingRangeItem[];
}

interface ExaminationItem {
  id: string;
  title: string;
  academic_session_id: string;
  exam_type_id: string;
  start_date: string;
  end_date: string;
  description?: string;
  status: "draft" | "scheduled" | "ongoing" | "completed" | "published";
  is_published: boolean;
  academic_session?: { id: string; name: string };
  exam_type?: ExamTypeItem;
  schedules_count?: number;
}

interface AssessmentComponentItem {
  id: string;
  component_name: string;
  max_marks: number;
  pass_marks?: number;
  weightage_percentage?: number;
}

interface ExamScheduleItem {
  id: string;
  examination_id: string;
  class_id: string;
  subject_id: string;
  exam_date: string;
  start_time?: string;
  end_time?: string;
  room_number?: string;
  max_marks: number;
  pass_marks: number;
  examination?: { id: string; title: string };
  school_class?: { id: string; name: string; code: string };
  subject?: { id: string; name: string; code: string };
  components?: AssessmentComponentItem[];
}

interface StudentMarksRow {
  enrollment_id: string;
  student_id: string;
  student_name: string;
  admission_number: string;
  roll_number?: string;
  mark_id?: string;
  marks_obtained: number | null | string;
  is_absent: boolean;
  remarks?: string;
  components?: Array<{
    component_id: string;
    component_name: string;
    max_marks: number;
    marks_obtained: number | null | string;
  }>;
}

interface ResultCorrectionItem {
  id: string;
  student_marks_id: string;
  old_marks: number;
  new_marks: number;
  reason: string;
  status: "pending" | "approved" | "rejected";
  created_at: string;
  student_mark?: {
    id: string;
    student?: { first_name: string; last_name: string; admission_number: string };
    schedule?: {
      examination?: { title: string };
      subject?: { name: string; code: string };
      school_class?: { name: string };
    };
  };
  requester?: { name: string; email: string };
  approver?: { name: string; email: string };
}

interface ClassPerformanceAnalytics {
  examination_id: string;
  class_id: string;
  total_students: number;
  appeared_students: number;
  passed_students: number;
  failed_students: number;
  pass_percentage: number;
  class_average: number;
  highest_score: number;
  lowest_score: number;
  grade_distribution: Record<string, number>;
}

interface ClassTopperItem {
  rank: number;
  student_id: string;
  student_name: string;
  admission_number: string;
  total_obtained: number;
  total_max: number;
  percentage: number;
  grade: string;
}

interface ReportCardSubjectItem {
  subject_name: string;
  subject_code: string;
  max_marks: number;
  pass_marks: number;
  marks_obtained: number;
  is_absent: boolean;
  grade: string;
  grade_point?: number;
  is_passed: boolean;
}

interface StudentReportCardData {
  school: {
    name: string;
    code?: string;
    address?: string;
    phone?: string;
    email?: string;
    logo_url?: string;
    affiliation_number?: string;
  };
  student: {
    id: string;
    name: string;
    admission_number: string;
    roll_number?: string;
    class_name: string;
    section_name?: string;
    photo_url?: string;
  };
  examination: {
    id: string;
    title: string;
    academic_year?: string;
    grading_scheme: string;
  };
  performance: ReportCardSubjectItem[];
  summary: {
    total_max_marks: number;
    total_obtained_marks: number;
    percentage: number;
    overall_grade: string;
    overall_grade_point?: number;
    result_status: "PASSED" | "FAILED" | "PROMOTED";
    rank?: number | null;
  };
  attendance: {
    total_days: number;
    days_present: number;
    attendance_percentage: number;
  };
  remarks: {
    teacher_remarks?: string;
    principal_remarks?: string;
  };
  meta: {
    generated_at: string;
    is_signed: boolean;
    ranking_displayed: boolean;
  };
}

export default function ExaminationManagementPage() {
  return (
    <Suspense fallback={<div className="p-8 text-center text-muted-foreground">Loading Examination System...</div>}>
      <ExaminationPageContent />
    </Suspense>
  );
}

function ExaminationPageContent() {
  const { user } = useAuth();
  const searchParams = useSearchParams();

  // Active Tab state
  const [activeTab, setActiveTab] = useState<
    "overview" | "schedules" | "marks" | "corrections" | "grading" | "reportcards" | "analytics"
  >("overview");

  // Notifications
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [loading, setLoading] = useState<boolean>(false);

  // Common Metadata
  const [academicSessions, setAcademicSessions] = useState<Array<{ id: string; name: string }>>([]);
  const [classes, setClasses] = useState<Array<{ id: string; name: string; code: string }>>([]);
  const [subjects, setSubjects] = useState<Array<{ id: string; name: string; code: string }>>([]);
  const [examTypes, setExamTypes] = useState<ExamTypeItem[]>([]);
  const [gradingSchemes, setGradingSchemes] = useState<GradingSchemeItem[]>([]);

  // Examinations Overview
  const [examinations, setExaminations] = useState<ExaminationItem[]>([]);
  const [selectedExamId, setSelectedExamId] = useState<string>("");
  const [selectedClassId, setSelectedClassId] = useState<string>("");
  const [selectedSubjectId, setSelectedSubjectId] = useState<string>("");

  // Modals
  const [showCreateExamModal, setShowCreateExamModal] = useState<boolean>(false);
  const [showScheduleModal, setShowScheduleModal] = useState<boolean>(false);
  const [showPublishModal, setShowPublishModal] = useState<boolean>(false);
  const [showCorrectionModal, setShowCorrectionModal] = useState<boolean>(false);
  const [showCsvImportModal, setShowCsvImportModal] = useState<boolean>(false);
  const [showGradingSchemeModal, setShowGradingSchemeModal] = useState<boolean>(false);

  // Form States: New Exam
  const [newExamForm, setNewExamForm] = useState({
    title: "",
    exam_type_id: "",
    academic_session_id: "",
    start_date: new Date().toISOString().split("T")[0],
    end_date: new Date(Date.now() + 14 * 86400000).toISOString().split("T")[0],
    description: "",
  });

  // Form States: Schedule Subject
  const [scheduleForm, setScheduleForm] = useState({
    examination_id: "",
    class_id: "",
    subject_id: "",
    exam_date: new Date().toISOString().split("T")[0],
    start_time: "09:30",
    end_time: "12:30",
    room_number: "Exam Hall 1",
    max_marks: 100,
    pass_marks: 33,
    components: [] as Array<{ component_name: string; max_marks: number; pass_marks: number }>,
  });

  // Form States: Publish Result
  const [publishForm, setPublishForm] = useState({
    class_id: "",
    allow_parent_view: true,
    allow_student_view: true,
  });

  // Schedules state
  const [schedulesList, setSchedulesList] = useState<ExamScheduleItem[]>([]);
  const [selectedScheduleId, setSelectedScheduleId] = useState<string>("");

  // Marks Entry state
  const [studentMarksData, setStudentMarksData] = useState<StudentMarksRow[]>([]);
  const [activeScheduleDetails, setActiveScheduleDetails] = useState<ExamScheduleItem | null>(null);
  const [csvContentToImport, setCsvContentToImport] = useState<string>("");

  // Correction Requests state
  const [correctionsList, setCorrectionsList] = useState<ResultCorrectionItem[]>([]);
  const [correctionFilter, setCorrectionFilter] = useState<string>("all");
  const [correctionTarget, setCorrectionTarget] = useState<{
    mark_id: string;
    student_name: string;
    current_marks: number;
  } | null>(null);
  const [correctionForm, setCorrectionForm] = useState({
    new_marks: "",
    reason: "",
  });

  // Report Card state
  const [reportCardStudents, setReportCardStudents] = useState<Array<{ id: string; name: string; admission_number: string }>>([]);
  const [selectedStudentId, setSelectedStudentId] = useState<string>("");
  const [showRankingToggle, setShowRankingToggle] = useState<boolean>(true);
  const [reportCardTeacherRemarks, setReportCardTeacherRemarks] = useState<string>("Consistent academic dedication and active class participation.");
  const [reportCardPrincipalRemarks, setReportCardPrincipalRemarks] = useState<string>("Promoted with commendation. Keep up the high standards!");
  const [generatedReportCard, setGeneratedReportCard] = useState<StudentReportCardData | null>(null);

  // Analytics & Toppers state
  const [classAnalytics, setClassAnalytics] = useState<ClassPerformanceAnalytics | null>(null);
  const [classToppers, setClassToppers] = useState<ClassTopperItem[]>([]);

  // Helper notification
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
  // Load Initial Metadata
  // ==========================================

  const loadMetadata = useCallback(async () => {
    try {
      const [sessionsRes, classesRes, subjectsRes, typesRes, schemesRes] = await Promise.all([
        apiClient.get("/academic-sessions").catch(() => ({ data: { data: [] } })),
        apiClient.get("/academic/classes").catch(() => ({ data: { data: [] } })),
        apiClient.get("/academic/subjects").catch(() => ({ data: { data: [] } })),
        apiClient.get("/examinations/types").catch(() => ({ data: { data: [] } })),
        apiClient.get("/examinations/grading-schemes").catch(() => ({ data: { data: [] } })),
      ]);

      const sessList = sessionsRes.data?.data || [];
      const clsList = classesRes.data?.data || [];
      const subList = subjectsRes.data?.data || [];
      const typeList = typesRes.data?.data || [];
      const schemeList = schemesRes.data?.data || [];

      setAcademicSessions(sessList);
      setClasses(clsList);
      setSubjects(subList);
      setExamTypes(typeList);
      setGradingSchemes(schemeList);

      if (sessList.length > 0) {
        setNewExamForm((prev) => ({ ...prev, academic_session_id: sessList[0].id }));
      }
      if (typeList.length > 0) {
        setNewExamForm((prev) => ({ ...prev, exam_type_id: typeList[0].id }));
      }
      if (clsList.length > 0) {
        setSelectedClassId(clsList[0].id);
        setScheduleForm((prev) => ({ ...prev, class_id: clsList[0].id }));
      }
      if (subList.length > 0) {
        setScheduleForm((prev) => ({ ...prev, subject_id: subList[0].id }));
      }
    } catch (err) {
      console.error("Failed to load examination metadata", err);
    }
  }, []);

  const loadExaminations = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get("/examinations");
      const list = res.data?.data || [];
      setExaminations(list);
      if (list.length > 0 && !selectedExamId) {
        setSelectedExamId(list[0].id);
        setScheduleForm((prev) => ({ ...prev, examination_id: list[0].id }));
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to load examinations", true);
    } finally {
      setLoading(false);
    }
  }, [selectedExamId]);

  const loadCorrections = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      if (correctionFilter !== "all") params.append("status", correctionFilter);
      const res = await apiClient.get(`/examinations/corrections?${params.toString()}`);
      setCorrectionsList(res.data?.data || []);
    } catch (err) {
      console.error("Failed to load corrections", err);
    }
  }, [correctionFilter]);

  useEffect(() => {
    loadMetadata();
    loadExaminations();
    loadCorrections();
  }, [loadMetadata, loadExaminations, loadCorrections]);

  // ==========================================
  // Schedule Fetcher
  // ==========================================

  const loadSchedules = useCallback(async () => {
    if (!selectedExamId) return;
    try {
      const params = new URLSearchParams();
      if (selectedClassId) params.append("class_id", selectedClassId);
      const res = await apiClient.get(`/examinations/${selectedExamId}/schedules?${params.toString()}`);
      const list = res.data?.data || [];
      setSchedulesList(list);
      if (list.length > 0 && !selectedScheduleId) {
        setSelectedScheduleId(list[0].id);
      }
    } catch (err) {
      console.error("Failed to load exam schedules", err);
    }
  }, [selectedExamId, selectedClassId, selectedScheduleId]);

  useEffect(() => {
    if (selectedExamId) {
      loadSchedules();
    }
  }, [selectedExamId, selectedClassId, loadSchedules]);

  // ==========================================
  // Marks Entry Fetcher
  // ==========================================

  const loadMarksForSchedule = useCallback(async (schedId: string) => {
    if (!schedId) return;
    setLoading(true);
    try {
      const res = await apiClient.get(`/examinations/schedules/${schedId}/marks`);
      const sched = res.data?.data?.schedule;
      const students = res.data?.data?.students || [];

      setActiveScheduleDetails(sched);
      setStudentMarksData(students);
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to load marks for selected schedule", true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === "marks" && selectedScheduleId) {
      loadMarksForSchedule(selectedScheduleId);
    }
  }, [activeTab, selectedScheduleId, loadMarksForSchedule]);

  // ==========================================
  // Report Card Fetcher
  // ==========================================

  const loadReportCardStudents = useCallback(async () => {
    if (!selectedClassId) return;
    try {
      const res = await apiClient.get(`/students?class_id=${selectedClassId}&status=active`);
      const list = (res.data?.data || []).map((s: any) => ({
        id: s.id,
        name: `${s.first_name} ${s.last_name}`,
        admission_number: s.admission_number,
      }));
      setReportCardStudents(list);
      if (list.length > 0 && !selectedStudentId) {
        setSelectedStudentId(list[0].id);
      }
    } catch (err) {
      console.error("Failed to load students for report card", err);
    }
  }, [selectedClassId, selectedStudentId]);

  useEffect(() => {
    if (activeTab === "reportcards") {
      loadReportCardStudents();
    }
  }, [activeTab, selectedClassId, loadReportCardStudents]);

  const handleGenerateReportCard = async () => {
    if (!selectedExamId || !selectedStudentId) {
      notify("Please select both an examination and a student.", true);
      return;
    }
    setLoading(true);
    try {
      const params = new URLSearchParams({
        show_ranking: showRankingToggle ? "true" : "false",
        teacher_remarks: reportCardTeacherRemarks,
        principal_remarks: reportCardPrincipalRemarks,
      });
      const res = await apiClient.get(
        `/examinations/${selectedExamId}/students/${selectedStudentId}/report-card?${params.toString()}`
      );
      setGeneratedReportCard(res.data?.data || null);
      notify("Student report card generated successfully!");
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to generate report card", true);
    } finally {
      setLoading(false);
    }
  };

  // ==========================================
  // Analytics & Toppers Fetcher
  // ==========================================

  const loadAnalytics = useCallback(async () => {
    if (!selectedExamId || !selectedClassId) return;
    setLoading(true);
    try {
      const [perfRes, toppersRes] = await Promise.all([
        apiClient.get(`/examinations/${selectedExamId}/classes/${selectedClassId}/performance`),
        apiClient.get(`/examinations/${selectedExamId}/classes/${selectedClassId}/toppers?limit=5`),
      ]);
      setClassAnalytics(perfRes.data?.data || null);
      setClassToppers(toppersRes.data?.data || []);
    } catch (err: any) {
      console.error("Failed to load analytics", err);
    } finally {
      setLoading(false);
    }
  }, [selectedExamId, selectedClassId]);

  useEffect(() => {
    if (activeTab === "analytics" && selectedExamId && selectedClassId) {
      loadAnalytics();
    }
  }, [activeTab, selectedExamId, selectedClassId, loadAnalytics]);

  // ==========================================
  // Handlers: Exam Management
  // ==========================================

  const handleCreateExamination = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await apiClient.post("/examinations", newExamForm);
      notify(`Examination "${res.data?.data?.title}" created successfully!`);
      setShowCreateExamModal(false);
      loadExaminations();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to create examination", true);
    }
  };

  const handleScheduleSubject = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post("/examinations/schedules", scheduleForm);
      notify("Subject examination scheduled successfully!");
      setShowScheduleModal(false);
      loadSchedules();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to schedule subject", true);
    }
  };

  const handleCalculateClassResults = async (examId: string, classId: string) => {
    try {
      setLoading(true);
      const res = await apiClient.post(`/examinations/${examId}/classes/${classId}/calculate`);
      notify(`Results and class rankings computed successfully for ${res.data?.data?.length || 0} students!`);
      if (activeTab === "analytics") {
        loadAnalytics();
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Calculation failed", true);
    } finally {
      setLoading(false);
    }
  };

  const handlePublishResults = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedExamId) return;
    try {
      await apiClient.post(`/examinations/${selectedExamId}/publish`, {
        class_id: publishForm.class_id || selectedClassId,
        allow_parent_view: publishForm.allow_parent_view,
        allow_student_view: publishForm.allow_student_view,
      });
      notify("Results published! Students and parents can now view authorized report cards.");
      setShowPublishModal(false);
      loadExaminations();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to publish results", true);
    }
  };

  // ==========================================
  // Handlers: Marks Entry & CSV
  // ==========================================

  const handleSaveMarks = async () => {
    if (!selectedScheduleId || studentMarksData.length === 0) return;
    setLoading(true);
    try {
      const payload = {
        marks: studentMarksData.map((row) => ({
          enrollment_id: row.enrollment_id,
          student_id: row.student_id,
          marks_obtained: row.is_absent ? null : (row.marks_obtained !== "" ? Number(row.marks_obtained) : null),
          is_absent: row.is_absent,
          remarks: row.remarks || null,
          components: row.components?.map((c) => ({
            component_id: c.component_id,
            marks_obtained: c.marks_obtained !== "" ? Number(c.marks_obtained) : 0,
          })),
        })),
      };

      await apiClient.post(`/examinations/schedules/${selectedScheduleId}/marks`, payload);
      notify("Student marks recorded successfully!");
      loadMarksForSchedule(selectedScheduleId);
    } catch (err: any) {
      notify(err.response?.data?.message || "Marks validation error", true);
    } finally {
      setLoading(false);
    }
  };

  const handleDownloadTemplate = async () => {
    if (!selectedScheduleId) return;
    try {
      const res = await apiClient.get(`/examinations/schedules/${selectedScheduleId}/template`, {
        responseType: "blob",
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", `marks_template_${selectedScheduleId}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      notify("CSV template downloaded!");
    } catch (err) {
      notify("Failed to download CSV template", true);
    }
  };

  const handleImportCsv = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedScheduleId || !csvContentToImport.trim()) return;
    setLoading(true);
    try {
      await apiClient.post(`/examinations/schedules/${selectedScheduleId}/import`, {
        csv_content: csvContentToImport,
      });
      notify("Marks imported from CSV successfully!");
      setShowCsvImportModal(false);
      setCsvContentToImport("");
      loadMarksForSchedule(selectedScheduleId);
    } catch (err: any) {
      notify(err.response?.data?.message || "CSV import validation error", true);
    } finally {
      setLoading(false);
    }
  };

  // ==========================================
  // Handlers: Marks Correction Workflow
  // ==========================================

  const handleOpenCorrection = (row: StudentMarksRow) => {
    if (!row.mark_id) {
      notify("No mark record found to correct. Enter marks first.", true);
      return;
    }
    setCorrectionTarget({
      mark_id: row.mark_id,
      student_name: row.student_name,
      current_marks: Number(row.marks_obtained) || 0,
    });
    setCorrectionForm({
      new_marks: String(row.marks_obtained || 0),
      reason: "",
    });
    setShowCorrectionModal(true);
  };

  const handleSubmitCorrection = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!correctionTarget) return;
    try {
      await apiClient.post(`/examinations/marks/${correctionTarget.mark_id}/correction`, {
        new_marks: Number(correctionForm.new_marks),
        reason: correctionForm.reason,
      });
      notify("Correction request submitted for administrative audit & approval.");
      setShowCorrectionModal(false);
      loadCorrections();
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to submit correction request", true);
    }
  };

  const handleReviewCorrection = async (correctionId: string, approve: boolean) => {
    try {
      await apiClient.post(`/examinations/corrections/${correctionId}/review`, {
        approve,
      });
      notify(approve ? "Marks correction approved and ledger updated." : "Correction request rejected.");
      loadCorrections();
      if (selectedScheduleId) {
        loadMarksForSchedule(selectedScheduleId);
      }
    } catch (err: any) {
      notify(err.response?.data?.message || "Failed to process review", true);
    }
  };

  // KPI Calculations
  const totalExams = examinations.length;
  const publishedExams = examinations.filter((e) => e.is_published).length;
  const activeExams = examinations.filter((e) => e.status === "scheduled" || e.status === "ongoing").length;
  const pendingCorrectionsCount = correctionsList.filter((c) => c.status === "pending").length;

  return (
    <div className="flex h-screen bg-background">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Header */}
        <header className="border-b bg-card px-8 py-5 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-3">
              <BookOpenCheck className="w-7 h-7 text-primary" />
              Examination & Result Management System
            </h1>
            <p className="text-sm text-muted-foreground">
              Configurable grading schemes (CBSE/ICSE/State), theory & practical marks entry, result calculation, audit correction workflow, and official report cards.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                loadExaminations();
                loadCorrections();
                notify("Refreshed examination data.");
              }}
              className="flex items-center gap-2"
            >
              <RefreshCw className="w-4 h-4" /> Refresh
            </Button>
            <Can permissions={["examinations.create"]}>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setScheduleForm((prev) => ({
                    ...prev,
                    examination_id: selectedExamId || (examinations[0]?.id ?? ""),
                    class_id: selectedClassId || (classes[0]?.id ?? ""),
                    subject_id: subjects[0]?.id ?? "",
                  }));
                  setShowScheduleModal(true);
                }}
                className="flex items-center gap-2"
              >
                <Calendar className="w-4 h-4" /> Schedule Exam
              </Button>
              <Button
                size="sm"
                onClick={() => setShowCreateExamModal(true)}
                className="flex items-center gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
              >
                <Plus className="w-4 h-4" /> New Examination
              </Button>
            </Can>
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
            onClick={() => setActiveTab("overview")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "overview"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <Layers className="w-4 h-4" /> Examinations Overview
          </button>
          <button
            onClick={() => setActiveTab("schedules")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "schedules"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <Calendar className="w-4 h-4" /> Exam Schedules
          </button>
          <button
            onClick={() => setActiveTab("marks")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "marks"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <FileSpreadsheet className="w-4 h-4" /> Marks Entry & CSV
          </button>
          <button
            onClick={() => setActiveTab("corrections")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "corrections"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <ShieldCheck className="w-4 h-4" /> Marks Corrections
            {pendingCorrectionsCount > 0 && (
              <Badge variant="destructive" className="ml-1 text-xs px-1.5 py-0.2">
                {pendingCorrectionsCount}
              </Badge>
            )}
          </button>
          <button
            onClick={() => setActiveTab("grading")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "grading"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <Sliders className="w-4 h-4" /> Grading Schemes
          </button>
          <button
            onClick={() => setActiveTab("reportcards")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "reportcards"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <FileText className="w-4 h-4" /> Report Cards
          </button>
          <button
            onClick={() => setActiveTab("analytics")}
            className={`py-4 border-b-2 transition-all flex items-center gap-2 ${
              activeTab === "analytics"
                ? "border-primary text-primary font-semibold"
                : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            <BarChart3 className="w-4 h-4" /> Analytics & Toppers
          </button>
        </div>

        {/* Main Content Area */}
        <div className="flex-1 overflow-y-auto p-8 space-y-6">
          {/* ========================================== */}
          {/* TAB 1: EXAMINATIONS OVERVIEW */}
          {/* ========================================== */}
          {activeTab === "overview" && (
            <div className="space-y-6">
              {/* Metric Cards */}
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="bg-card">
                  <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle className="text-sm font-medium text-muted-foreground">Total Examinations</CardTitle>
                    <BookOpenCheck className="w-4 h-4 text-primary" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold">{totalExams}</div>
                    <p className="text-xs text-muted-foreground">Across active sessions</p>
                  </CardContent>
                </Card>

                <Card className="bg-card">
                  <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle className="text-sm font-medium text-muted-foreground">Active / Scheduled</CardTitle>
                    <Clock className="w-4 h-4 text-amber-500" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">{activeExams}</div>
                    <p className="text-xs text-muted-foreground">In progress or upcoming</p>
                  </CardContent>
                </Card>

                <Card className="bg-card">
                  <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle className="text-sm font-medium text-muted-foreground">Published Results</CardTitle>
                    <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{publishedExams}</div>
                    <p className="text-xs text-muted-foreground">Visible to students & parents</p>
                  </CardContent>
                </Card>

                <Card className="bg-card">
                  <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                    <CardTitle className="text-sm font-medium text-muted-foreground">Pending Corrections</CardTitle>
                    <ShieldCheck className="w-4 h-4 text-rose-500" />
                  </CardHeader>
                  <CardContent>
                    <div className="text-2xl font-bold text-rose-600 dark:text-rose-400">{pendingCorrectionsCount}</div>
                    <p className="text-xs text-muted-foreground">Requires principal review</p>
                  </CardContent>
                </Card>
              </div>

              {/* Master Examinations Table */}
              <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                  <div>
                    <CardTitle>Master Examinations Registry</CardTitle>
                    <CardDescription>Academic assessment terms, unit tests, and annual examination milestones.</CardDescription>
                  </div>
                  <Button
                    size="sm"
                    onClick={() => setShowCreateExamModal(true)}
                    className="flex items-center gap-2"
                  >
                    <Plus className="w-4 h-4" /> Add Examination
                  </Button>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Examination Title</TableHead>
                        <TableHead>Assessment Type</TableHead>
                        <TableHead>Academic Session</TableHead>
                        <TableHead>Date Range</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Published</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {examinations.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                            No examinations created yet. Click "New Examination" to get started.
                          </TableCell>
                        </TableRow>
                      ) : (
                        examinations.map((exam) => (
                          <TableRow key={exam.id} className={selectedExamId === exam.id ? "bg-muted/40" : ""}>
                            <TableCell className="font-semibold text-foreground">
                              {exam.title}
                              {exam.description && (
                                <p className="text-xs text-muted-foreground font-normal">{exam.description}</p>
                              )}
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline">{exam.exam_type?.name || "General Assessment"}</Badge>
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                              {exam.academic_session?.name || "Current Session"}
                            </TableCell>
                            <TableCell className="text-sm">
                              {exam.start_date} to {exam.end_date}
                            </TableCell>
                            <TableCell>
                              <Badge
                                variant={
                                  exam.status === "completed"
                                    ? "secondary"
                                    : exam.status === "ongoing"
                                    ? "default"
                                    : "outline"
                                }
                                className="capitalize"
                              >
                                {exam.status}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {exam.is_published ? (
                                <Badge className="bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30">
                                  Published
                                </Badge>
                              ) : (
                                <Badge variant="secondary">Draft / Private</Badge>
                              )}
                            </TableCell>
                            <TableCell className="text-right space-x-2">
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                  setSelectedExamId(exam.id);
                                  setActiveTab("schedules");
                                }}
                              >
                                Schedules
                              </Button>
                              <Can permissions={["examinations.publish"]}>
                                <Button
                                  variant="outline"
                                  size="sm"
                                  onClick={() => {
                                    setSelectedExamId(exam.id);
                                    setPublishForm((prev) => ({ ...prev, class_id: selectedClassId || classes[0]?.id }));
                                    setShowPublishModal(true);
                                  }}
                                  className="text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10"
                                >
                                  Publish
                                </Button>
                              </Can>
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

          {/* ========================================== */}
          {/* TAB 2: EXAM SCHEDULES */}
          {/* ========================================== */}
          {activeTab === "schedules" && (
            <div className="space-y-6">
              {/* Filter Bar */}
              <div className="flex flex-wrap items-center justify-between gap-4 bg-card p-4 rounded-xl border">
                <div className="flex flex-wrap items-center gap-4">
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Select Examination</Label>
                    <select
                      value={selectedExamId}
                      onChange={(e) => setSelectedExamId(e.target.value)}
                      className="h-9 w-64 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {examinations.map((exam) => (
                        <option key={exam.id} value={exam.id}>
                          {exam.title}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Filter by Class</Label>
                    <select
                      value={selectedClassId}
                      onChange={(e) => setSelectedClassId(e.target.value)}
                      className="h-9 w-48 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      <option value="">All Classes</option>
                      {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    onClick={() => {
                      setScheduleForm((prev) => ({
                        ...prev,
                        examination_id: selectedExamId,
                        class_id: selectedClassId || (classes[0]?.id ?? ""),
                        subject_id: subjects[0]?.id ?? "",
                      }));
                      setShowScheduleModal(true);
                    }}
                    className="flex items-center gap-2"
                  >
                    <Plus className="w-4 h-4" /> Add Schedule
                  </Button>
                </div>
              </div>

              {/* Schedules Table */}
              <Card>
                <CardHeader>
                  <CardTitle>Subject Examination Timetable</CardTitle>
                  <CardDescription>
                    Scheduled subjects with assigned examination date, duration, room allocation, and assessment breakdown.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Class</TableHead>
                        <TableHead>Subject</TableHead>
                        <TableHead>Exam Date</TableHead>
                        <TableHead>Time Slot</TableHead>
                        <TableHead>Hall / Room</TableHead>
                        <TableHead>Max Marks</TableHead>
                        <TableHead>Pass Marks</TableHead>
                        <TableHead>Components</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {schedulesList.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                            No subject examinations scheduled yet for this selection.
                          </TableCell>
                        </TableRow>
                      ) : (
                        schedulesList.map((sched) => (
                          <TableRow key={sched.id}>
                            <TableCell className="font-medium">{sched.school_class?.name || "Class"}</TableCell>
                            <TableCell className="font-semibold text-foreground">
                              {sched.subject?.name}
                              <span className="text-xs text-muted-foreground block font-normal">
                                {sched.subject?.code}
                              </span>
                            </TableCell>
                            <TableCell>{sched.exam_date}</TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                              {sched.start_time || "09:30"} - {sched.end_time || "12:30"}
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline">{sched.room_number || "Hall 1"}</Badge>
                            </TableCell>
                            <TableCell className="font-semibold">{Number(sched.max_marks)}</TableCell>
                            <TableCell className="text-muted-foreground">{Number(sched.pass_marks)}</TableCell>
                            <TableCell>
                              {sched.components && sched.components.length > 0 ? (
                                <div className="flex flex-wrap gap-1">
                                  {sched.components.map((c) => (
                                    <Badge key={c.id} variant="secondary" className="text-xs">
                                      {c.component_name} ({Number(c.max_marks)})
                                    </Badge>
                                  ))}
                                </div>
                              ) : (
                                <span className="text-xs text-muted-foreground">Single Score</span>
                              )}
                            </TableCell>
                            <TableCell className="text-right space-x-2">
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                  setSelectedScheduleId(sched.id);
                                  setActiveTab("marks");
                                }}
                                className="flex items-center gap-1.5"
                              >
                                <FileSpreadsheet className="w-3.5 h-3.5" /> Enter Marks
                              </Button>
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

          {/* ========================================== */}
          {/* TAB 3: MARKS ENTRY & BULK CSV */}
          {/* ========================================== */}
          {activeTab === "marks" && (
            <div className="space-y-6">
              {/* Schedule Picker Bar */}
              <div className="flex flex-wrap items-center justify-between gap-4 bg-card p-4 rounded-xl border">
                <div className="flex flex-wrap items-center gap-4">
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Select Examination</Label>
                    <select
                      value={selectedExamId}
                      onChange={(e) => setSelectedExamId(e.target.value)}
                      className="h-9 w-60 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {examinations.map((exam) => (
                        <option key={exam.id} value={exam.id}>
                          {exam.title}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Select Scheduled Subject</Label>
                    <select
                      value={selectedScheduleId}
                      onChange={(e) => {
                        setSelectedScheduleId(e.target.value);
                        loadMarksForSchedule(e.target.value);
                      }}
                      className="h-9 w-72 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {schedulesList.map((sched) => (
                        <option key={sched.id} value={sched.id}>
                          {sched.school_class?.name} - {sched.subject?.name} (Max: {Number(sched.max_marks)})
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleDownloadTemplate}
                    disabled={!selectedScheduleId}
                    className="flex items-center gap-2"
                  >
                    <Download className="w-4 h-4" /> Download CSV Template
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowCsvImportModal(true)}
                    disabled={!selectedScheduleId}
                    className="flex items-center gap-2"
                  >
                    <Upload className="w-4 h-4" /> Import CSV Marks
                  </Button>
                  <Button
                    size="sm"
                    onClick={handleSaveMarks}
                    disabled={loading || studentMarksData.length === 0}
                    className="flex items-center gap-2 bg-primary text-primary-foreground hover:bg-primary/90"
                  >
                    <Check className="w-4 h-4" /> Save All Marks
                  </Button>
                </div>
              </div>

              {/* Schedule Context Banner */}
              {activeScheduleDetails && (
                <div className="bg-primary/5 border border-primary/20 rounded-xl p-4 flex flex-wrap items-center justify-between text-sm">
                  <div className="flex items-center gap-6">
                    <div>
                      <span className="text-muted-foreground">Class:</span>{" "}
                      <strong className="text-foreground">{activeScheduleDetails.school_class?.name}</strong>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Subject:</span>{" "}
                      <strong className="text-foreground">{activeScheduleDetails.subject?.name}</strong>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Maximum Marks:</span>{" "}
                      <strong className="text-primary font-bold">{Number(activeScheduleDetails.max_marks)}</strong>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Passing Marks:</span>{" "}
                      <strong className="text-foreground">{Number(activeScheduleDetails.pass_marks)}</strong>
                    </div>
                  </div>
                  <div className="text-xs text-muted-foreground">
                    Only assigned subject teachers and administrative roles can commit score modifications.
                  </div>
                </div>
              )}

              {/* Student Marks Entry Grid */}
              <Card>
                <CardHeader>
                  <CardTitle>Class Marks Entry Roster</CardTitle>
                  <CardDescription>
                    Enter subject marks directly into the grid, toggle absent status, or inspect sub-assessment components.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-16">Roll No</TableHead>
                        <TableHead>Admission No</TableHead>
                        <TableHead>Student Name</TableHead>
                        <TableHead className="w-32">Marks Obtained</TableHead>
                        <TableHead className="w-24 text-center">Absent</TableHead>
                        <TableHead>Sub-Components (Theory/Practical)</TableHead>
                        <TableHead>Remarks</TableHead>
                        <TableHead className="text-right">Audit Action</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {studentMarksData.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                            No students enrolled in this class or schedule not selected.
                          </TableCell>
                        </TableRow>
                      ) : (
                        studentMarksData.map((row, index) => {
                          const maxMarks = activeScheduleDetails ? Number(activeScheduleDetails.max_marks) : 100;
                          const currentMarksNum = Number(row.marks_obtained);
                          const isInvalid = !row.is_absent && row.marks_obtained !== "" && currentMarksNum > maxMarks;

                          return (
                            <TableRow key={row.enrollment_id} className={isInvalid ? "bg-rose-500/10" : ""}>
                              <TableCell className="font-mono text-xs">{row.roll_number || index + 1}</TableCell>
                              <TableCell className="font-mono text-xs text-muted-foreground">
                                {row.admission_number}
                              </TableCell>
                              <TableCell className="font-medium">{row.student_name}</TableCell>
                              <TableCell>
                                <Input
                                  type="number"
                                  min={0}
                                  max={maxMarks}
                                  step="0.5"
                                  disabled={row.is_absent}
                                  value={row.is_absent ? "" : (row.marks_obtained ?? "")}
                                  onChange={(e) => {
                                    const val = e.target.value;
                                    const updated = [...studentMarksData];
                                    updated[index].marks_obtained = val === "" ? "" : Number(val);
                                    setStudentMarksData(updated);
                                  }}
                                  placeholder={row.is_absent ? "ABS" : "0-100"}
                                  className={`h-8 font-semibold ${
                                    isInvalid ? "border-rose-500 text-rose-600 focus-visible:ring-rose-500" : ""
                                  }`}
                                />
                                {isInvalid && (
                                  <span className="text-[10px] text-rose-500 font-medium block">
                                    Exceeds {maxMarks}
                                  </span>
                                )}
                              </TableCell>
                              <TableCell className="text-center">
                                <input
                                  type="checkbox"
                                  checked={row.is_absent}
                                  onChange={(e) => {
                                    const isAbs = e.target.checked;
                                    const updated = [...studentMarksData];
                                    updated[index].is_absent = isAbs;
                                    if (isAbs) {
                                      updated[index].marks_obtained = null;
                                    }
                                    setStudentMarksData(updated);
                                  }}
                                  className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                />
                              </TableCell>
                              <TableCell>
                                {row.components && row.components.length > 0 ? (
                                  <div className="flex flex-wrap gap-2">
                                    {row.components.map((comp, compIdx) => (
                                      <div key={comp.component_id} className="flex items-center gap-1 text-xs">
                                        <span className="text-muted-foreground">{comp.component_name}:</span>
                                        <Input
                                          type="number"
                                          min={0}
                                          max={comp.max_marks}
                                          value={comp.marks_obtained ?? ""}
                                          disabled={row.is_absent}
                                          onChange={(e) => {
                                            const updated = [...studentMarksData];
                                            if (updated[index].components) {
                                              updated[index].components![compIdx].marks_obtained =
                                                e.target.value === "" ? "" : Number(e.target.value);
                                            }
                                            setStudentMarksData(updated);
                                          }}
                                          className="h-7 w-16 text-xs p-1"
                                        />
                                        <span className="text-[10px] text-muted-foreground">/{comp.max_marks}</span>
                                      </div>
                                    ))}
                                  </div>
                                ) : (
                                  <span className="text-xs text-muted-foreground">Standard marks</span>
                                )}
                              </TableCell>
                              <TableCell>
                                <Input
                                  type="text"
                                  value={row.remarks || ""}
                                  placeholder="Optional note"
                                  onChange={(e) => {
                                    const updated = [...studentMarksData];
                                    updated[index].remarks = e.target.value;
                                    setStudentMarksData(updated);
                                  }}
                                  className="h-8 text-xs"
                                />
                              </TableCell>
                              <TableCell className="text-right">
                                {row.mark_id ? (
                                  <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => handleOpenCorrection(row)}
                                    className="text-xs text-amber-600 hover:text-amber-700 hover:bg-amber-500/10"
                                  >
                                    Request Fix
                                  </Button>
                                ) : (
                                  <span className="text-xs text-muted-foreground">New Entry</span>
                                )}
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

          {/* ========================================== */}
          {/* TAB 4: MARKS CORRECTIONS WORKFLOW */}
          {/* ========================================== */}
          {activeTab === "corrections" && (
            <div className="space-y-6">
              {/* Filter */}
              <div className="flex items-center justify-between bg-card p-4 rounded-xl border">
                <div>
                  <h3 className="font-semibold text-foreground">Marks Correction Audit Queue</h3>
                  <p className="text-xs text-muted-foreground">
                    All post-submission score revisions require reason documentation and principal/admin sign-off.
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <Label className="text-xs text-muted-foreground">Status Filter:</Label>
                  <select
                    value={correctionFilter}
                    onChange={(e) => setCorrectionFilter(e.target.value)}
                    className="h-9 w-36 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                  >
                    <option value="all">All Requests</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                  </select>
                  <Button variant="outline" size="sm" onClick={loadCorrections}>
                    <RefreshCw className="w-4 h-4" />
                  </Button>
                </div>
              </div>

              {/* Corrections Table */}
              <Card>
                <CardContent className="pt-6">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Student</TableHead>
                        <TableHead>Examination & Subject</TableHead>
                        <TableHead>Score Change</TableHead>
                        <TableHead>Justification Reason</TableHead>
                        <TableHead>Requested By</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Administrative Action</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {correctionsList.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                            No marks correction requests found in audit queue.
                          </TableCell>
                        </TableRow>
                      ) : (
                        correctionsList.map((item) => (
                          <TableRow key={item.id}>
                            <TableCell className="font-medium">
                              {item.student_mark?.student?.first_name} {item.student_mark?.student?.last_name}
                              <span className="text-xs text-muted-foreground block font-mono">
                                {item.student_mark?.student?.admission_number}
                              </span>
                            </TableCell>
                            <TableCell>
                              <div className="text-sm font-semibold">
                                {item.student_mark?.schedule?.subject?.name}
                              </div>
                              <div className="text-xs text-muted-foreground">
                                {item.student_mark?.schedule?.examination?.title} ({item.student_mark?.schedule?.school_class?.name})
                              </div>
                            </TableCell>
                            <TableCell>
                              <div className="flex items-center gap-2">
                                <span className="line-through text-muted-foreground text-xs">{Number(item.old_marks)}</span>
                                <span className="font-bold text-foreground text-sm">{Number(item.new_marks)}</span>
                              </div>
                            </TableCell>
                            <TableCell className="text-sm italic max-w-xs">{item.reason}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {item.requester?.name || "Teacher"}
                              <span className="block text-[10px]">{new Date(item.created_at).toLocaleDateString()}</span>
                            </TableCell>
                            <TableCell>
                              <Badge
                                variant={
                                  item.status === "approved"
                                    ? "default"
                                    : item.status === "rejected"
                                    ? "destructive"
                                    : "outline"
                                }
                                className="capitalize"
                              >
                                {item.status}
                              </Badge>
                            </TableCell>
                            <TableCell className="text-right space-x-2">
                              {item.status === "pending" ? (
                                <Can permissions={["examinations.publish"]}>
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => handleReviewCorrection(item.id, true)}
                                    className="text-emerald-600 hover:bg-emerald-500/10 text-xs"
                                  >
                                    Approve
                                  </Button>
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => handleReviewCorrection(item.id, false)}
                                    className="text-rose-600 hover:bg-rose-500/10 text-xs"
                                  >
                                    Reject
                                  </Button>
                                </Can>
                              ) : (
                                <span className="text-xs text-muted-foreground">Reviewed</span>
                              )}
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

          {/* ========================================== */}
          {/* TAB 5: CONFIGURABLE GRADING SCHEMES */}
          {/* ========================================== */}
          {activeTab === "grading" && (
            <div className="space-y-6">
              <div className="flex items-center justify-between bg-card p-4 rounded-xl border">
                <div>
                  <h3 className="font-semibold text-foreground">Configurable Assessment & Grading Engine</h3>
                  <p className="text-xs text-muted-foreground">
                    Support CBSE 9-point scale, ICSE percentages, State Board GPA, or custom international scales without hardcoding.
                  </p>
                </div>
                <Button size="sm" onClick={() => setShowGradingSchemeModal(true)} className="flex items-center gap-2">
                  <Plus className="w-4 h-4" /> Create Scheme
                </Button>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {gradingSchemes.map((scheme) => (
                  <Card key={scheme.id} className="relative overflow-hidden">
                    <CardHeader className="pb-3 border-b">
                      <div className="flex items-center justify-between">
                        <CardTitle className="text-lg flex items-center gap-2">
                          <Sliders className="w-5 h-5 text-primary" />
                          {scheme.name}
                        </CardTitle>
                        <Badge variant="outline">{scheme.board_type.toUpperCase()}</Badge>
                      </div>
                      <CardDescription>
                        Calculation: <span className="font-semibold text-foreground capitalize">{scheme.calculation_type}</span>
                        {scheme.description && ` • ${scheme.description}`}
                      </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-4">
                      <h4 className="text-xs font-semibold uppercase text-muted-foreground tracking-wider mb-2">
                        Grade Intervals & Points
                      </h4>
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Grade</TableHead>
                            <TableHead>Min %</TableHead>
                            <TableHead>Max %</TableHead>
                            <TableHead>Grade Point</TableHead>
                            <TableHead>Remarks</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {scheme.ranges && scheme.ranges.length > 0 ? (
                            scheme.ranges.map((range, rIdx) => (
                              <TableRow key={rIdx}>
                                <TableCell className="font-bold text-primary">{range.grade_name}</TableCell>
                                <TableCell>{Number(range.min_percentage)}%</TableCell>
                                <TableCell>{Number(range.max_percentage)}%</TableCell>
                                <TableCell className="font-mono">{range.grade_point ?? "-"}</TableCell>
                                <TableCell className="text-xs text-muted-foreground">{range.description || "-"}</TableCell>
                              </TableRow>
                            ))
                          ) : (
                            <TableRow>
                              <TableCell colSpan={5} className="text-center py-4 text-xs text-muted-foreground">
                                No custom intervals defined.
                              </TableCell>
                            </TableRow>
                          )}
                        </TableBody>
                      </Table>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          )}

          {/* ========================================== */}
          {/* TAB 6: REPORT CARD BUILDER & VIEWER */}
          {/* ========================================== */}
          {activeTab === "reportcards" && (
            <div className="space-y-6">
              {/* Controls */}
              <div className="flex flex-wrap items-center justify-between gap-4 bg-card p-4 rounded-xl border">
                <div className="flex flex-wrap items-center gap-4">
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Examination</Label>
                    <select
                      value={selectedExamId}
                      onChange={(e) => setSelectedExamId(e.target.value)}
                      className="h-9 w-60 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {examinations.map((exam) => (
                        <option key={exam.id} value={exam.id}>
                          {exam.title}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Class</Label>
                    <select
                      value={selectedClassId}
                      onChange={(e) => setSelectedClassId(e.target.value)}
                      className="h-9 w-44 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Student</Label>
                    <select
                      value={selectedStudentId}
                      onChange={(e) => setSelectedStudentId(e.target.value)}
                      className="h-9 w-60 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {reportCardStudents.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.name} ({s.admission_number})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="flex items-center gap-2 pt-5">
                    <input
                      type="checkbox"
                      id="rankingToggle"
                      checked={showRankingToggle}
                      onChange={(e) => setShowRankingToggle(e.target.checked)}
                      className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                    >
                    </input>
                    <label htmlFor="rankingToggle" className="text-xs font-medium cursor-pointer">
                      Show Class Ranking (Configurable Policy)
                    </label>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    onClick={handleGenerateReportCard}
                    disabled={loading || !selectedStudentId}
                    className="flex items-center gap-2"
                  >
                    <Eye className="w-4 h-4" /> Generate Report Card
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => window.print()}
                    disabled={!generatedReportCard}
                    className="flex items-center gap-2"
                  >
                    <Printer className="w-4 h-4" /> Print / Save PDF
                  </Button>
                </div>
              </div>

              {/* Printable Official Report Card Document */}
              {generatedReportCard ? (
                <div id="printable-report-card" className="bg-white text-slate-900 border shadow-md rounded-xl p-8 max-w-4xl mx-auto space-y-6">
                  {/* School Header */}
                  <div className="border-b-2 border-slate-900 pb-4 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                      <div className="w-16 h-16 rounded-full bg-slate-100 border border-slate-300 flex items-center justify-center">
                        <GraduationCap className="w-8 h-8 text-slate-800" />
                      </div>
                      <div>
                        <h2 className="text-2xl font-bold tracking-tight uppercase">
                          {generatedReportCard.school.name}
                        </h2>
                        <p className="text-xs text-slate-600">
                          Affiliation: {generatedReportCard.school.affiliation_number || "CBSE / State Board Affiliated"} • School Code: {generatedReportCard.school.code || "SCH-001"}
                        </p>
                        <p className="text-xs text-slate-500">{generatedReportCard.school.address || "Main Campus, Education Boulevard"}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <Badge className="bg-slate-900 text-white hover:bg-slate-800 uppercase tracking-widest text-xs px-3 py-1">
                        Official Academic Record
                      </Badge>
                      <p className="text-xs text-slate-500 mt-1">
                        Academic Year: {generatedReportCard.examination.academic_year || "2026-2027"}
                      </p>
                    </div>
                  </div>

                  {/* Student Bio Grid */}
                  <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                    <div>
                      <span className="text-slate-500 block">Student Name:</span>
                      <strong className="text-sm font-bold text-slate-900">{generatedReportCard.student.name}</strong>
                    </div>
                    <div>
                      <span className="text-slate-500 block">Admission Number:</span>
                      <strong className="font-mono text-slate-800">{generatedReportCard.student.admission_number}</strong>
                    </div>
                    <div>
                      <span className="text-slate-500 block">Class & Section:</span>
                      <strong className="text-slate-800">{generatedReportCard.student.class_name}</strong>
                    </div>
                    <div>
                      <span className="text-slate-500 block">Roll Number:</span>
                      <strong className="font-mono text-slate-800">{generatedReportCard.student.roll_number || "01"}</strong>
                    </div>
                  </div>

                  {/* Examination Performance Table */}
                  <div>
                    <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                      Assessment Marks & Grade Evaluation ({generatedReportCard.examination.title})
                    </h4>
                    <table className="w-full text-left border-collapse border border-slate-300 text-xs">
                      <thead>
                        <tr className="bg-slate-100 border-b border-slate-300">
                          <th className="p-2 border-r border-slate-300">Subject Name</th>
                          <th className="p-2 border-r border-slate-300 text-center">Max Marks</th>
                          <th className="p-2 border-r border-slate-300 text-center">Pass Marks</th>
                          <th className="p-2 border-r border-slate-300 text-center">Marks Obtained</th>
                          <th className="p-2 border-r border-slate-300 text-center">Grade</th>
                          <th className="p-2 border-r border-slate-300 text-center">Grade Point</th>
                          <th className="p-2 text-center">Result</th>
                        </tr>
                      </thead>
                      <tbody>
                        {generatedReportCard.performance.map((sub, sIdx) => (
                          <tr key={sIdx} className="border-b border-slate-200">
                            <td className="p-2 border-r border-slate-300 font-medium">
                              {sub.subject_name}
                              <span className="text-[10px] text-slate-500 ml-1">({sub.subject_code})</span>
                            </td>
                            <td className="p-2 border-r border-slate-300 text-center">{sub.max_marks}</td>
                            <td className="p-2 border-r border-slate-300 text-center">{sub.pass_marks}</td>
                            <td className="p-2 border-r border-slate-300 text-center font-bold">
                              {sub.is_absent ? <span className="text-rose-600">ABS</span> : sub.marks_obtained}
                            </td>
                            <td className="p-2 border-r border-slate-300 text-center font-bold text-indigo-700">
                              {sub.grade}
                            </td>
                            <td className="p-2 border-r border-slate-300 text-center font-mono">
                              {sub.grade_point ?? "-"}
                            </td>
                            <td className="p-2 text-center">
                              {sub.is_passed ? (
                                <span className="text-emerald-700 font-semibold">PASS</span>
                              ) : (
                                <span className="text-rose-600 font-semibold">FAIL</span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Summary & Attendance Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="border border-slate-200 rounded-lg p-3 bg-slate-50 space-y-1 text-xs">
                      <h5 className="font-bold text-slate-800 uppercase tracking-wide">Performance Summary</h5>
                      <div className="flex justify-between">
                        <span>Total Marks:</span>
                        <strong>
                          {generatedReportCard.summary.total_obtained_marks} / {generatedReportCard.summary.total_max_marks}
                        </strong>
                      </div>
                      <div className="flex justify-between">
                        <span>Overall Percentage:</span>
                        <strong className="text-indigo-700 font-bold">{generatedReportCard.summary.percentage}%</strong>
                      </div>
                      <div className="flex justify-between">
                        <span>Overall Grade:</span>
                        <strong className="text-indigo-700 font-bold">{generatedReportCard.summary.overall_grade}</strong>
                      </div>
                      {generatedReportCard.summary.rank !== null && generatedReportCard.summary.rank !== undefined ? (
                        <div className="flex justify-between bg-indigo-50 p-1 rounded font-semibold text-indigo-900">
                          <span>Class Rank:</span>
                          <strong>#{generatedReportCard.summary.rank} in Class</strong>
                        </div>
                      ) : (
                        <div className="flex justify-between text-slate-500 italic text-[11px]">
                          <span>Class Rank:</span>
                          <span>Non-Competitive / Unranked</span>
                        </div>
                      )}
                      <div className="flex justify-between pt-1 border-t">
                        <span>Outcome:</span>
                        <Badge
                          className={
                            generatedReportCard.summary.result_status === "PASSED" ||
                            generatedReportCard.summary.result_status === "PROMOTED"
                              ? "bg-emerald-600 text-white"
                              : "bg-rose-600 text-white"
                          }
                        >
                          {generatedReportCard.summary.result_status}
                        </Badge>
                      </div>
                    </div>

                    <div className="border border-slate-200 rounded-lg p-3 bg-slate-50 space-y-1 text-xs">
                      <h5 className="font-bold text-slate-800 uppercase tracking-wide">Attendance & Conduct</h5>
                      <div className="flex justify-between">
                        <span>School Working Days:</span>
                        <strong>{generatedReportCard.attendance.total_days} days</strong>
                      </div>
                      <div className="flex justify-between">
                        <span>Days Present:</span>
                        <strong>{generatedReportCard.attendance.days_present} days</strong>
                      </div>
                      <div className="flex justify-between">
                        <span>Attendance Ratio:</span>
                        <strong>{generatedReportCard.attendance.attendance_percentage}%</strong>
                      </div>
                      <div className="pt-2 border-t text-[11px] text-slate-600">
                        <span className="font-semibold block text-slate-800">Teacher Remarks:</span>
                        <em>"{generatedReportCard.remarks.teacher_remarks || "Satisfactory academic performance."}"</em>
                      </div>
                    </div>
                  </div>

                  {/* Signatures Block */}
                  <div className="pt-12 grid grid-cols-3 gap-6 text-center text-xs text-slate-600 border-t border-slate-300">
                    <div>
                      <div className="border-b border-slate-400 mb-1 w-36 mx-auto"></div>
                      <span>Class Teacher</span>
                    </div>
                    <div>
                      <div className="border-b border-slate-400 mb-1 w-36 mx-auto"></div>
                      <span>Parent / Guardian</span>
                    </div>
                    <div>
                      <div className="border-b border-slate-400 mb-1 w-36 mx-auto"></div>
                      <span className="font-bold text-slate-800">Principal Signature & Seal</span>
                    </div>
                  </div>
                </div>
              ) : (
                <Card>
                  <CardContent className="py-16 text-center text-muted-foreground space-y-3">
                    <FileText className="w-12 h-12 mx-auto text-muted-foreground/50" />
                    <p className="text-sm">
                      Select an examination and student above, then click <strong>"Generate Report Card"</strong> to render the official transcript.
                    </p>
                  </CardContent>
                </Card>
              )}
            </div>
          )}

          {/* ========================================== */}
          {/* TAB 7: ANALYTICS & TOPPERS */}
          {/* ========================================== */}
          {activeTab === "analytics" && (
            <div className="space-y-6">
              {/* Controls */}
              <div className="flex flex-wrap items-center justify-between gap-4 bg-card p-4 rounded-xl border">
                <div className="flex items-center gap-4">
                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Select Examination</Label>
                    <select
                      value={selectedExamId}
                      onChange={(e) => setSelectedExamId(e.target.value)}
                      className="h-9 w-60 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {examinations.map((exam) => (
                        <option key={exam.id} value={exam.id}>
                          {exam.title}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <Label className="text-xs text-muted-foreground">Select Class</Label>
                    <select
                      value={selectedClassId}
                      onChange={(e) => setSelectedClassId(e.target.value)}
                      className="h-9 w-44 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                    >
                      {classes.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleCalculateClassResults(selectedExamId, selectedClassId)}
                    className="flex items-center gap-2"
                  >
                    <Sliders className="w-4 h-4" /> Recalculate Results
                  </Button>
                  <Button size="sm" onClick={loadAnalytics} className="flex items-center gap-2">
                    <RefreshCw className="w-4 h-4" /> Refresh Analytics
                  </Button>
                </div>
              </div>

              {/* Class Performance KPIs */}
              {classAnalytics && (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-xs font-medium text-muted-foreground">Class Average</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="text-2xl font-bold">{classAnalytics.class_average}%</div>
                      <p className="text-xs text-muted-foreground">Across all subjects</p>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-xs font-medium text-muted-foreground">Pass Percentage</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {classAnalytics.pass_percentage}%
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {classAnalytics.passed_students} passed / {classAnalytics.appeared_students} appeared
                      </p>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-xs font-medium text-muted-foreground">Highest Score</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="text-2xl font-bold text-primary">{classAnalytics.highest_score}%</div>
                      <p className="text-xs text-muted-foreground">Class top percentage</p>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-xs font-medium text-muted-foreground">Lowest Score</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="text-2xl font-bold text-rose-500">{classAnalytics.lowest_score}%</div>
                      <p className="text-xs text-muted-foreground">Target for remedial focus</p>
                    </CardContent>
                  </Card>
                </div>
              )}

              {/* Class Toppers Podium */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Trophy className="w-5 h-5 text-amber-500" /> Class Toppers Podium
                  </CardTitle>
                  <CardDescription>Top ranking students based on cumulative percentage across subjects.</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {classToppers.slice(0, 3).map((topper) => {
                      const badgeColor =
                        topper.rank === 1
                          ? "bg-amber-500/15 border-amber-500 text-amber-600 dark:text-amber-400"
                          : topper.rank === 2
                          ? "bg-slate-300/30 border-slate-400 text-slate-700 dark:text-slate-300"
                          : "bg-amber-700/15 border-amber-700 text-amber-800 dark:text-amber-500";

                      return (
                        <div
                          key={topper.student_id}
                          className={`border rounded-xl p-4 text-center space-y-2 relative overflow-hidden ${badgeColor}`}
                        >
                          <div className="text-3xl font-extrabold">#{topper.rank}</div>
                          <h4 className="font-bold text-foreground text-base">{topper.student_name}</h4>
                          <p className="text-xs text-muted-foreground font-mono">{topper.admission_number}</p>
                          <div className="pt-2 border-t border-muted/50 flex justify-around text-xs">
                            <div>
                              <span className="block text-muted-foreground">Score</span>
                              <strong>
                                {topper.total_obtained}/{topper.total_max}
                              </strong>
                            </div>
                            <div>
                              <span className="block text-muted-foreground">Percentage</span>
                              <strong className="text-primary font-bold">{topper.percentage}%</strong>
                            </div>
                            <div>
                              <span className="block text-muted-foreground">Grade</span>
                              <strong>{topper.grade}</strong>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>

              {/* Grade Distribution Chart */}
              {classAnalytics && classAnalytics.grade_distribution && (
                <Card>
                  <CardHeader>
                    <CardTitle>Grade Distribution Frequency</CardTitle>
                    <CardDescription>Number of students falling into each letter grade bracket.</CardDescription>
                  </CardHeader>
                  <CardContent className="h-72">
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart
                        data={Object.entries(classAnalytics.grade_distribution).map(([grade, count]) => ({
                          grade,
                          students: count,
                        }))}
                        margin={{ top: 20, right: 30, left: 0, bottom: 5 }}
                      >
                        <CartesianGrid strokeDasharray="3 3" opacity={0.3} />
                        <XAxis dataKey="grade" />
                        <YAxis allowDecimals={false} />
                        <Tooltip />
                        <Bar dataKey="students" fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>
              )}
            </div>
          )}
        </div>
      </div>

      {/* ========================================== */}
      {/* MODAL 1: CREATE MASTER EXAMINATION */}
      {/* ========================================== */}
      <Modal
        isOpen={showCreateExamModal}
        onClose={() => setShowCreateExamModal(false)}
        title="Create Master Examination"
        description="Define an examination session, evaluation milestone, or board assessment term."
      >
        <form onSubmit={handleCreateExamination} className="space-y-4 pt-2">
          <div className="space-y-1">
            <Label>Examination Title *</Label>
            <Input
              required
              placeholder="e.g. Mid-Term Examination 2026-27"
              value={newExamForm.title}
              onChange={(e) => setNewExamForm({ ...newExamForm, title: e.target.value })}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <Label>Assessment Type *</Label>
              <select
                required
                value={newExamForm.exam_type_id}
                onChange={(e) => setNewExamForm({ ...newExamForm, exam_type_id: e.target.value })}
                className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
              >
                {examTypes.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <Label>Academic Session *</Label>
              <select
                required
                value={newExamForm.academic_session_id}
                onChange={(e) => setNewExamForm({ ...newExamForm, academic_session_id: e.target.value })}
                className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
              >
                {academicSessions.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <Label>Start Date *</Label>
              <Input
                type="date"
                required
                value={newExamForm.start_date}
                onChange={(e) => setNewExamForm({ ...newExamForm, start_date: e.target.value })}
              />
            </div>
            <div className="space-y-1">
              <Label>End Date *</Label>
              <Input
                type="date"
                required
                value={newExamForm.end_date}
                onChange={(e) => setNewExamForm({ ...newExamForm, end_date: e.target.value })}
              />
            </div>
          </div>

          <div className="space-y-1">
            <Label>Description / Instructions</Label>
            <Input
              placeholder="e.g. Mandatory for progression to senior academic session."
              value={newExamForm.description}
              onChange={(e) => setNewExamForm({ ...newExamForm, description: e.target.value })}
            />
          </div>

          <div className="flex justify-end gap-3 pt-3">
            <Button type="button" variant="outline" onClick={() => setShowCreateExamModal(false)}>
              Cancel
            </Button>
            <Button type="submit">Create Examination</Button>
          </div>
        </form>
      </Modal>

      {/* ========================================== */}
      {/* MODAL 2: SCHEDULE SUBJECT EXAM */}
      {/* ========================================== */}
      <Modal
        isOpen={showScheduleModal}
        onClose={() => setShowScheduleModal(false)}
        title="Schedule Subject Examination"
        description="Assign examination date, classroom, timing, and passing thresholds."
      >
        <form onSubmit={handleScheduleSubject} className="space-y-4 pt-2">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <Label>Examination *</Label>
              <select
                required
                value={scheduleForm.examination_id}
                onChange={(e) => setScheduleForm({ ...scheduleForm, examination_id: e.target.value })}
                className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
              >
                {examinations.map((exam) => (
                  <option key={exam.id} value={exam.id}>
                    {exam.title}
                  </option>
                ))}
              </select>
            </div>

            <div className="space-y-1">
              <Label>Target Class *</Label>
              <select
                required
                value={scheduleForm.class_id}
                onChange={(e) => setScheduleForm({ ...scheduleForm, class_id: e.target.value })}
                className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
              >
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="space-y-1">
            <Label>Subject *</Label>
            <select
              required
              value={scheduleForm.subject_id}
              onChange={(e) => setScheduleForm({ ...scheduleForm, subject_id: e.target.value })}
              className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
            >
              {subjects.map((sub) => (
                <option key={sub.id} value={sub.id}>
                  {sub.name} ({sub.code})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div className="space-y-1">
              <Label>Date *</Label>
              <Input
                type="date"
                required
                value={scheduleForm.exam_date}
                onChange={(e) => setScheduleForm({ ...scheduleForm, exam_date: e.target.value })}
              />
            </div>
            <div className="space-y-1">
              <Label>Start Time</Label>
              <Input
                type="time"
                value={scheduleForm.start_time}
                onChange={(e) => setScheduleForm({ ...scheduleForm, start_time: e.target.value })}
              />
            </div>
            <div className="space-y-1">
              <Label>End Time</Label>
              <Input
                type="time"
                value={scheduleForm.end_time}
                onChange={(e) => setScheduleForm({ ...scheduleForm, end_time: e.target.value })}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <div className="space-y-1">
              <Label>Hall / Room</Label>
              <Input
                placeholder="Hall 101"
                value={scheduleForm.room_number}
                onChange={(e) => setScheduleForm({ ...scheduleForm, room_number: e.target.value })}
              />
            </div>
            <div className="space-y-1">
              <Label>Max Marks *</Label>
              <Input
                type="number"
                required
                min={1}
                value={scheduleForm.max_marks}
                onChange={(e) => setScheduleForm({ ...scheduleForm, max_marks: Number(e.target.value) })}
              />
            </div>
            <div className="space-y-1">
              <Label>Pass Marks *</Label>
              <Input
                type="number"
                required
                min={1}
                value={scheduleForm.pass_marks}
                onChange={(e) => setScheduleForm({ ...scheduleForm, pass_marks: Number(e.target.value) })}
              />
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-3">
            <Button type="button" variant="outline" onClick={() => setShowScheduleModal(false)}>
              Cancel
            </Button>
            <Button type="submit">Schedule Subject</Button>
          </div>
        </form>
      </Modal>

      {/* ========================================== */}
      {/* MODAL 3: PUBLISH RESULTS */}
      {/* ========================================== */}
      <Modal
        isOpen={showPublishModal}
        onClose={() => setShowPublishModal(false)}
        title="Publish Examination Results"
        description="Make examination scores and final report cards available on parent and student portals."
      >
        <form onSubmit={handlePublishResults} className="space-y-4 pt-2">
          <div className="space-y-1">
            <Label>Select Target Class (Leave blank for whole exam)</Label>
            <select
              value={publishForm.class_id}
              onChange={(e) => setPublishForm({ ...publishForm, class_id: e.target.value })}
              className="w-full h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
            >
              <option value="">All Classes</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-3 pt-2">
            <div className="flex items-center gap-3">
              <input
                type="checkbox"
                id="parentPortalView"
                checked={publishForm.allow_parent_view}
                onChange={(e) => setPublishForm({ ...publishForm, allow_parent_view: e.target.checked })}
                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
              />
              <label htmlFor="parentPortalView" className="text-sm cursor-pointer">
                Allow Parent Portal View
              </label>
            </div>

            <div className="flex items-center gap-3">
              <input
                type="checkbox"
                id="studentPortalView"
                checked={publishForm.allow_student_view}
                onChange={(e) => setPublishForm({ ...publishForm, allow_student_view: e.target.checked })}
                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
              />
              <label htmlFor="studentPortalView" className="text-sm cursor-pointer">
                Allow Student Portal View
              </label>
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={() => setShowPublishModal(false)}>
              Cancel
            </Button>
            <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700 text-white">
              Confirm Publication
            </Button>
          </div>
        </form>
      </Modal>

      {/* ========================================== */}
      {/* MODAL 4: CSV IMPORT */}
      {/* ========================================== */}
      <Modal
        isOpen={showCsvImportModal}
        onClose={() => setShowCsvImportModal(false)}
        title="Bulk Import Student Marks via CSV"
        description="Upload or paste CSV with enrollment_id, student_id, marks_obtained, is_absent, remarks."
      >
        <form onSubmit={handleImportCsv} className="space-y-4 pt-2">
          <div className="space-y-1">
            <Label>Paste CSV Data *</Label>
            <textarea
              required
              rows={8}
              value={csvContentToImport}
              onChange={(e) => setCsvContentToImport(e.target.value)}
              placeholder="enrollment_id,student_id,admission_number,student_name,roll_number,marks_obtained,is_absent,remarks&#10;uuid-1,uuid-s1,ADM001,John Doe,1,85.5,0,Excellent"
              className="w-full rounded-md border border-input bg-background p-3 font-mono text-xs shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <Button type="button" variant="outline" onClick={() => setShowCsvImportModal(false)}>
              Cancel
            </Button>
            <Button type="submit">Import Marks</Button>
          </div>
        </form>
      </Modal>

      {/* ========================================== */}
      {/* MODAL 5: REQUEST MARKS CORRECTION */}
      {/* ========================================== */}
      <Modal
        isOpen={showCorrectionModal}
        onClose={() => setShowCorrectionModal(false)}
        title="Request Marks Correction"
        description="Audit-trailed score rectification request for administrative review."
      >
        {correctionTarget && (
          <form onSubmit={handleSubmitCorrection} className="space-y-4 pt-2">
            <div className="bg-muted/50 p-3 rounded-lg text-xs space-y-1">
              <div>
                <span className="text-muted-foreground">Student:</span>{" "}
                <strong>{correctionTarget.student_name}</strong>
              </div>
              <div>
                <span className="text-muted-foreground">Current Recorded Marks:</span>{" "}
                <strong>{correctionTarget.current_marks}</strong>
              </div>
            </div>

            <div className="space-y-1">
              <Label>New Marks Value *</Label>
              <Input
                type="number"
                step="0.5"
                required
                value={correctionForm.new_marks}
                onChange={(e) => setCorrectionForm({ ...correctionForm, new_marks: e.target.value })}
              />
            </div>

            <div className="space-y-1">
              <Label>Audit Reason / Justification *</Label>
              <Input
                required
                placeholder="e.g. Recounting error in section B question 3"
                value={correctionForm.reason}
                onChange={(e) => setCorrectionForm({ ...correctionForm, reason: e.target.value })}
              />
            </div>

            <div className="flex justify-end gap-3 pt-3">
              <Button type="button" variant="outline" onClick={() => setShowCorrectionModal(false)}>
                Cancel
              </Button>
              <Button type="submit">Submit for Review</Button>
            </div>
          </form>
        )}
      </Modal>

      {/* ========================================== */}
      {/* MODAL 6: CREATE GRADING SCHEME */}
      {/* ========================================== */}
      <Modal
        isOpen={showGradingSchemeModal}
        onClose={() => setShowGradingSchemeModal(false)}
        title="Create Configurable Grading Scheme"
        description="Build custom grade ranges and grade points without hardcoding board rules."
      >
        <div className="p-4 text-center text-xs text-muted-foreground">
          Grading schemes are pre-seeded with CBSE 9-Point, Percentage, and GPA scales. You can create customized institutional scales via the grading API.
          <div className="pt-4 flex justify-end">
            <Button size="sm" onClick={() => setShowGradingSchemeModal(false)}>
              Got it
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

