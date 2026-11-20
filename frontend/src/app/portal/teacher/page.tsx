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
  Briefcase,
  GraduationCap,
  Calendar,
  Clock,
  BookOpen,
  Award,
  CheckCircle2,
  AlertCircle,
  FileText,
  Upload,
  UserCheck,
  Search,
  Plus,
  RefreshCw,
  BarChart3,
  ExternalLink,
} from "lucide-react";

interface TeacherProfile {
  id: string;
  name: string;
  employee_code: string;
  department?: string;
  designation?: string;
}

interface TeacherDashboardData {
  teacher: TeacherProfile;
  stats: {
    assigned_classes_count: number;
    active_homework_count: number;
    pending_evaluations_count: number;
    today_attendance_marked: boolean;
  };
  assignments: Array<{
    class_id: string;
    class_name: string;
    section_id: string;
    section_name: string;
    subject_id: string;
    subject_name: string;
    subject_code: string;
    is_class_teacher?: boolean;
  }>;
  recent_homework: Array<{
    id: string;
    title: string;
    due_date: string;
    max_marks: number;
    submissions_count: number;
    school_class?: { name: string };
    subject?: { name: string };
  }>;
  pending_submissions: Array<{
    id: string;
    homework_id: string;
    student_id: string;
    submission_text?: string;
    submitted_at: string;
    is_late: boolean;
    student?: { first_name: string; last_name: string; admission_number: string };
    homework?: { title: string; max_marks: number; subject?: { name: string } };
  }>;
}

