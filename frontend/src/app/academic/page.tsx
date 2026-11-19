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
  GraduationCap,
  Calendar,
  BookOpen,
  Clock,
  Layers,
  Plus,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Loader2,
  CalendarDays,
  UserCheck,
  Check,
  RefreshCw,
  Building,
  School,
  Shuffle,
  Users,
} from "lucide-react";

interface AcademicSessionItem {
  id: string;
  name: string;
  start_date: string;
  end_date: string;
  is_current: boolean;
  enrollments_count?: number;
  timetables_count?: number;
}

interface BoardItem {
  id: string;
  code: string;
  name: string;
  description?: string;
  is_active: boolean;
}

interface SectionItem {
  id: string;
  name: string;
  room_number?: string;
  max_capacity: number;
  is_active: boolean;
  class_teachers?: Array<{
    id: string;
    is_primary: boolean;
    employee?: {
      id: string;
      first_name: string;
      last_name: string;
      employee_code: string;
    };
  }>;
}

interface ClassItem {
  id: string;
  name: string;
  code: string;
  order_index: number;
  is_active: boolean;
  board?: BoardItem;
  sections?: SectionItem[];
}

interface SubjectItem {
  id: string;
  name: string;
  code: string;
  type: string;
  credit_hours: string | number;
  is_active: boolean;
  class_subjects_count?: number;
}

interface CalendarEventItem {
  id: string;
  title: string;
  event_type: string;
  start_date: string;
  end_date: string;
  is_holiday: boolean;
  description?: string;
}

interface TimetablePeriodItem {
  id: string;
  period_number: number;
  period_name: string;
  start_time: string;
  end_time: string;
  is_break: boolean;
}

interface TimetableEntryItem {
  id: string;
  period_id: string;
  class_id: string;
  section_id: string;
  subject_id: string;
  employee_id: string;
  day_of_week: number;
  room_number?: string;
  school_class?: { name: string; code: string };
  section?: { name: string };
  subject?: { name: string; code: string };
  employee?: {
    first_name: string;
    last_name: string;
    user?: { name: string };
  };
  substitutions?: Array<{
    id: string;
    date: string;
    substitute_teacher?: { user?: { name: string } };
  }>;
}

interface TimetableItem {
  id: string;
  name: string;
  effective_from: string;
  effective_to?: string;
  is_active: boolean;
  periods?: TimetablePeriodItem[];
  entries?: TimetableEntryItem[];
}

