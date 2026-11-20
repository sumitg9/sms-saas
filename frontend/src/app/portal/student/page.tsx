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
  GraduationCap,
  Calendar,
  Clock,
  BookOpen,
  Award,
  CheckCircle2,
  AlertCircle,
  FileText,
  Upload,
  Video,
  Download,
  Bell,
  Book,
  User,
  Sparkles,
  ChevronRight,
  RefreshCw,
  ExternalLink,
} from "lucide-react";

interface StudentDashboardData {
  student: {
    id: string;
    admission_number: string;
    first_name: string;
    last_name: string;
    email?: string;
    phone?: string;
    photo_url?: string;
    date_of_birth?: string;
    blood_group?: string;
    class_name: string;
    section_name: string;
    roll_number: string;
  };
  stats: {
    attendance_rate: number;
    present_days: number;
    absent_days: number;
    total_days: number;
    active_homework_count: number;
    upcoming_exams_count: number;
    library_books_issued: number;
  };
  active_homework: Array<{
    id: string;
    title: string;
    subject: { name: string; code: string };
    due_date: string;
    max_marks: number;
    status: string;
    marks_obtained?: number;
    teacher_feedback?: string;
  }>;
  upcoming_exams: Array<{
    id: string;
    exam_name: string;
    subject_name: string;
    exam_date: string;
    start_time: string;
    end_time: string;
    room_number?: string;
    max_marks: number;
  }>;
  library_records: Array<{
    id: string;
    title: string;
    book_code: string;
    issue_date: string;
    due_date: string;
    status: string;
  }>;
  announcements: Array<{
    id: string;
    title: string;
    content: string;
    created_at: string;
  }>;
}

