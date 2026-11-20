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
import {
  CalendarCheck,
  Users,
  CheckCircle2,
  XCircle,
  Clock,
  AlertCircle,
  Loader2,
  Save,
  CheckCheck,
  RefreshCw,
  Search,
  Calendar,
} from "lucide-react";

interface Branch {
  id: string;
  name: string;
}

interface SchoolClass {
  id: string;
  name: string;
  code?: string;
}

interface Section {
  id: string;
  name: string;
  class_id: string;
}

interface RosterStudent {
  student_id: string;
  enrollment_id: string;
  roll_number: string;
  student_name: string;
  admission_number: string;
  status: "present" | "absent" | "late" | "half_day" | "excused";
  remarks?: string;
  is_marked?: boolean;
}

export default function AttendancePage() {
  const { user, activeTenant } = useAuth();

  const [activeTab, setActiveTab] = useState<"mark" | "history">("mark");
  const [branches, setBranches] = useState<Branch[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [sections, setSections] = useState<Section[]>([]);

  // Selection state
  const [selectedBranchId, setSelectedBranchId] = useState<string>("");
  const [selectedClassId, setSelectedClassId] = useState<string>("");
  const [selectedSectionId, setSelectedSectionId] = useState<string>("");
  const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split("T")[0]);

  // Roster & Attendance state
  const [roster, setRoster] = useState<RosterStudent[]>([]);
  const [loadingRoster, setLoadingRoster] = useState<boolean>(false);
  const [savingAttendance, setSavingAttendance] = useState<boolean>(false);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // History list state
  const [historyRecords, setHistoryRecords] = useState<any[]>([]);
  const [loadingHistory, setLoadingHistory] = useState<boolean>(false);

  // Fetch initial branches and classes
  useEffect(() => {
    async function loadMetadata() {
      try {
        const [branchRes, classRes] = await Promise.all([
          apiClient.get("/settings/branches").catch(() => ({ data: { data: [] } })),
          apiClient.get("/academic/classes").catch(() => ({ data: { data: [] } })),
        ]);

        const branchData = branchRes.data?.data || [];
        setBranches(branchData);
        if (branchData.length > 0) {
          setSelectedBranchId(branchData[0].id);
        }

        const classData = classRes.data?.data || [];
        setClasses(classData);
        if (classData.length > 0) {
          setSelectedClassId(classData[0].id);
        }
      } catch (err) {
        console.error("Failed to load initial metadata", err);
      }
    }

    loadMetadata();
  }, []);

  // Fetch sections when class changes
  useEffect(() => {
    if (!selectedClassId) return;

    async function loadSections() {
      try {
        const res = await apiClient.get(`/academic/classes/${selectedClassId}/sections`);
        const sectionData = res.data?.data || [];
        setSections(sectionData);
        if (sectionData.length > 0) {
          setSelectedSectionId(sectionData[0].id);
        } else {
          setSelectedSectionId("");
        }
      } catch (err) {
        setSections([]);
        setSelectedSectionId("");
      }
    }

    loadSections();
  }, [selectedClassId]);

  // Load roster
  const fetchRoster = useCallback(async () => {
    if (!selectedBranchId || !selectedClassId || !selectedSectionId) {
      setMessage({ type: "error", text: "Please select Branch, Class, and Section first." });
      return;
    }

    setLoadingRoster(true);
    setMessage(null);

    try {
      const res = await apiClient.get("/attendance/roster", {
        params: {
          branch_id: selectedBranchId,
          class_id: selectedClassId,
          section_id: selectedSectionId,
          date: selectedDate,
        },
      });

      const rosterData = res.data?.data?.roster || [];
      setRoster(rosterData);
    } catch (err: any) {
      setMessage({
        type: "error",
        text: err.response?.data?.message || "Failed to load attendance roster.",
      });
      setRoster([]);
    } finally {
      setLoadingRoster(false);
    }
  }, [selectedBranchId, selectedClassId, selectedSectionId, selectedDate]);

  // Load History
  const fetchHistory = useCallback(async () => {
    setLoadingHistory(true);
    try {
      const res = await apiClient.get("/attendance", {
        params: {
          branch_id: selectedBranchId || undefined,
          date: selectedDate || undefined,
        },
      });
      setHistoryRecords(res.data?.data || []);
    } catch (err) {
      console.error(err);
      setHistoryRecords([]);
    } finally {
      setLoadingHistory(false);
    }
  }, [selectedBranchId, selectedDate]);

  useEffect(() => {
    if (activeTab === "history") {
      fetchHistory();
    }
  }, [activeTab, fetchHistory]);

  // Status updates
  const updateStatus = (index: number, status: RosterStudent["status"]) => {
    setRoster((prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], status };
      return updated;
    });
  };

  const markAll = (status: RosterStudent["status"]) => {
    setRoster((prev) => prev.map((s) => ({ ...s, status })));
  };

  // Submit attendance
  const saveAttendance = async () => {
    if (roster.length === 0) return;

    setSavingAttendance(true);
    setMessage(null);

    try {
      const payload = {
        branch_id: selectedBranchId,
        class_id: selectedClassId,
        section_id: selectedSectionId,
        date: selectedDate,
        records: roster.map((s) => ({
          student_id: s.student_id,
          enrollment_id: s.enrollment_id,
          status: s.status,
          remarks: s.remarks,
        })),
      };

      const res = await apiClient.post("/attendance/mark", payload);
      setMessage({
        type: "success",
        text: res.data?.message || "Attendance saved successfully!",
      });
    } catch (err: any) {
      setMessage({
        type: "error",
        text: err.response?.data?.message || "Failed to record attendance.",
      });
    } finally {
      setSavingAttendance(false);
    }
  };

  // Metrics
  const totalCount = roster.length;
  const presentCount = roster.filter((s) => s.status === "present").length;
  const absentCount = roster.filter((s) => s.status === "absent").length;
  const lateCount = roster.filter((s) => s.status === "late").length;
  const attendanceRate = totalCount > 0 ? Math.round((presentCount / totalCount) * 100) : 0;

  return (
    <div className="flex h-screen bg-slate-50 dark:bg-slate-900">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-y-auto">
        <header className="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-8 py-5">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <CalendarCheck className="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                Student Attendance
              </h1>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Mark daily attendance, manage section rosters, and inspect student attendance history.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Button
                variant={activeTab === "mark" ? "default" : "outline"}
                onClick={() => setActiveTab("mark")}
                className="gap-2"
              >
                <CheckCheck className="h-4 w-4" />
                Mark Attendance
              </Button>
              <Button
                variant={activeTab === "history" ? "default" : "outline"}
                onClick={() => setActiveTab("history")}
                className="gap-2"
              >
                <Calendar className="h-4 w-4" />
                Attendance Log
              </Button>
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

          {activeTab === "mark" ? (
            <>
              {/* Filter Card */}
              <Card>
                <CardHeader className="pb-4">
                  <CardTitle className="text-lg">Select Class & Date</CardTitle>
                  <CardDescription>Choose the section roster to mark attendance.</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                      <Label htmlFor="branch">Campus / Branch</Label>
                      <select
                        id="branch"
                        value={selectedBranchId}
                        onChange={(e) => setSelectedBranchId(e.target.value)}
                        className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                      >
                        {branches.map((b) => (
                          <option key={b.id} value={b.id}>
                            {b.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <Label htmlFor="class">Class</Label>
                      <select
                        id="class"
                        value={selectedClassId}
                        onChange={(e) => setSelectedClassId(e.target.value)}
                        className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                      >
                        {classes.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <Label htmlFor="section">Section</Label>
                      <select
                        id="section"
                        value={selectedSectionId}
                        onChange={(e) => setSelectedSectionId(e.target.value)}
                        className="w-full mt-1.5 h-10 px-3 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                      >
                        {sections.map((s) => (
                          <option key={s.id} value={s.id}>
                            Section {s.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <Label htmlFor="date">Date</Label>
                      <Input
                        id="date"
                        type="date"
                        value={selectedDate}
                        onChange={(e) => setSelectedDate(e.target.value)}
                        className="mt-1.5"
                      />
                    </div>

                    <div>
                      <Button
                        onClick={fetchRoster}
                        disabled={loadingRoster}
                        className="w-full gap-2 bg-indigo-600 hover:bg-indigo-700"
                      >
                        {loadingRoster ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Search className="h-4 w-4" />
                        )}
                        Load Roster
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Roster & Metrics */}
              {roster.length > 0 && (
                <>
                  <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <Card className="p-4 bg-white dark:bg-slate-800">
                      <div className="flex justify-between items-center">
                        <span className="text-sm font-medium text-slate-500">Total Enrolled</span>
                        <Users className="h-4 w-4 text-slate-400" />
                      </div>
                      <div className="text-2xl font-bold mt-2">{totalCount}</div>
                    </Card>
                    <Card className="p-4 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200">
                      <div className="flex justify-between items-center">
                        <span className="text-sm font-medium text-emerald-700 dark:text-emerald-400">
                          Present
                        </span>
                        <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                      </div>
                      <div className="text-2xl font-bold text-emerald-800 dark:text-emerald-300 mt-2">
                        {presentCount} ({attendanceRate}%)
                      </div>
                    </Card>
                    <Card className="p-4 bg-rose-50 dark:bg-rose-950/20 border-rose-200">
                      <div className="flex justify-between items-center">
                        <span className="text-sm font-medium text-rose-700 dark:text-rose-400">
                          Absent
                        </span>
                        <XCircle className="h-4 w-4 text-rose-600" />
                      </div>
                      <div className="text-2xl font-bold text-rose-800 dark:text-rose-300 mt-2">
                        {absentCount}
                      </div>
                    </Card>
                    <Card className="p-4 bg-amber-50 dark:bg-amber-950/20 border-amber-200">
                      <div className="flex justify-between items-center">
                        <span className="text-sm font-medium text-amber-700 dark:text-amber-400">Late</span>
                        <Clock className="h-4 w-4 text-amber-600" />
                      </div>
                      <div className="text-2xl font-bold text-amber-800 dark:text-amber-300 mt-2">
                        {lateCount}
                      </div>
                    </Card>
                  </div>

                  <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                      <div>
                        <CardTitle className="text-lg">Student Roster ({selectedDate})</CardTitle>
                        <CardDescription>
                          Click buttons to toggle individual student status or mark all at once.
                        </CardDescription>
                      </div>
                      <div className="flex items-center gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => markAll("present")}
                          className="text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                        >
                          All Present
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => markAll("absent")}
                          className="text-rose-700 border-rose-300 hover:bg-rose-50"
                        >
                          All Absent
                        </Button>
                        <Button
                          onClick={saveAttendance}
                          disabled={savingAttendance}
                          className="gap-2 bg-indigo-600 hover:bg-indigo-700"
                        >
                          {savingAttendance ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <Save className="h-4 w-4" />
                          )}
                          Save Attendance
                        </Button>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead className="w-16">Roll #</TableHead>
                            <TableHead>Admission #</TableHead>
                            <TableHead>Student Name</TableHead>
                            <TableHead className="text-center">Status</TableHead>
                            <TableHead>Remarks</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {roster.map((student, idx) => (
                            <TableRow key={student.student_id}>
                              <TableCell className="font-semibold text-slate-700 dark:text-slate-300">
                                {student.roll_number || idx + 1}
                              </TableCell>
                              <TableCell>{student.admission_number || "—"}</TableCell>
                              <TableCell className="font-medium text-slate-900 dark:text-white">
                                {student.student_name}
                              </TableCell>
                              <TableCell>
                                <div className="flex justify-center gap-1.5">
                                  {(
                                    [
                                      { key: "present", label: "P", color: "emerald" },
                                      { key: "absent", label: "A", color: "rose" },
                                      { key: "late", label: "L", color: "amber" },
                                      { key: "excused", label: "E", color: "blue" },
                                    ] as const
                                  ).map((opt) => {
                                    const isSelected = student.status === opt.key;
                                    return (
                                      <button
                                        key={opt.key}
                                        type="button"
                                        onClick={() => updateStatus(idx, opt.key)}
                                        className={`w-9 h-9 rounded-md font-bold text-xs transition-all ${
                                          isSelected
                                            ? opt.color === "emerald"
                                              ? "bg-emerald-600 text-white shadow-sm ring-2 ring-emerald-400"
                                              : opt.color === "rose"
                                              ? "bg-rose-600 text-white shadow-sm ring-2 ring-rose-400"
                                              : opt.color === "amber"
                                              ? "bg-amber-500 text-white shadow-sm ring-2 ring-amber-300"
                                              : "bg-blue-600 text-white shadow-sm ring-2 ring-blue-300"
                                            : "bg-slate-100 hover:bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                        }`}
                                      >
                                        {opt.label}
                                      </button>
                                    );
                                  })}
                                </div>
                              </TableCell>
                              <TableCell>
                                <Input
                                  placeholder="Optional remark..."
                                  value={student.remarks || ""}
                                  onChange={(e) => {
                                    const val = e.target.value;
                                    setRoster((prev) => {
                                      const arr = [...prev];
                                      arr[idx] = { ...arr[idx], remarks: val };
                                      return arr;
                                    });
                                  }}
                                  className="h-8 text-xs"
                                />
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </CardContent>
                  </Card>
                </>
              )}
            </>
          ) : (
            <Card>
              <CardHeader className="flex flex-row items-center justify-between pb-4">
                <div>
                  <CardTitle className="text-lg">Recent Attendance Records</CardTitle>
                  <CardDescription>Audit past attendance logs across classes.</CardDescription>
                </div>
                <Button variant="outline" size="sm" onClick={fetchHistory} className="gap-2">
                  <RefreshCw className="h-4 w-4" />
                  Refresh
                </Button>
              </CardHeader>
              <CardContent>
                {loadingHistory ? (
                  <div className="py-12 flex justify-center items-center text-slate-400">
                    <Loader2 className="h-6 w-6 animate-spin mr-2" />
                    Loading attendance records...
                  </div>
                ) : historyRecords.length === 0 ? (
                  <div className="py-12 text-center text-slate-400">No attendance records found.</div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Student</TableHead>
                        <TableHead>Class & Section</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Remarks</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {historyRecords.map((rec) => (
                        <TableRow key={rec.id}>
                          <TableCell className="font-medium">{rec.date}</TableCell>
                          <TableCell>
                            {rec.student?.first_name} {rec.student?.last_name}
                          </TableCell>
                          <TableCell>
                            {rec.school_class?.name} - {rec.section?.name}
                          </TableCell>
                          <TableCell>
                            <Badge
                              variant={
                                rec.status === "present"
                                  ? "default"
                                  : rec.status === "absent"
                                  ? "destructive"
                                  : "secondary"
                              }
                            >
                              {rec.status}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-xs text-slate-500">{rec.remarks || "—"}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          )}
        </main>
      </div>
    </div>
  );
}