function AcademicDashboard() {
  const searchParams = useSearchParams();
  const initialTab = searchParams.get("tab") || "overview";
  const [activeTab, setActiveTab] = useState<string>(initialTab);

  const { activeTenant } = useAuth();

  // Data States
  const [sessions, setSessions] = useState<AcademicSessionItem[]>([]);
  const [boards, setBoards] = useState<BoardItem[]>([]);
  const [classes, setClasses] = useState<ClassItem[]>([]);
  const [subjects, setSubjects] = useState<SubjectItem[]>([]);
  const [events, setEvents] = useState<CalendarEventItem[]>([]);
  const [timetables, setTimetables] = useState<TimetableItem[]>([]);
  const [activeTimetable, setActiveTimetable] = useState<TimetableItem | null>(null);

  const [isLoading, setIsLoading] = useState(true);
  const [actionMessage, setActionMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Modal States
  const [isSessionModalOpen, setIsSessionModalOpen] = useState(false);
  const [isClassModalOpen, setIsClassModalOpen] = useState(false);
  const [isSectionModalOpen, setIsSectionModalOpen] = useState(false);
  const [isSubjectModalOpen, setIsSubjectModalOpen] = useState(false);
  const [isEventModalOpen, setIsEventModalOpen] = useState(false);
  const [isSlotModalOpen, setIsSlotModalOpen] = useState(false);
  const [isSubstituteModalOpen, setIsSubstituteModalOpen] = useState(false);

  // Target Selections for Sub-Modals
  const [selectedClass, setSelectedClass] = useState<ClassItem | null>(null);
  const [selectedEntry, setSelectedEntry] = useState<TimetableEntryItem | null>(null);

  // Form Field States
  const [newSessionName, setNewSessionName] = useState("");
  const [newSessionStart, setNewSessionStart] = useState("");
  const [newSessionEnd, setNewSessionEnd] = useState("");
  const [newSessionIsCurrent, setNewSessionIsCurrent] = useState(true);

  const [newClassName, setNewClassName] = useState("");
  const [newClassCode, setNewClassCode] = useState("");
  const [newClassBoardId, setNewClassBoardId] = useState("");

  const [newSectionName, setNewSectionName] = useState("");
  const [newSectionRoom, setNewSectionRoom] = useState("");
  const [newSectionCapacity, setNewSectionCapacity] = useState(40);

  const [newSubjectName, setNewSubjectName] = useState("");
  const [newSubjectCode, setNewSubjectCode] = useState("");
  const [newSubjectType, setNewSubjectType] = useState("theory");
  const [newSubjectCredits, setNewSubjectCredits] = useState(1.0);

  const [newEventTitle, setNewEventTitle] = useState("");
  const [newEventType, setNewEventType] = useState("holiday");
  const [newEventStart, setNewEventStart] = useState("");
  const [newEventEnd, setNewEventEnd] = useState("");
  const [newEventIsHoliday, setNewEventIsHoliday] = useState(true);

  // Slot Form States
  const [slotDay, setSlotDay] = useState(1);
  const [slotPeriodId, setSlotPeriodId] = useState("");
  const [slotClassId, setSlotClassId] = useState("");
  const [slotSectionId, setSlotSectionId] = useState("");
  const [slotSubjectId, setSlotSubjectId] = useState("");
  const [slotTeacherId, setSlotTeacherId] = useState("");
  const [slotRoom, setSlotRoom] = useState("");
  const [conflictWarning, setConflictWarning] = useState<string[] | null>(null);

  // Substitute States
  const [substituteTeacherId, setSubstituteTeacherId] = useState("");
  const [substituteDate, setSubstituteDate] = useState("");
  const [substituteReason, setSubstituteReason] = useState("");

  // Fetch all academic resources
  const loadAcademicData = useCallback(async () => {
    setIsLoading(true);
    try {
      const [sessionsRes, boardsRes, classesRes, subjectsRes, timetablesRes] = await Promise.all([
        apiClient.get("/academic-sessions"),
        apiClient.get("/boards"),
        apiClient.get("/classes"),
        apiClient.get("/subjects"),
        apiClient.get("/timetables"),
      ]);

      setSessions(sessionsRes.data.data || []);
      setBoards(boardsRes.data.data || []);
      setClasses(classesRes.data.data || []);
      setSubjects(subjectsRes.data.data || []);
      const ttList = timetablesRes.data.data || [];
      setTimetables(ttList);

      if (ttList.length > 0) {
        // Fetch detailed active timetable
        const detailedTtRes = await apiClient.get(`/timetables/${ttList[0].id}`);
        setActiveTimetable(detailedTtRes.data.data);
      }

      // Fetch Calendars
      const calRes = await apiClient.get("/academic-calendars");
      if (calRes.data.data?.length > 0) {
        const calId = calRes.data.data[0].id;
        const calDetail = await apiClient.get(`/academic-calendars/${calId}`);
        setEvents(calDetail.data.data.events || []);
      }
    } catch {
      // API error handling
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAcademicData();
  }, [loadAcademicData]);

  // Session Handlers
  const handleCreateSession = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionMessage(null);
    try {
      await apiClient.post("/academic-sessions", {
        name: newSessionName,
        start_date: newSessionStart,
        end_date: newSessionEnd,
        is_current: newSessionIsCurrent,
      });
      setIsSessionModalOpen(false);
      setActionMessage({ type: "success", text: `Academic session '${newSessionName}' created successfully.` });
      loadAcademicData();
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      const msg = errorObj.errors?.start_date?.[0] || errorObj.message || "Failed to create academic session.";
      setActionMessage({ type: "error", text: msg });
    }
  };

  const handleActivateSession = async (sessionId: string) => {
    setActionMessage(null);
    try {
      await apiClient.post(`/academic-sessions/${sessionId}/activate`);
      setActionMessage({ type: "success", text: "Academic session activated." });
      loadAcademicData();
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setActionMessage({ type: "error", text: errorObj.message || "Failed to activate session." });
    }
  };

  // Class & Section Handlers
  const handleCreateClass = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionMessage(null);
    try {
      await apiClient.post("/classes", {
        name: newClassName,
        code: newClassCode,
        board_id: newClassBoardId || null,
      });
      setIsClassModalOpen(false);
      setNewClassName("");
      setNewClassCode("");
      setActionMessage({ type: "success", text: `Class '${newClassName}' created successfully.` });
      loadAcademicData();
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      const msg = errorObj.errors?.code?.[0] || errorObj.errors?.name?.[0] || errorObj.message || "Failed to create class.";
      setActionMessage({ type: "error", text: msg });
    }
  };

  const handleCreateSection = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedClass) return;
    setActionMessage(null);
    try {
      await apiClient.post(`/classes/${selectedClass.id}/sections`, {
        name: newSectionName,
        room_number: newSectionRoom || null,
        max_capacity: newSectionCapacity,
      });
      setIsSectionModalOpen(false);
      setNewSectionName("");
      setNewSectionRoom("");
      setActionMessage({ type: "success", text: `Section '${newSectionName}' added to ${selectedClass.name}.` });
      loadAcademicData();
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      const msg = errorObj.errors?.name?.[0] || errorObj.message || "Failed to add section.";
      setActionMessage({ type: "error", text: msg });
    }
  };

  // Subject Handlers
  const handleCreateSubject = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionMessage(null);
    try {
      await apiClient.post("/subjects", {
        name: newSubjectName,
        code: newSubjectCode,
        type: newSubjectType,
        credit_hours: newSubjectCredits,
      });
      setIsSubjectModalOpen(false);
      setNewSubjectName("");
      setNewSubjectCode("");
      setActionMessage({ type: "success", text: `Subject '${newSubjectName}' created successfully.` });
      loadAcademicData();
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      const msg = errorObj.errors?.code?.[0] || errorObj.message || "Failed to create subject.";
      setActionMessage({ type: "error", text: msg });
    }
  };

  // Calendar Event Handlers
  const handleCreateEvent = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionMessage(null);
    try {
      // Find or create default calendar
      let calId: string;
      const calRes = await apiClient.get("/academic-calendars");
      if (calRes.data.data?.length > 0) {
        calId = calRes.data.data[0].id;
      } else {
        const activeSession = sessions.find((s) => s.is_current) || sessions[0];
        const newCal = await apiClient.post("/academic-calendars", {
          academic_session_id: activeSession.id,
          name: `${activeSession.name} Academic Calendar`,
        });
        calId = newCal.data.data.id;
      }

      await apiClient.post(`/academic-calendars/${calId}/events`, {
        title: newEventTitle,
        event_type: newEventType,
        start_date: newEventStart,
        end_date: newEventEnd,
        is_holiday: newEventIsHoliday,
      });

      setIsEventModalOpen(false);
      setNewEventTitle("");
      setActionMessage({ type: "success", text: `Calendar event '${newEventTitle}' recorded.` });
      loadAcademicData();
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setActionMessage({ type: "error", text: errorObj.message || "Failed to add event." });
    }
  };

  // Timetable Slot & Conflict Detection Handlers
  const handlePreflightConflictCheck = async () => {
    if (!activeTimetable || !slotPeriodId || !slotSectionId || !slotTeacherId) return;

    try {
      const res = await apiClient.post(`/timetables/${activeTimetable.id}/check-conflicts`, {
        period_id: slotPeriodId,
        day_of_week: Number(slotDay),
        class_id: slotClassId,
        section_id: slotSectionId,
        subject_id: slotSubjectId,
        employee_id: slotTeacherId,
        room_number: slotRoom || null,
      });

      if (res.data.has_conflict) {
        setConflictWarning(res.data.conflicts);
      } else {
        setConflictWarning(null);
      }
    } catch {
      // pre-flight error
    }
  };

  const handleAssignSlot = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeTimetable) return;
    setActionMessage(null);

    try {
      await apiClient.post(`/timetables/${activeTimetable.id}/slots`, {
        period_id: slotPeriodId,
        day_of_week: Number(slotDay),
        class_id: slotClassId,
        section_id: slotSectionId,
        subject_id: slotSubjectId,
        employee_id: slotTeacherId,
        room_number: slotRoom || null,
      });

      setIsSlotModalOpen(false);
      setActionMessage({ type: "success", text: "Period assigned successfully without conflict." });
      // Reload active timetable
      const detailedTt = await apiClient.get(`/timetables/${activeTimetable.id}`);
      setActiveTimetable(detailedTt.data.data);
    } catch (err: unknown) {
      const errorObj = err as { errors?: { timetable_conflict?: string[] }; message?: string };
      const conflicts = errorObj.errors?.timetable_conflict;
      if (conflicts && conflicts.length > 0) {
        setConflictWarning(conflicts);
      } else {
        setActionMessage({ type: "error", text: errorObj.message || "Failed to schedule slot." });
      }
    }
  };

  // Substitute Teacher Handler
  const handleAssignSubstitute = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedEntry) return;
    setActionMessage(null);

    try {
      await apiClient.post(`/timetable-entries/${selectedEntry.id}/substitute`, {
        substitute_employee_id: substituteTeacherId,
        date: substituteDate,
        reason: substituteReason || null,
      });

      setIsSubstituteModalOpen(false);
      setActionMessage({ type: "success", text: "Substitute teacher scheduled successfully." });
      if (activeTimetable) {
        const detailedTt = await apiClient.get(`/timetables/${activeTimetable.id}`);
        setActiveTimetable(detailedTt.data.data);
      }
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      const msg = errorObj.errors?.substitute_employee_id?.[0] || errorObj.message || "Failed to assign substitute.";
      setActionMessage({ type: "error", text: msg });
    }
  };

  const currentSession = sessions.find((s) => s.is_current);

  const daysMap: Record<number, string> = {
    1: "Monday",
    2: "Tuesday",
    3: "Wednesday",
    4: "Thursday",
    5: "Friday",
    6: "Saturday",
  };

  return (
    <div className="flex min-h-[calc(100vh-3.5rem)]">
      <Sidebar className="hidden md:flex" />

      <div className="flex-1 overflow-y-auto px-4 py-8 sm:px-8 max-w-7xl mx-auto space-y-6">
        {/* Module Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
          <div>
            <div className="flex items-center gap-2 mb-1.5">
              <Badge variant="outline" className="text-[10px] font-mono uppercase bg-blue-500/10 text-blue-600 border-blue-500/20">
                Core Academic Module
              </Badge>
              {currentSession && (
                <Badge variant="success" className="text-[10px] gap-1">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
                  Active Year: {currentSession.name}
                </Badge>
              )}
            </div>
            <h1 className="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
              Academic Management
            </h1>
            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
              Configurable academic structure supporting CBSE, ICSE, and State Boards &bull; Conflict-Free Scheduling
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={loadAcademicData} disabled={isLoading} className="gap-1.5 text-xs">
              <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? "animate-spin" : ""}`} />
              <span>Refresh</span>
            </Button>

            <Can permission="academics.create">
              <Button size="sm" onClick={() => setIsSessionModalOpen(true)} className="gap-1.5 text-xs">
                <Plus className="h-3.5 w-3.5" />
                <span>New Session</span>
              </Button>
            </Can>
          </div>
        </div>

        {/* Global Feedback Banner */}
        {actionMessage && (
          <div
            className={`flex items-center justify-between rounded-lg p-3 text-xs border ${
              actionMessage.type === "success"
                ? "bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                : "bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900"
            }`}
          >
            <div className="flex items-center gap-2">
              {actionMessage.type === "success" ? (
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
              ) : (
                <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
              )}
              <span>{actionMessage.text}</span>
            </div>
            <button onClick={() => setActionMessage(null)} className="text-xs font-semibold hover:underline">
              Dismiss
            </button>
          </div>
        )}

        {/* KPI Summary Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card className="hover:shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-slate-500">Academic Sessions</CardTitle>
              <Calendar className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold">{sessions.length} Years</div>
              <p className="text-[11px] text-slate-500 mt-1">
                Active: {currentSession?.name || "None Selected"}
              </p>
            </CardContent>
          </Card>

          <Card className="hover:shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-slate-500">Classes &amp; Sections</CardTitle>
              <GraduationCap className="h-4 w-4 text-purple-600" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold">{classes.length} Classes</div>
              <p className="text-[11px] text-slate-500 mt-1">
                {classes.reduce((sum, c) => sum + (c.sections?.length || 0), 0)} Registered Sections
              </p>
            </CardContent>
          </Card>

          <Card className="hover:shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-slate-500">Subjects Catalogue</CardTitle>
              <BookOpen className="h-4 w-4 text-emerald-600" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold">{subjects.length} Subjects</div>
              <p className="text-[11px] text-slate-500 mt-1">Theory &amp; Practical Electives</p>
            </CardContent>
          </Card>

          <Card className="hover:shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-slate-500">Board Affiliations</CardTitle>
              <School className="h-4 w-4 text-amber-600" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold">{boards.length} Boards</div>
              <div className="flex gap-1 mt-1">
                {boards.slice(0, 3).map((b) => (
                  <Badge key={b.id} variant="outline" className="text-[9px]">
                    {b.code}
                  </Badge>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Navigation Tabs Header */}
        <div className="flex items-center gap-1 border-b border-slate-200 dark:border-slate-800 overflow-x-auto pb-px">
          {[
            { id: "overview", label: "Overview & Boards", icon: Layers },
            { id: "sessions", label: "Academic Sessions", icon: Calendar },
            { id: "classes", label: "Classes & Sections", icon: GraduationCap },
            { id: "subjects", label: "Subject Catalogue", icon: BookOpen },
            { id: "calendar", label: "Academic Calendar", icon: CalendarDays },
            { id: "timetable", label: "Master Timetable", icon: Clock },
          ].map((tab) => {
            const Icon = tab.icon;
            const isTabActive = activeTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-2 px-4 py-2.5 text-xs font-medium border-b-2 whitespace-nowrap transition-colors ${
                  isTabActive
                    ? "border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400 font-semibold"
                    : "border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-200"
                }`}
              >
                <Icon className="h-4 w-4" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </div>

        {/* Tab 1: Overview & Boards */}
        {activeTab === "overview" && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Education Boards */}
              <Card>
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2">
                    <School className="h-5 w-5 text-blue-600" />
                    <span>Education Boards Configuration</span>
                  </CardTitle>
                  <CardDescription className="text-xs">
                    Multi-board curriculum setup supporting CBSE, ICSE, State Boards, and International programs.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  {boards.map((b) => (
                    <div
                      key={b.id}
                      className="flex items-start justify-between p-3 rounded-lg border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40"
                    >
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-xs text-slate-900 dark:text-slate-100">{b.name}</span>
                          <Badge variant="outline" className="text-[10px] font-mono">
                            {b.code}
                          </Badge>
                        </div>
                        {b.description && <p className="text-[11px] text-slate-500 line-clamp-2">{b.description}</p>}
                      </div>
                      <Badge variant="success" className="text-[10px]">
                        Active
                      </Badge>
                    </div>
                  ))}
                </CardContent>
              </Card>

              {/* Board-Agnostic Architecture Note */}
              <Card className="border-blue-200 bg-blue-50/30 dark:border-blue-900 dark:bg-blue-950/20">
                <CardHeader>
                  <CardTitle className="text-base flex items-center gap-2 text-slate-900 dark:text-slate-100">
                    <Building className="h-5 w-5 text-blue-600" />
                    <span>Configurable Assessment Engine</span>
                  </CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                  <p>
                    <strong>No Hardcoded Board Rules:</strong> Rather than hardcoding assessment policies for CBSE or State
                    boards, EduCore allows independent configuration of:
                  </p>
                  <ul className="list-disc pl-5 space-y-1.5 text-slate-500 dark:text-slate-400">
                    <li>Grading Schemes (Letter Grades, CBSE 9-Point Scale, GPA 4.0, or Percentage marks).</li>
                    <li>Credit weights and weekly period allocations per subject.</li>
                    <li>Individual class-level board mappings (e.g., CBSE for Secondary, State Board for Primary).</li>
                    <li>Term structures and vacation schedules defined strictly via the Academic Calendar.</li>
                  </ul>
                </CardContent>
              </Card>
            </div>
          </div>
        )}

        {/* Tab 2: Academic Sessions */}
        {activeTab === "sessions" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">Academic Sessions</h3>
                <p className="text-xs text-slate-500">
                  Manage school academic years, configure start and end dates, and switch active operational years.
                </p>
              </div>
              <Can permission="academics.create">
                <Button size="sm" onClick={() => setIsSessionModalOpen(true)} className="gap-1.5 text-xs">
                  <Plus className="h-3.5 w-3.5" />
                  <span>Create Session</span>
                </Button>
              </Can>
            </div>

            <Card>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Session Name</TableHead>
                      <TableHead>Start Date</TableHead>
                      <TableHead>End Date</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Action</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sessions.map((sess) => (
                      <TableRow key={sess.id}>
                        <TableCell className="font-semibold text-xs">{sess.name}</TableCell>
                        <TableCell className="text-xs text-slate-600 dark:text-slate-400">
                          {new Date(sess.start_date).toLocaleDateString()}
                        </TableCell>
                        <TableCell className="text-xs text-slate-600 dark:text-slate-400">
                          {new Date(sess.end_date).toLocaleDateString()}
                        </TableCell>
                        <TableCell>
                          {sess.is_current ? (
                            <Badge variant="success" className="text-[10px]">
                              Current Active
                            </Badge>
                          ) : (
                            <Badge variant="secondary" className="text-[10px]">
                              Historical
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          {!sess.is_current && (
                            <Can permission="academics.manage">
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleActivateSession(sess.id)}
                                className="h-7 text-xs gap-1"
                              >
                                <Check className="h-3 w-3" />
                                <span>Activate</span>
                              </Button>
                            </Can>
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

        {/* Tab 3: Classes & Sections */}
        {activeTab === "classes" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">Classes &amp; Sections</h3>
                <p className="text-xs text-slate-500">
                  Organize grades, configure sections, room capacities, and assign class teachers.
                </p>
              </div>
              <Can permission="academics.create">
                <Button size="sm" onClick={() => setIsClassModalOpen(true)} className="gap-1.5 text-xs">
                  <Plus className="h-3.5 w-3.5" />
                  <span>Add Class</span>
                </Button>
              </Can>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {classes.map((cls) => (
                <Card key={cls.id} className="hover:border-blue-300 dark:hover:border-blue-800 transition-colors">
                  <CardHeader className="pb-3 flex flex-row items-center justify-between">
                    <div>
                      <CardTitle className="text-sm font-bold flex items-center gap-1.5">
                        <span>{cls.name}</span>
                        <Badge variant="outline" className="text-[10px] font-mono">
                          {cls.code}
                        </Badge>
                      </CardTitle>
                      {cls.board && <CardDescription className="text-[11px]">{cls.board.name}</CardDescription>}
                    </div>

                    <Can permission="academics.create">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          setSelectedClass(cls);
                          setIsSectionModalOpen(true);
                        }}
                        className="h-7 px-2 text-xs gap-1"
                      >
                        <Plus className="h-3 w-3" />
                        <span>Section</span>
                      </Button>
                    </Can>
                  </CardHeader>
                  <CardContent className="space-y-2 pt-0">
                    <div className="text-[11px] font-semibold text-slate-500">Sections ({cls.sections?.length || 0}):</div>
                    <div className="flex flex-wrap gap-1.5">
                      {cls.sections && cls.sections.length > 0 ? (
                        cls.sections.map((sec) => {
                          const primaryTeacher = sec.class_teachers?.find((ct) => ct.is_primary)?.employee;
                          return (
                            <div
                              key={sec.id}
                              className="rounded-md border border-slate-200 dark:border-slate-800 p-2 text-xs bg-slate-50/50 dark:bg-slate-800/40 w-full flex items-center justify-between"
                            >
                              <div>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">{sec.name}</span>
                                {sec.room_number && (
                                  <span className="text-[10px] text-slate-400 ml-2">Room: {sec.room_number}</span>
                                )}
                                {primaryTeacher && (
                                  <p className="text-[10px] text-blue-600 dark:text-blue-400 flex items-center gap-1 mt-0.5">
                                    <UserCheck className="h-3 w-3" />
                                    <span>
                                      {primaryTeacher.first_name} {primaryTeacher.last_name}
                                    </span>
                                  </p>
                                )}
                              </div>
                              <Badge variant="secondary" className="text-[10px]">
                                Cap: {sec.max_capacity}
                              </Badge>
                            </div>
                          );
                        })
                      ) : (
                        <p className="text-[11px] text-slate-400">No sections added yet.</p>
                      )}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* Tab 4: Subject Catalogue */}
        {activeTab === "subjects" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">Subject Management</h3>
                <p className="text-xs text-slate-500">
                  Course catalogue, theory/practical classifications, credit units, and teacher subject assignments.
                </p>
              </div>
              <Can permission="academics.create">
                <Button size="sm" onClick={() => setIsSubjectModalOpen(true)} className="gap-1.5 text-xs">
                  <Plus className="h-3.5 w-3.5" />
                  <span>Add Subject</span>
                </Button>
              </Can>
            </div>

            <Card>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Subject Code</TableHead>
                      <TableHead>Subject Title</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Credit Units</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {subjects.map((sub) => (
                      <TableRow key={sub.id}>
                        <TableCell className="font-mono text-xs font-semibold">{sub.code}</TableCell>
                        <TableCell className="text-xs font-medium">{sub.name}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-[10px] capitalize">
                            {sub.type}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs font-mono">{sub.credit_hours} hrs/wk</TableCell>
                        <TableCell>
                          <Badge variant="success" className="text-[10px]">
                            Active
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Tab 5: Academic Calendar */}
        {activeTab === "calendar" && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">Academic Calendar &amp; Events</h3>
                <p className="text-xs text-slate-500">
                  Configure school holidays, examination date windows, vacation periods, and institutional events.
                </p>
              </div>
              <Can permission="academics.manage">
                <Button size="sm" onClick={() => setIsEventModalOpen(true)} className="gap-1.5 text-xs">
                  <Plus className="h-3.5 w-3.5" />
                  <span>Add Event / Holiday</span>
                </Button>
              </Can>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {events.map((evt) => (
                <Card key={evt.id} className="border-l-4 border-l-blue-500">
                  <CardHeader className="pb-2">
                    <div className="flex items-center justify-between">
                      <Badge
                        variant={evt.is_holiday ? "destructive" : "outline"}
                        className="text-[10px] uppercase font-mono"
                      >
                        {evt.event_type}
                      </Badge>
                      <span className="text-[11px] text-slate-400">
                        {new Date(evt.start_date).toLocaleDateString()}
                      </span>
                    </div>
                    <CardTitle className="text-sm font-semibold mt-1">{evt.title}</CardTitle>
                  </CardHeader>
                  <CardContent className="text-xs text-slate-500">
                    <p>
                      {new Date(evt.start_date).toLocaleDateString()} &mdash;{" "}
                      {new Date(evt.end_date).toLocaleDateString()}
                    </p>
                    {evt.description && <p className="mt-1 text-slate-600 dark:text-slate-400">{evt.description}</p>}
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* Tab 6: Master Timetable & Conflict Engine */}
        {activeTab === "timetable" && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                  <Clock className="h-5 w-5 text-blue-600" />
                  <span>Master Timetable &amp; Schedule Matrix</span>
                </h3>
                <p className="text-xs text-slate-500">
                  Period slot scheduling with dual-layer conflict detection (teacher availability &amp; room double-booking)
                </p>
              </div>

              <div className="flex items-center gap-2">
                <Can permission="academics.manage">
                  <Button size="sm" onClick={() => setIsSlotModalOpen(true)} className="gap-1.5 text-xs">
                    <Plus className="h-3.5 w-3.5" />
                    <span>Assign Period Slot</span>
                  </Button>
                </Can>
              </div>
            </div>

            {/* Conflict Engine Notice */}
            <div className="rounded-lg border border-amber-200 bg-amber-50/50 dark:border-amber-900/60 dark:bg-amber-950/20 p-3.5 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2.5">
              <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 mt-0.5" />
              <div className="space-y-1">
                <span className="font-semibold">Automated Conflict Prevention Active</span>
                <p className="text-slate-600 dark:text-slate-400 text-[11px]">
                  The backend Conflict Engine validates in real time: teacher availability, room exclusivity, section
                  period collision, and break protections. Substitute teacher assignments check historical availability.
                </p>
              </div>
            </div>

            {/* Weekly Timetable Schedule Matrix */}
            {activeTimetable && activeTimetable.periods && (
              <Card className="overflow-x-auto">
                <CardHeader className="pb-3 border-b border-slate-100 dark:border-slate-800">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-sm font-bold">{activeTimetable.name}</CardTitle>
                      <CardDescription className="text-xs">
                        Effective from {new Date(activeTimetable.effective_from).toLocaleDateString()}
                      </CardDescription>
                    </div>
                    <Badge variant="outline" className="text-xs">
                      {activeTimetable.periods.length} Periods Defined
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="p-0">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-24">Day</TableHead>
                        {activeTimetable.periods.map((p) => (
                          <TableHead key={p.id} className="text-center min-w-[140px]">
                            <div className="flex flex-col items-center">
                              <span className="font-bold text-xs">{p.period_name}</span>
                              <span className="text-[10px] font-mono text-slate-400">
                                {p.start_time} - {p.end_time}
                              </span>
                              {p.is_break && (
                                <Badge variant="secondary" className="text-[9px] mt-0.5 py-0 px-1">
                                  Break
                                </Badge>
                              )}
                            </div>
                          </TableHead>
                        ))}
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {[1, 2, 3, 4, 5, 6].map((dayNum) => (
                        <TableRow key={dayNum}>
                          <TableCell className="font-semibold text-xs bg-slate-50 dark:bg-slate-900/50">
                            {daysMap[dayNum]}
                          </TableCell>
                          {activeTimetable.periods?.map((p) => {
                            if (p.is_break) {
                              return (
                                <TableCell key={p.id} className="bg-slate-100/40 dark:bg-slate-800/20 text-center text-[10px] text-slate-400 italic">
                                  Break
                                </TableCell>
                              );
                            }

                            const entry = activeTimetable.entries?.find(
                              (e) => e.day_of_week === dayNum && e.period_id === p.id
                            );

                            return (
                              <TableCell key={p.id} className="p-2 text-center align-top">
                                {entry ? (
                                  <div className="rounded-lg border border-blue-200 dark:border-blue-900 bg-blue-50/50 dark:bg-blue-950/30 p-2 text-left space-y-1">
                                    <div className="flex items-center justify-between">
                                      <span className="font-bold text-xs text-blue-900 dark:text-blue-200">
                                        {entry.subject?.name || "Subject"}
                                      </span>
                                      {entry.room_number && (
                                        <Badge variant="outline" className="text-[9px] px-1 py-0 font-mono">
                                          {entry.room_number}
                                        </Badge>
                                      )}
                                    </div>
                                    <p className="text-[10px] text-slate-600 dark:text-slate-400">
                                      {entry.school_class?.name} - {entry.section?.name}
                                    </p>
                                    <div className="flex items-center justify-between pt-1 border-t border-blue-100 dark:border-blue-900/60 text-[10px] text-slate-500">
                                      <span>
                                        {entry.employee?.first_name} {entry.employee?.last_name}
                                      </span>
                                      <button
                                        onClick={() => {
                                          setSelectedEntry(entry);
                                          setIsSubstituteModalOpen(true);
                                        }}
                                        className="text-[9px] text-blue-600 hover:underline font-semibold"
                                        title="Assign substitute teacher"
                                      >
                                        Sub
                                      </button>
                                    </div>
                                  </div>
                                ) : (
                                  <span className="text-[11px] text-slate-300 dark:text-slate-700">&mdash;</span>
                                )}
                              </TableCell>
                            );
                          })}
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {/* ========================================================================= */}
        {/* Modals for Create Operations */}
        {/* ========================================================================= */}

        {/* 1. Create Academic Session Modal */}
        <Modal
          isOpen={isSessionModalOpen}
          onClose={() => setIsSessionModalOpen(false)}
          title="Create Academic Session"
          description="Define a new academic year period. Start date must precede end date."
        >
          <form onSubmit={handleCreateSession} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="sessionName">Session Title (e.g. 2026-2027)</Label>
              <Input
                id="sessionName"
                placeholder="2026-2027"
                value={newSessionName}
                onChange={(e) => setNewSessionName(e.target.value)}
                required
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="sessionStart">Start Date</Label>
                <Input
                  id="sessionStart"
                  type="date"
                  value={newSessionStart}
                  onChange={(e) => setNewSessionStart(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="sessionEnd">End Date</Label>
                <Input
                  id="sessionEnd"
                  type="date"
                  value={newSessionEnd}
                  onChange={(e) => setNewSessionEnd(e.target.value)}
                  required
                />
              </div>
            </div>
            <div className="flex items-center gap-2">
              <input
                id="isCurrentSession"
                type="checkbox"
                checked={newSessionIsCurrent}
                onChange={(e) => setNewSessionIsCurrent(e.target.checked)}
                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <Label htmlFor="isCurrentSession" className="text-xs">
                Set as active current session for this branch
              </Label>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsSessionModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm">
                Save Academic Session
              </Button>
            </div>
          </form>
        </Modal>

        {/* 2. Create Class Modal */}
        <Modal
          isOpen={isClassModalOpen}
          onClose={() => setIsClassModalOpen(false)}
          title="Add New Class / Grade"
          description="Register a new academic standard in this branch."
        >
          <form onSubmit={handleCreateClass} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="className">Class Name (e.g. Grade 10)</Label>
              <Input
                id="className"
                placeholder="Grade 10"
                value={newClassName}
                onChange={(e) => setNewClassName(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="classCode">Class Code (e.g. G10)</Label>
              <Input
                id="classCode"
                placeholder="G10"
                value={newClassCode}
                onChange={(e) => setNewClassCode(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="classBoard">Affiliated Education Board</Label>
              <select
                id="classBoard"
                value={newClassBoardId}
                onChange={(e) => setNewClassBoardId(e.target.value)}
                className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
              >
                <option value="">Select Education Board</option>
                {boards.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name} ({b.code})
                  </option>
                ))}
              </select>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsClassModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm">
                Create Class
              </Button>
            </div>
          </form>
        </Modal>

        {/* 3. Create Section Modal */}
        <Modal
          isOpen={isSectionModalOpen}
          onClose={() => setIsSectionModalOpen(false)}
          title={`Add Section to ${selectedClass?.name || "Class"}`}
          description="Configure section name, designated room, and maximum enrollment seat capacity."
        >
          <form onSubmit={handleCreateSection} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="secName">Section Identifier (e.g. Section A)</Label>
              <Input
                id="secName"
                placeholder="Section A"
                value={newSectionName}
                onChange={(e) => setNewSectionName(e.target.value)}
                required
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="secRoom">Room Number</Label>
                <Input
                  id="secRoom"
                  placeholder="Room 101"
                  value={newSectionRoom}
                  onChange={(e) => setNewSectionRoom(e.target.value)}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="secCap">Capacity</Label>
                <Input
                  id="secCap"
                  type="number"
                  min={1}
                  max={200}
                  value={newSectionCapacity}
                  onChange={(e) => setNewSectionCapacity(Number(e.target.value))}
                  required
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsSectionModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm">
                Add Section
              </Button>
            </div>
          </form>
        </Modal>

        {/* 4. Create Subject Modal */}
        <Modal
          isOpen={isSubjectModalOpen}
          onClose={() => setIsSubjectModalOpen(false)}
          title="Add New Subject"
          description="Register curriculum subject code, type, and weekly credit periods."
        >
          <form onSubmit={handleCreateSubject} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="subName">Subject Title (e.g. Mathematics)</Label>
              <Input
                id="subName"
                placeholder="Mathematics"
                value={newSubjectName}
                onChange={(e) => setNewSubjectName(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="subCode">Subject Code (e.g. MATH-10)</Label>
              <Input
                id="subCode"
                placeholder="MATH-10"
                value={newSubjectCode}
                onChange={(e) => setNewSubjectCode(e.target.value)}
                required
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="subType">Subject Type</Label>
                <select
                  id="subType"
                  value={newSubjectType}
                  onChange={(e) => setNewSubjectType(e.target.value)}
                  className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
                >
                  <option value="theory">Theory</option>
                  <option value="practical">Practical / Lab</option>
                  <option value="elective">Elective</option>
                  <option value="co_curricular">Co-Curricular</option>
                </select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="subCredits">Credit Hours</Label>
                <Input
                  id="subCredits"
                  type="number"
                  step="0.5"
                  min="0.5"
                  value={newSubjectCredits}
                  onChange={(e) => setNewSubjectCredits(Number(e.target.value))}
                  required
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsSubjectModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm">
                Add Subject
              </Button>
            </div>
          </form>
        </Modal>

        {/* 5. Create Calendar Event Modal */}
        <Modal
          isOpen={isEventModalOpen}
          onClose={() => setIsEventModalOpen(false)}
          title="Add Calendar Event / Holiday"
          description="Schedule vacations, exams, and institutional holidays on the school calendar."
        >
          <form onSubmit={handleCreateEvent} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="evtTitle">Event Title</Label>
              <Input
                id="evtTitle"
                placeholder="e.g. Winter Vacation"
                value={newEventTitle}
                onChange={(e) => setNewEventTitle(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="evtType">Event Category</Label>
              <select
                id="evtType"
                value={newEventType}
                onChange={(e) => setNewEventType(e.target.value)}
                className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
              >
                <option value="holiday">Official Holiday</option>
                <option value="vacation">Vacation Period</option>
                <option value="exam">Examination Window</option>
                <option value="sports">Sports / Cultural</option>
                <option value="event">Institutional Meeting</option>
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="evtStart">Start Date</Label>
                <Input
                  id="evtStart"
                  type="date"
                  value={newEventStart}
                  onChange={(e) => setNewEventStart(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="evtEnd">End Date</Label>
                <Input
                  id="evtEnd"
                  type="date"
                  value={newEventEnd}
                  onChange={(e) => setNewEventEnd(e.target.value)}
                  required
                />
              </div>
            </div>
            <div className="flex items-center gap-2">
              <input
                id="isHoliday"
                type="checkbox"
                checked={newEventIsHoliday}
                onChange={(e) => setNewEventIsHoliday(e.target.checked)}
                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <Label htmlFor="isHoliday" className="text-xs">
                Mark as Non-Working Day (Holiday)
              </Label>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsEventModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm">
                Save Event
              </Button>
            </div>
          </form>
        </Modal>

        {/* 6. Assign Timetable Slot Modal with Preflight Conflict Detection */}
        <Modal
          isOpen={isSlotModalOpen}
          onClose={() => {
            setIsSlotModalOpen(false);
            setConflictWarning(null);
          }}
          title="Schedule Timetable Period Slot"
          description="Assign class, section, subject, and teacher. System prevents double-booking."
        >
          <form onSubmit={handleAssignSlot} className="space-y-4">
            {conflictWarning && (
              <div className="rounded-lg bg-rose-50 dark:bg-rose-950/40 p-3 border border-rose-200 dark:border-rose-900 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                <div className="font-semibold flex items-center gap-1.5">
                  <AlertCircle className="h-4 w-4" />
                  <span>Scheduling Conflict Detected</span>
                </div>
                {conflictWarning.map((c, i) => (
                  <p key={i} className="pl-5 text-[11px] list-disc">
                    &bull; {c}
                  </p>
                ))}
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="slotDay">Day of Week</Label>
                <select
                  id="slotDay"
                  value={slotDay}
                  onChange={(e) => setSlotDay(Number(e.target.value))}
                  className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
                >
                  <option value={1}>Monday</option>
                  <option value={2}>Tuesday</option>
                  <option value={3}>Wednesday</option>
                  <option value={4}>Thursday</option>
                  <option value={5}>Friday</option>
                  <option value={6}>Saturday</option>
                </select>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="slotPeriod">Period</Label>
                <select
                  id="slotPeriod"
                  value={slotPeriodId}
                  onChange={(e) => setSlotPeriodId(e.target.value)}
                  className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
                  required
                >
                  <option value="">Select Period</option>
                  {activeTimetable?.periods?.map((p) => (
                    <option key={p.id} value={p.id} disabled={p.is_break}>
                      {p.period_name} ({p.start_time} - {p.end_time}) {p.is_break ? "[Break]" : ""}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="slotClass">Class</Label>
                <select
                  id="slotClass"
                  value={slotClassId}
                  onChange={(e) => {
                    setSlotClassId(e.target.value);
                    setSlotSectionId("");
                  }}
                  className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
                  required
                >
                  <option value="">Select Class</option>
                  {classes.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="slotSection">Section</Label>
                <select
                  id="slotSection"
                  value={slotSectionId}
                  onChange={(e) => setSlotSectionId(e.target.value)}
                  className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
                  required
                >
                  <option value="">Select Section</option>
                  {classes
                    .find((c) => c.id === slotClassId)
                    ?.sections?.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="slotSubject">Subject</Label>
                <select
                  id="slotSubject"
                  value={slotSubjectId}
                  onChange={(e) => setSlotSubjectId(e.target.value)}
                  className="w-full rounded-md border border-slate-200 bg-transparent px-3 py-1.5 text-xs dark:border-slate-800"
                  required
                >
                  <option value="">Select Subject</option>
                  {subjects.map((sub) => (
                    <option key={sub.id} value={sub.id}>
                      {sub.name} ({sub.code})
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="slotRoom">Designated Room</Label>
                <Input
                  id="slotRoom"
                  placeholder="e.g. Room 102"
                  value={slotRoom}
                  onChange={(e) => setSlotRoom(e.target.value)}
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="slotTeacher">Assigned Teacher (UUID)</Label>
              <Input
                id="slotTeacher"
                placeholder="Enter Teacher Employee UUID"
                value={slotTeacherId}
                onChange={(e) => setSlotTeacherId(e.target.value)}
                required
              />
            </div>

            <div className="flex items-center justify-between pt-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={handlePreflightConflictCheck}
                className="text-xs text-blue-600 gap-1"
              >
                <Shuffle className="h-3.5 w-3.5" />
                <span>Test Conflicts</span>
              </Button>

              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setIsSlotModalOpen(false);
                    setConflictWarning(null);
                  }}
                >
                  Cancel
                </Button>
                <Button type="submit" size="sm">
                  Schedule Slot
                </Button>
              </div>
            </div>
          </form>
        </Modal>

        {/* 7. Assign Substitute Teacher Modal */}
        <Modal
          isOpen={isSubstituteModalOpen}
          onClose={() => setIsSubstituteModalOpen(false)}
          title="Assign Substitute Teacher"
          description="Schedule a relief teacher for this period slot on a designated date."
        >
          <form onSubmit={handleAssignSubstitute} className="space-y-4">
            <div className="rounded-lg bg-slate-50 dark:bg-slate-800/40 p-3 text-xs space-y-1">
              <span className="font-semibold text-slate-800 dark:text-slate-200">Covering Slot:</span>
              <p className="text-slate-500">
                {selectedEntry?.subject?.name} &bull; {selectedEntry?.school_class?.name} - {selectedEntry?.section?.name}
              </p>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="subTeacher">Substitute Teacher Employee UUID</Label>
              <Input
                id="subTeacher"
                placeholder="UUID of substitute teacher"
                value={substituteTeacherId}
                onChange={(e) => setSubstituteTeacherId(e.target.value)}
                required
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="subDate">Absence Date</Label>
              <Input
                id="subDate"
                type="date"
                value={substituteDate}
                onChange={(e) => setSubstituteDate(e.target.value)}
                required
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="subReason">Reason for Substitution</Label>
              <Input
                id="subReason"
                placeholder="e.g. Medical leave, Training"
                value={substituteReason}
                onChange={(e) => setSubstituteReason(e.target.value)}
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" size="sm" onClick={() => setIsSubstituteModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm">
                Confirm Substitute
              </Button>
            </div>
          </form>
        </Modal>
      </div>
    </div>
  );
}

export default function AcademicPage() {
  return (
    <Suspense
      fallback={
        <div className="flex h-[calc(100vh-3.5rem)] items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      }
    >
      <AcademicDashboard />
    </Suspense>
  );
}