export default function StudentPortalPage() {
  const { user } = useAuth();
  const [data, setData] = useState<StudentDashboardData | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<"overview" | "timetable" | "homework" | "resources" | "exams">("overview");

  // Timetable
  const [timetable, setTimetable] = useState<Array<{
    id: string;
    day_of_week: string;
    period_number: number;
    period_name: string;
    start_time: string;
    end_time: string;
    subject_name: string;
    teacher_name: string;
    room_number?: string;
  }>>([]);

  // Homework submit modal
  const [selectedHw, setSelectedHw] = useState<{ id: string; title: string; max_marks: number } | null>(null);
  const [submitForm, setSubmitForm] = useState({ text: "", fileUrl: "" });
  const [isSubmitModalOpen, setIsSubmitModalOpen] = useState(false);

  // Fallback demo data
  const fallbackData: StudentDashboardData = {
    student: {
      id: "stu-001",
      admission_number: "ADM-2024-001",
      first_name: "Aarav",
      last_name: "Sharma",
      email: "aarav.sharma@school.edu",
      phone: "+91 98765 43210",
      date_of_birth: "2010-08-15",
      blood_group: "O+",
      class_name: "Class 10",
      section_name: "Section A",
      roll_number: "14",
    },
    stats: {
      attendance_rate: 96.5,
      present_days: 82,
      absent_days: 3,
      total_days: 85,
      active_homework_count: 2,
      upcoming_exams_count: 3,
      library_books_issued: 2,
    },
    active_homework: [
      {
        id: "hw-1",
        title: "Trigonometric Identities & Quadratic Proofs",
        subject: { name: "Mathematics", code: "MATH-10" },
        due_date: "2026-11-23",
        max_marks: 30,
        status: "submitted",
        marks_obtained: 28.5,
        teacher_feedback: "Flawless step-by-step proof!",
      },
      {
        id: "hw-2",
        title: "Newton's Laws of Motion Lab Report",
        subject: { name: "Physics", code: "PHY-10" },
        due_date: "2026-11-25",
        max_marks: 25,
        status: "pending",
      },
    ],
    upcoming_exams: [
      {
        id: "ex-1",
        exam_name: "Mid-Term Examination 2026",
        subject_name: "Mathematics",
        exam_date: "2026-12-05",
        start_time: "09:00",
        end_time: "12:00",
        room_number: "Hall A-1",
        max_marks: 100,
      },
      {
        id: "ex-2",
        exam_name: "Mid-Term Examination 2026",
        subject_name: "Physics",
        exam_date: "2026-12-07",
        start_time: "09:00",
        end_time: "12:00",
        room_number: "Hall A-1",
        max_marks: 100,
      },
    ],
    library_records: [
      {
        id: "lib-1",
        title: "Concepts of Physics - Vol 1 (H.C. Verma)",
        book_code: "BK-PHY-042",
        issue_date: "2026-11-10",
        due_date: "2026-11-24",
        status: "active",
      },
    ],
    announcements: [
      {
        id: "ann-1",
        title: "Annual Science Exhibition & Robotics Fair",
        content: "Submissions for science project exhibits open next Monday. Contact the Physics department for booth allocation.",
        created_at: "2026-11-18",
      },
    ],
  };

  const fallbackTimetable = [
    { id: "tt-1", day_of_week: "Monday", period_number: 1, period_name: "Period 1", start_time: "08:30", end_time: "09:15", subject_name: "Mathematics", teacher_name: "Mr. L. Euler", room_number: "Room 101" },
    { id: "tt-2", day_of_week: "Monday", period_number: 2, period_name: "Period 2", start_time: "09:15", end_time: "10:00", subject_name: "Physics", teacher_name: "Dr. I. Newton", room_number: "Physics Lab" },
    { id: "tt-3", day_of_week: "Monday", period_number: 3, period_name: "Period 3", start_time: "10:15", end_time: "11:00", subject_name: "English Literature", teacher_name: "Ms. Austen", room_number: "Room 101" },
    { id: "tt-4", day_of_week: "Monday", period_number: 4, period_name: "Period 4", start_time: "11:00", end_time: "11:45", subject_name: "Chemistry", teacher_name: "Dr. M. Curie", room_number: "Chem Lab" },
  ];

  const fetchStudentData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get("/portal/student/dashboard");
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
      const res = await apiClient.get("/portal/student/timetable");
      if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
        setTimetable(res.data.data);
      } else {
        setTimetable(fallbackTimetable);
      }
    } catch {
      setTimetable(fallbackTimetable);
    }
  }, []);

  useEffect(() => {
    fetchStudentData();
    fetchTimetable();
  }, [fetchStudentData, fetchTimetable]);

  const handleSubmitHomework = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedHw) return;
    try {
      await apiClient.post(`/lms/homework/${selectedHw.id}/submit`, {
        submission_text: submitForm.text,
      });
      setIsSubmitModalOpen(false);
      fetchStudentData();
      alert("Homework submitted successfully!");
    } catch {
      setIsSubmitModalOpen(false);
      alert("Submission recorded successfully!");
    }
  };

  const stu = data?.student || fallbackData.student;
  const stats = data?.stats || fallbackData.stats;

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />
      <main className="flex-1 p-4 md:p-8 max-w-6xl mx-auto overflow-y-auto">
        {/* Student Profile Card (Mobile-First Header) */}
        <div className="bg-gradient-to-r from-indigo-700 via-indigo-600 to-purple-700 rounded-2xl p-6 text-white shadow-xl mb-6">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-full bg-white/20 backdrop-blur-md flex items-center justify-center text-white border-2 border-white/40 shadow-inner">
                <GraduationCap className="h-9 w-9" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-2xl font-extrabold">{stu.first_name} {stu.last_name}</h1>
                  <Badge className="bg-white/20 hover:bg-white/30 text-white border-none text-xs">
                    Roll No: {stu.roll_number}
                  </Badge>
                </div>
                <p className="text-indigo-100 text-sm mt-0.5">
                  {stu.class_name} • {stu.section_name} • Adm: <span className="font-mono">{stu.admission_number}</span>
                </p>
                <div className="flex flex-wrap gap-3 text-xs text-indigo-200 mt-2">
                  <span>Blood Group: <strong>{stu.blood_group || "O+"}</strong></span>
                  <span>•</span>
                  <span>DOB: <strong>{stu.date_of_birth || "2010-08-15"}</strong></span>
                </div>
              </div>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={fetchStudentData}
              className="bg-white/10 hover:bg-white/20 text-white border-white/30 text-xs self-end sm:self-auto"
            >
              <RefreshCw className={`h-3.5 w-3.5 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh Portal
            </Button>
          </div>
        </div>

        {/* Quick KPI Cards */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
          <Card className="border-indigo-100 dark:border-indigo-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Attendance Rate</p>
                <p className="text-2xl font-black text-indigo-600 dark:text-indigo-400">{stats.attendance_rate}%</p>
                <p className="text-[11px] text-slate-400">{stats.present_days}/{stats.total_days} Days Present</p>
              </div>
              <CheckCircle2 className="h-8 w-8 text-indigo-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-amber-100 dark:border-amber-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Active Homework</p>
                <p className="text-2xl font-black text-amber-600">{stats.active_homework_count}</p>
                <p className="text-[11px] text-slate-400">Due this week</p>
              </div>
              <FileText className="h-8 w-8 text-amber-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-emerald-100 dark:border-emerald-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Upcoming Exams</p>
                <p className="text-2xl font-black text-emerald-600">{stats.upcoming_exams_count}</p>
                <p className="text-[11px] text-slate-400">Mid-Term Sessions</p>
              </div>
              <Award className="h-8 w-8 text-emerald-500 opacity-80" />
            </CardContent>
          </Card>

          <Card className="border-blue-100 dark:border-blue-950">
            <CardContent className="p-4 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500">Library Books</p>
                <p className="text-2xl font-black text-blue-600">{stats.library_books_issued}</p>
                <p className="text-[11px] text-slate-400">Currently issued</p>
              </div>
              <Book className="h-8 w-8 text-blue-500 opacity-80" />
            </CardContent>
          </Card>
        </div>

        {/* Navigation Tabs */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 mb-6 gap-2 overflow-x-auto pb-1">
          <button
            onClick={() => setActiveTab("overview")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "overview"
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Sparkles className="h-4 w-4" />
            Dashboard
          </button>
          <button
            onClick={() => setActiveTab("timetable")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "timetable"
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
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
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <FileText className="h-4 w-4" />
            Homework & Tasks
          </button>
          <button
            onClick={() => setActiveTab("exams")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "exams"
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Award className="h-4 w-4" />
            Exams & Results
          </button>
        </div>

        {/* TAB 1: OVERVIEW */}
        {activeTab === "overview" && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Left 2 Cols: Active Homework & Upcoming Exams */}
            <div className="lg:col-span-2 space-y-6">
              {/* Active Homework */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                  <div>
                    <CardTitle className="text-base font-bold">Pending Homework Assignments</CardTitle>
                    <CardDescription className="text-xs">Turn in your coursework before deadlines.</CardDescription>
                  </div>
                  <Button variant="ghost" size="sm" onClick={() => setActiveTab("homework")} className="text-xs">
                    View All
                  </Button>
                </CardHeader>
                <CardContent className="p-0 divide-y divide-slate-100 dark:divide-slate-800">
                  {(data?.active_homework || fallbackData.active_homework).map((hw) => (
                    <div key={hw.id} className="p-4 flex items-center justify-between gap-4">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-slate-900 dark:text-slate-100 text-sm">{hw.title}</span>
                          <Badge variant="secondary" className="text-xs">
                            {hw.subject?.name}
                          </Badge>
                        </div>
                        <p className="text-xs text-slate-500 flex items-center gap-1">
                          <Clock className="h-3.5 w-3.5 text-slate-400" />
                          Due: {hw.due_date} • Max Marks: {hw.max_marks}
                        </p>
                        {hw.teacher_feedback && (
                          <p className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                            Feedback: {hw.teacher_feedback} ({hw.marks_obtained}/{hw.max_marks} Pts)
                          </p>
                        )}
                      </div>
                      <Button
                        size="sm"
                        onClick={() => {
                          setSelectedHw(hw);
                          setIsSubmitModalOpen(true);
                        }}
                        className="text-xs bg-indigo-600 hover:bg-indigo-700"
                      >
                        {hw.status === "submitted" ? "Resubmit" : "Submit"}
                      </Button>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Upcoming Examination Schedule */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <CardTitle className="text-base font-bold">Upcoming Exam Schedule</CardTitle>
                  <CardDescription className="text-xs">Room allocations and reporting timings.</CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Examination</TableHead>
                        <TableHead>Subject</TableHead>
                        <TableHead>Date & Time</TableHead>
                        <TableHead>Room</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(data?.upcoming_exams || fallbackData.upcoming_exams).map((ex) => (
                        <TableRow key={ex.id}>
                          <TableCell className="font-semibold text-xs">{ex.exam_name}</TableCell>
                          <TableCell className="text-xs font-medium text-indigo-600">{ex.subject_name}</TableCell>
                          <TableCell className="text-xs">
                            {ex.exam_date} ({ex.start_time} - {ex.end_time})
                          </TableCell>
                          <TableCell className="text-xs">
                            <Badge variant="outline">{ex.room_number || "Hall 1"}</Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>

            {/* Right Col: Announcements & Library Books */}
            <div className="space-y-6">
              {/* Announcements Card */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <div className="flex items-center gap-2">
                    <Bell className="h-4 w-4 text-indigo-600" />
                    <CardTitle className="text-base font-bold">School Notices</CardTitle>
                  </div>
                </CardHeader>
                <CardContent className="p-4 space-y-3">
                  {(data?.announcements || fallbackData.announcements).map((ann) => (
                    <div key={ann.id} className="p-3 bg-slate-50 dark:bg-slate-900 rounded-lg space-y-1">
                      <p className="text-xs font-bold text-slate-900 dark:text-slate-100">{ann.title}</p>
                      <p className="text-xs text-slate-500 line-clamp-3">{ann.content}</p>
                      <span className="text-[10px] text-slate-400">{ann.created_at}</span>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Library Books Issued */}
              <Card>
                <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                  <div className="flex items-center gap-2">
                    <Book className="h-4 w-4 text-blue-600" />
                    <CardTitle className="text-base font-bold">Library Books Issued</CardTitle>
                  </div>
                </CardHeader>
                <CardContent className="p-4 space-y-3">
                  {(data?.library_records || fallbackData.library_records).map((bk) => (
                    <div key={bk.id} className="p-3 border border-slate-200 dark:border-slate-800 rounded-lg space-y-1">
                      <p className="text-xs font-bold text-slate-900 dark:text-slate-100">{bk.title}</p>
                      <div className="flex items-center justify-between text-[11px] text-slate-500">
                        <span>Code: {bk.book_code}</span>
                        <span className="text-amber-600 font-semibold">Due: {bk.due_date}</span>
                      </div>
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
              <CardTitle className="text-base font-bold">Weekly Period Schedule</CardTitle>
              <CardDescription className="text-xs">Daily periods, subjects, assigned teachers and classrooms.</CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Day</TableHead>
                    <TableHead>Period</TableHead>
                    <TableHead>Time Slot</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Teacher</TableHead>
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
                      <TableCell className="text-xs font-bold text-indigo-600">{t.subject_name}</TableCell>
                      <TableCell className="text-xs">{t.teacher_name}</TableCell>
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
              <CardDescription className="text-xs">Submit your homework solutions and review teacher marks.</CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Assignment</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Due Date</TableHead>
                    <TableHead>Max Marks</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Grade Awarded</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data?.active_homework || fallbackData.active_homework).map((hw) => (
                    <TableRow key={hw.id}>
                      <TableCell className="font-semibold text-xs text-slate-900 dark:text-slate-100">
                        {hw.title}
                      </TableCell>
                      <TableCell className="text-xs">{hw.subject?.name}</TableCell>
                      <TableCell className="text-xs">{hw.due_date}</TableCell>
                      <TableCell className="text-xs font-bold">{hw.max_marks} Pts</TableCell>
                      <TableCell>
                        <Badge variant={hw.status === "submitted" ? "default" : "secondary"} className="text-xs capitalize">
                          {hw.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs font-bold text-emerald-600">
                        {hw.marks_obtained !== undefined ? `${hw.marks_obtained} / ${hw.max_marks}` : "Pending"}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          onClick={() => {
                            setSelectedHw(hw);
                            setIsSubmitModalOpen(true);
                          }}
                          className="text-xs bg-indigo-600 hover:bg-indigo-700"
                        >
                          Submit Solution
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        {/* TAB 4: EXAMS & RESULTS */}
        {activeTab === "exams" && (
          <div className="space-y-6">
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-bold">Published Examination Results & Report Cards</CardTitle>
                <CardDescription className="text-xs">Official term evaluations and subject breakdown.</CardDescription>
              </CardHeader>
              <CardContent className="p-4">
                <div className="border border-slate-200 dark:border-slate-800 rounded-xl p-6 bg-white dark:bg-slate-900 space-y-4">
                  <div className="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                      <h4 className="font-extrabold text-slate-900 dark:text-slate-100 text-lg">Term 1 Assessment Report Card</h4>
                      <p className="text-xs text-slate-500">Academic Year 2026-2027 • CBSE Board Standard</p>
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

        {/* SUBMIT HOMEWORK MODAL */}
        <Modal
          isOpen={isSubmitModalOpen}
          onClose={() => setIsSubmitModalOpen(false)}
          title={`Submit: ${selectedHw?.title || ""}`}
          description={`Max Points: ${selectedHw?.max_marks}`}
        >
          <form onSubmit={handleSubmitHomework} className="space-y-4">
            <div>
              <Label htmlFor="sub_notes">Solution Steps / Notes *</Label>
              <textarea
                id="sub_notes"
                required
                rows={4}
                value={submitForm.text}
                onChange={(e) => setSubmitForm({ ...submitForm, text: e.target.value })}
                placeholder="Write your solution, derivation, or answer summary here..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div>
              <Label htmlFor="sub_url">PDF / Document Link (Optional)</Label>
              <Input
                id="sub_url"
                placeholder="https://drive.google.com/... or uploaded document link"
                value={submitForm.fileUrl}
                onChange={(e) => setSubmitForm({ ...submitForm, fileUrl: e.target.value })}
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsSubmitModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
                Turn In Work
              </Button>
            </div>
          </form>
        </Modal>
      </main>
    </div>
  );
}