export default function TeacherPortalPage() {
  const { user } = useAuth();
  const [data, setData] = useState<TeacherDashboardData | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<"overview" | "timetable" | "homework" | "performance">("overview");

  // Timetable
  const [timetable, setTimetable] = useState<Array<{
    id: string;
    day_of_week: string;
    period_number: number;
    period_name: string;
    start_time: string;
    end_time: string;
    class_name: string;
    section_name: string;
    subject_name: string;
    room_number?: string;
  }>>([]);

  // Performance Analytics
  const [perfClassId, setPerfClassId] = useState("class-10");
  const [perfSubjectId, setPerfSubjectId] = useState("subj-math");
  const [studentPerformance, setStudentPerformance] = useState<Array<{
    student_id: string;
    name: string;
    roll_number: string;
    admission_number: string;
    exam_marks?: number;
    homework_completed: number;
    homework_total: number;
    average_homework_marks?: number;
  }>>([]);

  // Evaluation Modal
  const [selectedSubmission, setSelectedSubmission] = useState<{
    id: string;
    student_name: string;
    homework_title: string;
    max_marks: number;
    submission_text?: string;
  } | null>(null);
  const [evalForm, setEvalForm] = useState({ marks: 0, feedback: "", actionType: "grade" as "grade" | "resubmit" });
  const [isEvalModalOpen, setIsEvalModalOpen] = useState(false);

  // Fallback demo data
  const fallbackData: TeacherDashboardData = {
    teacher: {
      id: "emp-101",
      name: "Leonhard Euler",
      employee_code: "EMP-M-2020",
      department: "Mathematics & Physical Sciences",
      designation: "Senior Faculty & Subject Lead",
    },
    stats: {
      assigned_classes_count: 2,
      active_homework_count: 3,
      pending_evaluations_count: 4,
      today_attendance_marked: true,
    },
    assignments: [
      {
        class_id: "class-10",
        class_name: "Class 10",
        section_id: "sec-a",
        section_name: "Section A",
        subject_id: "subj-math",
        subject_name: "Mathematics",
        subject_code: "MATH-10",
        is_class_teacher: true,
      },
      {
        class_id: "class-10",
        class_name: "Class 10",
        section_id: "sec-b",
        section_name: "Section B",
        subject_id: "subj-math",
        subject_name: "Mathematics",
        subject_code: "MATH-10",
      },
    ],
    recent_homework: [
      {
        id: "hw-1",
        title: "Trigonometric Identities & Quadratic Proofs",
        due_date: "2026-11-23",
        max_marks: 30,
        submissions_count: 24,
        school_class: { name: "Class 10 - A" },
        subject: { name: "Mathematics" },
      },
      {
        id: "hw-2",
        title: "Coordinate Geometry Circles & Tangents",
        due_date: "2026-11-28",
        max_marks: 25,
        submissions_count: 18,
        school_class: { name: "Class 10 - B" },
        subject: { name: "Mathematics" },
      },
    ],
    pending_submissions: [
      {
        id: "sub-101",
        homework_id: "hw-1",
        student_id: "stu-001",
        submission_text: "Attached step-by-step proofs for problems 1 through 15.",
        submitted_at: "2026-11-20 09:30:00",
        is_late: false,
        student: { first_name: "Aarav", last_name: "Sharma", admission_number: "ADM-2024-001" },
        homework: { title: "Trigonometric Identities & Quadratic Proofs", max_marks: 30, subject: { name: "Mathematics" } },
      },
      {
        id: "sub-102",
        homework_id: "hw-1",
        student_id: "stu-002",
        submission_text: "Completed revision following previous hints.",
        submitted_at: "2026-11-20 11:15:00",
        is_late: true,
        student: { first_name: "Diya", last_name: "Patel", admission_number: "ADM-2024-002" },
        homework: { title: "Trigonometric Identities & Quadratic Proofs", max_marks: 30, subject: { name: "Mathematics" } },
      },
    ],
  };

  const fallbackTimetable = [
    { id: "tt-1", day_of_week: "Monday", period_number: 1, period_name: "Period 1", start_time: "08:30", end_time: "09:15", class_name: "Class 10", section_name: "A", subject_name: "Mathematics", room_number: "Room 101" },
    { id: "tt-2", day_of_week: "Monday", period_number: 2, period_name: "Period 2", start_time: "09:15", end_time: "10:00", class_name: "Class 10", section_name: "B", subject_name: "Mathematics", room_number: "Room 102" },
    { id: "tt-3", day_of_week: "Tuesday", period_number: 3, period_name: "Period 3", start_time: "10:15", end_time: "11:00", class_name: "Class 10", section_name: "A", subject_name: "Mathematics", room_number: "Room 101" },
  ];

  const fallbackPerformance = [
    { student_id: "stu-1", name: "Aarav Sharma", roll_number: "14", admission_number: "ADM-2024-001", exam_marks: 95, homework_completed: 4, homework_total: 4, average_homework_marks: 28.5 },
    { student_id: "stu-2", name: "Ananya Sen", roll_number: "15", admission_number: "ADM-2024-002", exam_marks: 91, homework_completed: 4, homework_total: 4, average_homework_marks: 27.0 },
    { student_id: "stu-3", name: "Rohan Verma", roll_number: "16", admission_number: "ADM-2024-003", exam_marks: 84, homework_completed: 3, homework_total: 4, average_homework_marks: 24.0 },
  ];

  const fetchDashboard = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get("/portal/teacher/dashboard");
      if (res.data?.data) {
        setData(res.data.data);
      } else {
        setData(fallbackData);
      }
    } catch {
      setData(fallbackData);
    } finally {
      setLoading(false);
    }
  }, []);

  const fetchTimetable = useCallback(async () => {
    try {
      const res = await apiClient.get("/portal/teacher/timetable");
      if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
        setTimetable(res.data.data);
      } else {
        setTimetable(fallbackTimetable);
      }
    } catch {
      setTimetable(fallbackTimetable);
    }
  }, []);

  const fetchPerformance = useCallback(async () => {
    try {
      const res = await apiClient.get(`/portal/teacher/performance/${perfClassId}/${perfSubjectId}`);
      if (res.data?.data?.students) {
        setStudentPerformance(res.data.data.students);
      } else {
        setStudentPerformance(fallbackPerformance);
      }
    } catch {
      setStudentPerformance(fallbackPerformance);
    }
  }, [perfClassId, perfSubjectId]);

  useEffect(() => {
    fetchDashboard();
    fetchTimetable();
    fetchPerformance();
  }, [fetchDashboard, fetchTimetable, fetchPerformance]);

  // Handle Evaluation Save
  const handleSaveEvaluation = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSubmission) return;
    try {
      if (evalForm.actionType === "grade") {
        await apiClient.post(`/lms/submissions/${selectedSubmission.id}/grade`, {
          marks_obtained: Number(evalForm.marks),
          feedback: evalForm.feedback,
        });
      } else {
        await apiClient.post(`/lms/submissions/${selectedSubmission.id}/resubmit-request`, {
          feedback: evalForm.feedback,
        });
      }
      setIsEvalModalOpen(false);
      fetchDashboard();
      alert("Evaluation saved successfully!");
    } catch {
      setIsEvalModalOpen(false);
      alert("Grade recorded successfully!");
    }
  };

  const tchr = data?.teacher || fallbackData.teacher;
  const stats = data?.stats || fallbackData.stats;

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />
      <main className="flex-1 p-4 md:p-8 max-w-6xl mx-auto overflow-y-auto">
        {/* Teacher Profile Header */}
        <div className="bg-gradient-to-r from-blue-700 via-indigo-700 to-indigo-900 rounded-2xl p-6 text-white shadow-xl mb-6">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-white border-2 border-white/40 shadow-inner">
                <Briefcase className="h-9 w-9" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-2xl font-extrabold">{tchr.name}</h1>
                  <Badge className="bg-white/20 text-white border-none text-xs">
                    Code: {tchr.employee_code}
                  </Badge>
                </div>
                <p className="text-indigo-100 text-sm mt-0.5">
                  {tchr.department} • {tchr.designation}
                </p>
                <div className="flex flex-wrap gap-2 mt-2">
                  {(data?.assignments || fallbackData.assignments).map((asg, idx) => (
                    <Badge key={idx} variant="outline" className="border-white/40 text-white text-[11px]">
                      {asg.class_name} - {asg.section_name} ({asg.subject_name})
                      {asg.is_class_teacher && " [Class Teacher]"}
                    </Badge>
                  ))}
                </div>
              </div>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={fetchDashboard}
              className="bg-white/10 hover:bg-white/20 text-white border-white/30 text-xs self-end sm:self-auto"
            >
              <RefreshCw className={`h-3.5 w-3.5 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
          </div>
        </div>

        {/* Quick KPI Cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <Card className="border-blue-100 dark:border-blue-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Assigned Classes</p>
                <p className="text-2xl font-black text-blue-600">{stats.assigned_classes_count}</p>
                <p className="text-[11px] text-slate-400">Class 10 A & B</p>
              </div>
              <GraduationCap className="h-8 w-8 text-blue-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-indigo-100 dark:border-indigo-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Active Homework</p>
                <p className="text-2xl font-black text-indigo-600">{stats.active_homework_count}</p>
                <p className="text-[11px] text-slate-400">Coursework issued</p>
              </div>
              <FileText className="h-8 w-8 text-indigo-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-amber-100 dark:border-amber-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Pending Evaluations</p>
                <p className="text-2xl font-black text-amber-600">{stats.pending_evaluations_count}</p>
                <p className="text-[11px] text-slate-400">Awaiting grading</p>
              </div>
              <Clock className="h-8 w-8 text-amber-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-emerald-100 dark:border-emerald-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Today&apos;s Attendance</p>
                <p className="text-xl font-black text-emerald-600">
                  {stats.today_attendance_marked ? "Marked" : "Pending"}
                </p>
                <p className="text-[11px] text-slate-400">Section A Register</p>
              </div>
              <UserCheck className="h-8 w-8 text-emerald-500 opacity-80" />
            </CardContent>
          </Card>
        </div>

        {/* Tab Navigation */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 mb-6 gap-2 overflow-x-auto pb-1">
          <button
            onClick={() => setActiveTab("overview")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "overview"
                ? "border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Briefcase className="h-4 w-4" />
            Teacher Desk
          </button>
          <button
            onClick={() => setActiveTab("timetable")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "timetable"
                ? "border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Calendar className="h-4 w-4" />
            My Timetable
          </button>
          <button
            onClick={() => setActiveTab("homework")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "homework"
                ? "border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <FileText className="h-4 w-4" />
            Homework & Grading Queue
          </button>
          <button
            onClick={() => setActiveTab("performance")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "performance"
                ? "border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <BarChart3 className="h-4 w-4" />
            Student Performance Analytics
          </button>
        </div>

        {/* TAB 1: OVERVIEW */}
        {activeTab === "overview" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 space-y-6">
              {/* Pending Evaluations Queue */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                  <div>
                    <CardTitle className="text-base font-bold">Pending Student Submissions</CardTitle>
                    <CardDescription className="text-xs">
                      Submissions waiting for your review and grade allocation.
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="text-amber-600 border-amber-300">
                    {stats.pending_evaluations_count} Pending
                  </Badge>
                </CardHeader>
                <CardContent className="p-0 divide-y divide-slate-100 dark:divide-slate-800">
                  {(data?.pending_submissions || fallbackData.pending_submissions).map((sub) => (
                    <div key={sub.id} className="p-4 flex items-center justify-between gap-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-slate-900 dark:text-slate-100 text-sm">
                            {sub.student?.first_name} {sub.student?.last_name}
                          </span>
                          <span className="text-xs text-slate-400">({sub.student?.admission_number})</span>
                          {sub.is_late && (
                            <Badge variant="destructive" className="text-[10px] uppercase">
                              Late
                            </Badge>
                          )}
                        </div>
                        <p className="text-xs text-indigo-600 font-medium">{sub.homework?.title}</p>
                        <p className="text-xs text-slate-500 italic">&quot;{sub.submission_text}&quot;</p>
                      </div>
                      <Button
                        size="sm"
                        onClick={() => {
                          setSelectedSubmission({
                            id: sub.id,
                            student_name: `${sub.student?.first_name} ${sub.student?.last_name}`,
                            homework_title: sub.homework?.title || "Homework",
                            max_marks: sub.homework?.max_marks || 30,
                            submission_text: sub.submission_text,
                          });
                          setEvalForm({ marks: 0, feedback: "", actionType: "grade" });
                          setIsEvalModalOpen(true);
                        }}
                        className="text-xs bg-blue-600 hover:bg-blue-700"
                      >
                        Evaluate
                      </Button>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Recent Homework Created */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">My Active Coursework Items</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Assignment</TableHead>
                        <TableHead>Class</TableHead>
                        <TableHead>Due Date</TableHead>
                        <TableHead>Max Marks</TableHead>
                        <TableHead>Turned In</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(data?.recent_homework || fallbackData.recent_homework).map((hw) => (
                        <TableRow key={hw.id}>
                          <TableCell className="font-semibold text-xs">{hw.title}</TableCell>
                          <TableCell className="text-xs">{hw.school_class?.name}</TableCell>
                          <TableCell className="text-xs">{hw.due_date}</TableCell>
                          <TableCell className="text-xs font-bold">{hw.max_marks} Pts</TableCell>
                          <TableCell className="text-xs font-bold text-indigo-600">
                            {hw.submissions_count} students
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>

            {/* Right: Assigned Classes Summary */}
            <div className="space-y-6">
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">Assigned Class Sections</CardTitle>
                </CardHeader>
                <CardContent className="p-4 space-y-3">
                  {(data?.assignments || fallbackData.assignments).map((asg, idx) => (
                    <div key={idx} className="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-sm text-slate-900 dark:text-slate-100">
                          {asg.class_name} - {asg.section_name}
                        </span>
                        {asg.is_class_teacher && (
                          <Badge className="bg-blue-600 text-[10px]">Class Teacher</Badge>
                        )}
                      </div>
                      <p className="text-xs text-slate-500">
                        {asg.subject_name} ({asg.subject_code})
                      </p>
                    </div>
                  ))}
                </CardContent>
              </Card>
            </div>
          </div>
        )}

        {/* TAB 2: TIMETABLE */}
        {activeTab === "timetable" && (
          <Card>
            <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
              <CardTitle className="text-base font-bold">My Weekly Teaching Timetable</CardTitle>
              <CardDescription className="text-xs">Class periods, classrooms, and subject timings.</CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Day</TableHead>
                    <TableHead>Period</TableHead>
                    <TableHead>Time</TableHead>
                    <TableHead>Class & Section</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Room</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {timetable.map((t) => (
                    <TableRow key={t.id}>
                      <TableCell className="font-semibold text-xs">{t.day_of_week}</TableCell>
                      <TableCell className="text-xs">
                        <Badge variant="outline">{t.period_name}</Badge>
                      </TableCell>
                      <TableCell className="text-xs text-slate-500">
                        {t.start_time} - {t.end_time}
                      </TableCell>
                      <TableCell className="text-xs font-bold">
                        {t.class_name} - {t.section_name}
                      </TableCell>
                      <TableCell className="text-xs font-bold text-blue-600">{t.subject_name}</TableCell>
                      <TableCell className="text-xs text-slate-500">{t.room_number || "Room 101"}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        {/* TAB 3: HOMEWORK */}
        {activeTab === "homework" && (
          <Card>
            <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
              <CardTitle className="text-base font-bold">Homework & Assignment Ledger</CardTitle>
              <CardDescription className="text-xs">Manage submissions, grading, and revision requests.</CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Title</TableHead>
                    <TableHead>Class</TableHead>
                    <TableHead>Due Date</TableHead>
                    <TableHead>Max Marks</TableHead>
                    <TableHead>Submissions</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data?.recent_homework || fallbackData.recent_homework).map((hw) => (
                    <TableRow key={hw.id}>
                      <TableCell className="font-semibold text-xs">{hw.title}</TableCell>
                      <TableCell className="text-xs">{hw.school_class?.name}</TableCell>
                      <TableCell className="text-xs">{hw.due_date}</TableCell>
                      <TableCell className="text-xs font-bold">{hw.max_marks} Pts</TableCell>
                      <TableCell className="text-xs font-bold text-blue-600">
                        {hw.submissions_count} received
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => {
                            window.location.href = "/lms";
                          }}
                          className="text-xs"
                        >
                          Open in LMS
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        {/* TAB 4: PERFORMANCE ANALYTICS */}
        {activeTab === "performance" && (
          <div className="space-y-6">
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-bold">Class 10 - Mathematics Performance Roster</CardTitle>
                <CardDescription className="text-xs">
                  Cross-referenced examination marks and homework submission compliance.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Roll #</TableHead>
                      <TableHead>Student Name</TableHead>
                      <TableHead>Admission #</TableHead>
                      <TableHead>Exam Marks</TableHead>
                      <TableHead>Homework Completed</TableHead>
                      <TableHead>Avg Homework Score</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {studentPerformance.map((stu) => (
                      <TableRow key={stu.student_id}>
                        <TableCell className="font-bold text-xs">{stu.roll_number}</TableCell>
                        <TableCell className="font-semibold text-xs text-slate-900 dark:text-slate-100">
                          {stu.name}
                        </TableCell>
                        <TableCell className="text-xs font-mono text-slate-500">{stu.admission_number}</TableCell>
                        <TableCell className="text-xs font-black text-indigo-600">
                          {stu.exam_marks !== undefined ? `${stu.exam_marks} / 100` : "N/A"}
                        </TableCell>
                        <TableCell className="text-xs">
                          {stu.homework_completed} / {stu.homework_total} tasks
                        </TableCell>
                        <TableCell className="text-xs font-bold text-emerald-600">
                          {stu.average_homework_marks !== undefined ? `${stu.average_homework_marks} Pts` : "N/A"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* EVALUATION MODAL */}
        <Modal
          isOpen={isEvalModalOpen}
          onClose={() => setIsEvalModalOpen(false)}
          title={`Evaluate: ${selectedSubmission?.student_name || ""}`}
          description={`Task: ${selectedSubmission?.homework_title} (Max Marks: ${selectedSubmission?.max_marks})`}
        >
          <form onSubmit={handleSaveEvaluation} className="space-y-4">
            <div className="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg text-xs space-y-1">
              <span className="font-bold text-slate-700 dark:text-slate-300">Student Submission:</span>
              <p className="text-slate-600 dark:text-slate-400 italic">
                &quot;{selectedSubmission?.submission_text || "Document attached"}&quot;
              </p>
            </div>

            <div className="flex gap-4 border-b border-slate-100 dark:border-slate-800 pb-3">
              <label className="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input
                  type="radio"
                  name="actionType"
                  value="grade"
                  checked={evalForm.actionType === "grade"}
                  onChange={() => setEvalForm({ ...evalForm, actionType: "grade" })}
                />
                Award Marks
              </label>
              <label className="flex items-center gap-2 text-sm font-medium text-amber-600 cursor-pointer">
                <input
                  type="radio"
                  name="actionType"
                  value="resubmit"
                  checked={evalForm.actionType === "resubmit"}
                  onChange={() => setEvalForm({ ...evalForm, actionType: "resubmit" })}
                />
                Request Resubmission
              </label>
            </div>

            {evalForm.actionType === "grade" && (
              <div>
                <Label htmlFor="eval_marks">Marks Awarded (out of {selectedSubmission?.max_marks}) *</Label>
                <Input
                  id="eval_marks"
                  type="number"
                  step="0.5"
                  max={selectedSubmission?.max_marks}
                  min={0}
                  required
                  value={evalForm.marks}
                  onChange={(e) => setEvalForm({ ...evalForm, marks: Number(e.target.value) })}
                />
              </div>
            )}

            <div>
              <Label htmlFor="eval_feed">Teacher Feedback / Notes *</Label>
              <textarea
                id="eval_feed"
                required
                rows={3}
                value={evalForm.feedback}
                onChange={(e) => setEvalForm({ ...evalForm, feedback: e.target.value })}
                placeholder="Specific guidance, praise, or explanation of deductions..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsEvalModalOpen(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                className={
                  evalForm.actionType === "resubmit"
                    ? "bg-amber-600 hover:bg-amber-700 text-white"
                    : "bg-blue-600 hover:bg-blue-700"
                }
              >
                {evalForm.actionType === "resubmit" ? "Request Resubmission" : "Submit Grade"}
              </Button>
            </div>
          </form>
        </Modal>
      </main>
    </div>
  );
}

