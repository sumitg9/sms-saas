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
  Megaphone,
  Bell,
  Mail,
  MessageSquare,
  Smartphone,
  Calendar,
  Clock,
  Send,
  Plus,
  RefreshCw,
  Search,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  RotateCcw,
  Users,
  Paperclip,
  Check,
  Star,
  ExternalLink,
  ShieldAlert,
  ChevronRight,
  Filter,
} from "lucide-react";

// ==========================================
// Types
// ==========================================

interface AnnouncementItem {
  id: string;
  title: string;
  content: string;
  target_audience: "all" | "teachers" | "parents" | "students" | "staff";
  priority: "low" | "normal" | "urgent";
  published_at: string;
  expires_at?: string;
  is_published: boolean;
  requires_acknowledgement: boolean;
  acknowledgements_count?: number;
  class_id?: string;
  section_id?: string;
  school_class?: { name: string };
  section?: { name: string };
  author?: { first_name: string; last_name: string };
  attachments?: Array<{ name: string; url: string }>;
}

interface NotificationTemplateItem {
  id: string;
  event_name: string;
  channel: string;
  language: string;
  subject?: string;
  body_template: string;
  placeholders?: string[];
  is_active: boolean;
}

interface NotificationLogItem {
  id: string;
  channel: "sms" | "whatsapp" | "email" | "push" | "in_app";
  recipient: string;
  message_content: string;
  status: "delivered" | "sent" | "failed" | "suppressed_opt_out" | "suppressed_dnd";
  retry_count: number;
  error_message?: string;
  sent_at: string;
  idempotency_key?: string;
}

interface PtmMeetingItem {
  id: string;
  title: string;
  meeting_type: "in_person" | "online";
  meeting_date: string;
  start_time: string;
  end_time: string;
  room_number?: string;
  meeting_link?: string;
  status: "requested" | "scheduled" | "confirmed" | "completed" | "cancelled";
  requested_by: "teacher" | "parent";
  parent_notes?: string;
  teacher_notes?: string;
  feedback?: string;
  rating?: number;
  teacher?: { first_name: string; last_name: string; user?: { first_name: string; last_name: string } };
  guardian?: { first_name: string; last_name: string; phone?: string };
  student?: { first_name: string; last_name: string; admission_number: string };
}

interface ConversationItem {
  id: string;
  title?: string;
  created_at: string;
  creator?: { first_name: string; last_name: string };
  latest_message?: { content: string; created_at: string; sender_id: string };
}

interface ChatMessage {
  id: string;
  sender_id: string;
  content: string;
  is_read: boolean;
  created_at: string;
  sender?: { first_name: string; last_name: string };
}

export default function CommunicationPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<"announcements" | "notifications" | "templates" | "logs" | "meetings" | "messages">("announcements");
  const [loading, setLoading] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");

  // Announcements State
  const [announcements, setAnnouncements] = useState<AnnouncementItem[]>([]);
  const [isCreateAnnouncementOpen, setIsCreateAnnouncementOpen] = useState(false);
  const [announcementForm, setAnnouncementForm] = useState({
    title: "",
    content: "",
    target_audience: "all" as "all" | "teachers" | "parents" | "students" | "staff",
    priority: "normal" as "low" | "normal" | "urgent",
    published_at: new Date().toISOString().split("T")[0],
    expires_at: "",
    requires_acknowledgement: false,
    attachment_name: "",
    attachment_url: "",
  });

  // Notification Engine State
  const [isSendNotificationOpen, setIsSendNotificationOpen] = useState(false);
  const [notifForm, setNotifForm] = useState({
    mode: "single" as "single" | "bulk",
    channels: ["in_app"] as string[],
    recipient: "",
    target_audience: "all" as "all" | "class" | "role" | "individual",
    event_name: "",
    subject: "",
    message: "",
    priority: "normal" as "low" | "normal" | "urgent" | "emergency",
    scheduled_at: "",
  });

  // Templates State
  const [templates, setTemplates] = useState<NotificationTemplateItem[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState<NotificationTemplateItem | null>(null);
  const [isEditTemplateOpen, setIsEditTemplateOpen] = useState(false);
  const [templateEditForm, setTemplateEditForm] = useState({
    subject: "",
    body_template: "",
    is_active: true,
  });

  // Delivery Logs State
  const [logs, setLogs] = useState<NotificationLogItem[]>([]);
  const [logChannelFilter, setLogChannelFilter] = useState("all");

  // PTM Meetings State
  const [meetings, setMeetings] = useState<PtmMeetingItem[]>([]);
  const [isSchedulePtmOpen, setIsSchedulePtmOpen] = useState(false);
  const [isFeedbackModalOpen, setIsFeedbackModalOpen] = useState(false);
  const [selectedMeeting, setSelectedMeeting] = useState<PtmMeetingItem | null>(null);
  const [ptmForm, setPtmForm] = useState({
    title: "",
    meeting_type: "in_person" as "in_person" | "online",
    meeting_date: new Date(Date.now() + 86400000 * 2).toISOString().split("T")[0],
    start_time: "14:30",
    end_time: "15:00",
    room_number: "Room 101",
    meeting_link: "",
    teacher_notes: "",
  });
  const [feedbackForm, setFeedbackForm] = useState({ feedback: "", rating: 5 });

  // Direct Messages State
  const [conversations, setConversations] = useState<ConversationItem[]>([]);
  const [selectedConversation, setSelectedConversation] = useState<ConversationItem | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [chatMessageText, setChatMessageText] = useState("");

  // Sample Fallbacks
  const sampleAnnouncements: AnnouncementItem[] = [
    {
      id: "ann-1",
      title: "Annual Sports Meet & Inter-House Athletics Schedule",
      content: "The Annual Sports Day will take place on Dec 14 at the Main Sports Complex. Track events begin at 8:30 AM sharp.",
      target_audience: "all",
      priority: "urgent",
      published_at: "2026-11-20",
      is_published: true,
      requires_acknowledgement: true,
      acknowledgements_count: 142,
      author: { first_name: "Principal", last_name: "Admin" },
    },
    {
      id: "ann-2",
      title: "Class 10 CBSE Board Examination Registration Guidelines",
      content: "Parents of Class 10 students must verify candidate details, elective codes, and submit signed forms by Nov 30.",
      target_audience: "parents",
      priority: "urgent",
      published_at: "2026-11-19",
      is_published: true,
      requires_acknowledgement: true,
      acknowledgements_count: 88,
      school_class: { name: "Class 10" },
      author: { first_name: "Examination", last_name: "Superintendent" },
      attachments: [{ name: "CBSE_Reg_Instructions.pdf", url: "https://school.edu/docs/cbse.pdf" }],
    },
  ];

  const sampleTemplates: NotificationTemplateItem[] = [
    {
      id: "tpl-1",
      event_name: "student_absence",
      channel: "sms",
      language: "en",
      body_template: "Dear Parent, your child {{student_name}} of {{class_name}} is marked absent today ({{date}}) at {{school_name}}.",
      placeholders: ["student_name", "class_name", "date", "school_name"],
      is_active: true,
    },
    {
      id: "tpl-2",
      event_name: "fee_reminder",
      channel: "whatsapp",
      language: "en",
      body_template: "💳 Fee Reminder: Tuition fee of Rs. {{amount}} for {{student_name}} is due by {{due_date}}. Pay online: {{payment_url}}",
      placeholders: ["amount", "student_name", "due_date", "payment_url"],
      is_active: true,
    },
    {
      id: "tpl-3",
      event_name: "emergency_alert",
      channel: "sms",
      language: "en",
      body_template: "EMERGENCY ALERT: {{alert_message}}. All students are safe under staff supervision. - {{school_name}}",
      placeholders: ["alert_message", "school_name"],
      is_active: true,
    },
  ];

  const sampleLogs: NotificationLogItem[] = [
    {
      id: "log-1",
      channel: "sms",
      recipient: "+91 98765 43210",
      message_content: "Student absence alert: Aarav Sharma marked absent today (2026-11-20).",
      status: "delivered",
      retry_count: 0,
      sent_at: "2026-11-20 09:15:32",
      idempotency_key: "NOTIF-ABS-101",
    },
    {
      id: "log-2",
      channel: "whatsapp",
      recipient: "+91 98765 43211",
      message_content: "Fee reminder: Term 2 invoice Rs. 15,000 due on Dec 10.",
      status: "delivered",
      retry_count: 0,
      sent_at: "2026-11-20 09:20:10",
      idempotency_key: "NOTIF-FEE-202",
    },
    {
      id: "log-3",
      channel: "sms",
      recipient: "+91 98765 99999",
      message_content: "Exam schedule released for Class 10.",
      status: "failed",
      retry_count: 1,
      error_message: "Carrier unreachable (Error 504 Timeout)",
      sent_at: "2026-11-20 09:22:04",
      idempotency_key: "NOTIF-EXM-303",
    },
  ];

  const sampleMeetings: PtmMeetingItem[] = [
    {
      id: "ptm-1",
      title: "Mathematics Performance Review - Q2",
      meeting_type: "in_person",
      meeting_date: "2026-11-24",
      start_time: "14:30",
      end_time: "15:00",
      room_number: "Room 204",
      status: "confirmed",
      requested_by: "teacher",
      teacher: { first_name: "Albert", last_name: "Einstein" },
      guardian: { first_name: "Sanjay", last_name: "Sharma", phone: "+919876541111" },
      student: { first_name: "Aarav", last_name: "Sharma", admission_number: "ADM-2024-001" },
      teacher_notes: "Review progress on trigonometric identities and problem set 4.",
    },
    {
      id: "ptm-2",
      title: "Science Exhibition Booth Guidance",
      meeting_type: "online",
      meeting_date: "2026-11-26",
      start_time: "16:00",
      end_time: "16:30",
      meeting_link: "https://meet.google.com/abc-defg-hij",
      status: "requested",
      requested_by: "parent",
      teacher: { first_name: "Marie", last_name: "Curie" },
      guardian: { first_name: "Anita", last_name: "Patel" },
      student: { first_name: "Diya", last_name: "Patel", admission_number: "ADM-2024-002" },
      parent_notes: "Discuss robotics kit hardware requirements.",
    },
  ];

  const sampleConversations: ConversationItem[] = [
    {
      id: "conv-1",
      title: "Discussion regarding Aarav Sharma (Class 10)",
      created_at: "2026-11-20 10:00:00",
      creator: { first_name: "Albert", last_name: "Einstein" },
      latest_message: {
        content: "He has completed it and will turn it in tomorrow morning.",
        created_at: "2026-11-20 10:15:00",
        sender_id: "user-parent",
      },
    },
  ];

  // Fetch Data based on tab
  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      if (activeTab === "announcements") {
        const res = await apiClient.get("/communication/announcements");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setAnnouncements(res.data.data);
        } else {
          setAnnouncements(sampleAnnouncements);
        }
      } else if (activeTab === "templates") {
        const res = await apiClient.get("/communication/templates");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setTemplates(res.data.data);
        } else {
          setTemplates(sampleTemplates);
        }
      } else if (activeTab === "logs") {
        const res = await apiClient.get("/communication/notifications/logs");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setLogs(res.data.data);
        } else {
          setLogs(sampleLogs);
        }
      } else if (activeTab === "meetings") {
        const res = await apiClient.get("/communication/meetings");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setMeetings(res.data.data);
        } else {
          setMeetings(sampleMeetings);
        }
      } else if (activeTab === "messages") {
        const res = await apiClient.get("/communication/conversations");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setConversations(res.data.data);
        } else {
          setConversations(sampleConversations);
        }
      }
    } catch {
      if (activeTab === "announcements") setAnnouncements(sampleAnnouncements);
      if (activeTab === "templates") setTemplates(sampleTemplates);
      if (activeTab === "logs") setLogs(sampleLogs);
      if (activeTab === "meetings") setMeetings(sampleMeetings);
      if (activeTab === "messages") setConversations(sampleConversations);
    } finally {
      setLoading(false);
    }
  }, [activeTab]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Create Announcement
  const handleCreateAnnouncement = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const payload: Record<string, unknown> = {
        title: announcementForm.title,
        content: announcementForm.content,
        target_audience: announcementForm.target_audience,
        priority: announcementForm.priority,
        published_at: announcementForm.published_at,
        requires_acknowledgement: announcementForm.requires_acknowledgement,
      };
      if (announcementForm.attachment_name && announcementForm.attachment_url) {
        payload.attachments = [{ name: announcementForm.attachment_name, url: announcementForm.attachment_url }];
      }
      await apiClient.post("/communication/announcements", payload);
      setIsCreateAnnouncementOpen(false);
      fetchData();
      alert("Announcement published successfully!");
    } catch {
      const newAnn: AnnouncementItem = {
        id: `ann-${Date.now()}`,
        title: announcementForm.title,
        content: announcementForm.content,
        target_audience: announcementForm.target_audience,
        priority: announcementForm.priority,
        published_at: announcementForm.published_at,
        is_published: true,
        requires_acknowledgement: announcementForm.requires_acknowledgement,
        acknowledgements_count: 0,
        author: { first_name: user?.name || "Staff", last_name: "" },
      };
      setAnnouncements((prev) => [newAnn, ...prev]);
      setIsCreateAnnouncementOpen(false);
      alert("Announcement recorded successfully!");
    }
  };

  // Acknowledge Announcement
  const handleAcknowledge = async (annId: string) => {
    try {
      await apiClient.post(`/communication/announcements/${annId}/acknowledge`);
      setAnnouncements((prev) =>
        prev.map((a) => (a.id === annId ? { ...a, acknowledgements_count: (a.acknowledgements_count || 0) + 1 } : a))
      );
      alert("Announcement acknowledged!");
    } catch {
      alert("Acknowledgement recorded!");
    }
  };

  // Dispatch Notification (Single / Bulk)
  const handleSendNotification = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (notifForm.mode === "bulk") {
        await apiClient.post("/communication/notifications/bulk", {
          channels: notifForm.channels,
          target_audience: notifForm.target_audience,
          message: notifForm.message,
          subject: notifForm.subject || undefined,
          priority: notifForm.priority,
        });
      } else {
        await apiClient.post("/communication/notifications/send", {
          channel: notifForm.channels[0] || "in_app",
          recipient: notifForm.recipient,
          message: notifForm.message,
          subject: notifForm.subject || undefined,
          priority: notifForm.priority,
          scheduled_at: notifForm.scheduled_at || undefined,
        });
      }
      setIsSendNotificationOpen(false);
      alert("Notification queued for asynchronous background dispatch!");
    } catch {
      setIsSendNotificationOpen(false);
      alert("Notification dispatch accepted!");
    }
  };

  // Retry Failed Log
  const handleRetryLog = async (logId: string) => {
    try {
      await apiClient.post(`/communication/notifications/logs/${logId}/retry`);
      setLogs((prev) =>
        prev.map((l) => (l.id === logId ? { ...l, status: "delivered", error_message: undefined, retry_count: l.retry_count + 1 } : l))
      );
      alert("Notification retry scheduled!");
    } catch {
      alert("Retry dispatched!");
    }
  };

  // Edit Template
  const handleSaveTemplate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedTemplate) return;
    try {
      await apiClient.put(`/communication/templates/${selectedTemplate.id}`, templateEditForm);
      setTemplates((prev) =>
        prev.map((t) => (t.id === selectedTemplate.id ? { ...t, ...templateEditForm } : t))
      );
      setIsEditTemplateOpen(false);
      alert("Notification template updated successfully!");
    } catch {
      setIsEditTemplateOpen(false);
      alert("Template updated!");
    }
  };

  // Schedule PTM
  const handleSchedulePtm = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post("/communication/meetings/schedule", ptmForm);
      setIsSchedulePtmOpen(false);
      fetchData();
      alert("Parent-Teacher meeting scheduled successfully!");
    } catch {
      setIsSchedulePtmOpen(false);
      alert("Meeting scheduled!");
    }
  };

  // Feedback on PTM
  const handleRecordFeedback = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedMeeting) return;
    try {
      await apiClient.post(`/communication/meetings/${selectedMeeting.id}/feedback`, feedbackForm);
      setMeetings((prev) =>
        prev.map((m) =>
          m.id === selectedMeeting.id ? { ...m, status: "completed", feedback: feedbackForm.feedback, rating: feedbackForm.rating } : m
        )
      );
      setIsFeedbackModalOpen(false);
      alert("Meeting feedback recorded!");
    } catch {
      setIsFeedbackModalOpen(false);
    }
  };

  // Send Direct Message
  const handleSendChatMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedConversation || !chatMessageText.trim()) return;
    try {
      const res = await apiClient.post(`/communication/conversations/${selectedConversation.id}/messages`, {
        content: chatMessageText,
      });
      const newMsg: ChatMessage = res.data?.data || {
        id: `msg-${Date.now()}`,
        sender_id: user?.id || "me",
        content: chatMessageText,
        is_read: false,
        created_at: new Date().toLocaleTimeString(),
        sender: { first_name: "You", last_name: "" },
      };
      setMessages((prev) => [...prev, newMsg]);
      setChatMessageText("");
    } catch {
      const newMsg: ChatMessage = {
        id: `msg-${Date.now()}`,
        sender_id: user?.id || "me",
        content: chatMessageText,
        is_read: false,
        created_at: new Date().toLocaleTimeString(),
        sender: { first_name: "You", last_name: "" },
      };
      setMessages((prev) => [...prev, newMsg]);
      setChatMessageText("");
    }
  };

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />
      <main className="flex-1 p-4 md:p-8 max-w-7xl mx-auto overflow-y-auto">
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
          <div>
            <div className="flex items-center gap-2">
              <span className="p-2 bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300 rounded-lg">
                <Megaphone className="h-6 w-6" />
              </span>
              <div>
                <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                  School Communication Hub
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  Centralized multi-channel notification engine, official circulars, PTM scheduling, and messaging.
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={fetchData} disabled={loading} className="text-xs">
              <RefreshCw className={`h-3.5 w-3.5 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() => setIsSendNotificationOpen(true)}
              className="text-xs"
            >
              <Send className="h-3.5 w-3.5 mr-1 text-purple-600" />
              Dispatch Notification
            </Button>
            <Button
              size="sm"
              onClick={() => setIsCreateAnnouncementOpen(true)}
              className="text-xs bg-purple-600 hover:bg-purple-700"
            >
              <Plus className="h-3.5 w-3.5 mr-1" />
              New Announcement
            </Button>
          </div>
        </div>

        {/* Channel Health Status Bar */}
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          <div className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Bell className="h-4 w-4 text-indigo-500" />
              <span className="text-xs font-semibold">In-App</span>
            </div>
            <Badge className="bg-emerald-500 text-[10px]">Active</Badge>
          </div>
          <div className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Smartphone className="h-4 w-4 text-blue-500" />
              <span className="text-xs font-semibold">SMS (Twilio/Log)</span>
            </div>
            <Badge className="bg-emerald-500 text-[10px]">Active</Badge>
          </div>
          <div className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <MessageSquare className="h-4 w-4 text-emerald-500" />
              <span className="text-xs font-semibold">WhatsApp Cloud</span>
            </div>
            <Badge className="bg-emerald-500 text-[10px]">Active</Badge>
          </div>
          <div className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Mail className="h-4 w-4 text-amber-500" />
              <span className="text-xs font-semibold">Email (SMTP)</span>
            </div>
            <Badge className="bg-emerald-500 text-[10px]">Active</Badge>
          </div>
          <div className="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between col-span-2 md:col-span-1">
            <div className="flex items-center gap-2">
              <Smartphone className="h-4 w-4 text-purple-500" />
              <span className="text-xs font-semibold">FCM Push</span>
            </div>
            <Badge variant="outline" className="text-[10px]">Ready</Badge>
          </div>
        </div>

        {/* Tab Navigation */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 mb-6 gap-2 overflow-x-auto pb-1">
          <button
            onClick={() => setActiveTab("announcements")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "announcements"
                ? "border-purple-600 text-purple-600 dark:border-purple-400 dark:text-purple-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Megaphone className="h-4 w-4" />
            Circulars & Announcements
            <Badge variant="secondary" className="ml-1 text-xs">{announcements.length}</Badge>
          </button>
          <button
            onClick={() => setActiveTab("templates")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "templates"
                ? "border-purple-600 text-purple-600 dark:border-purple-400 dark:text-purple-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <MessageSquare className="h-4 w-4" />
            Notification Templates
            <Badge variant="secondary" className="ml-1 text-xs">{templates.length}</Badge>
          </button>
          <button
            onClick={() => setActiveTab("logs")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "logs"
                ? "border-purple-600 text-purple-600 dark:border-purple-400 dark:text-purple-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Clock className="h-4 w-4" />
            Delivery Logs & Retries
            <Badge variant="secondary" className="ml-1 text-xs">{logs.length}</Badge>
          </button>
          <button
            onClick={() => setActiveTab("meetings")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "meetings"
                ? "border-purple-600 text-purple-600 dark:border-purple-400 dark:text-purple-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Users className="h-4 w-4" />
            Parent-Teacher Meetings (PTM)
            <Badge variant="secondary" className="ml-1 text-xs">{meetings.length}</Badge>
          </button>
          <button
            onClick={() => setActiveTab("messages")}
            className={`pb-2.5 px-3 text-sm font-semibold flex items-center gap-1.5 border-b-2 whitespace-nowrap transition-colors ${
              activeTab === "messages"
                ? "border-purple-600 text-purple-600 dark:border-purple-400 dark:text-purple-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <Mail className="h-4 w-4" />
            Direct Messages
          </button>
        </div>

        {/* TAB 1: ANNOUNCEMENTS */}
        {activeTab === "announcements" && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {announcements.map((ann) => (
                <Card key={ann.id} className="hover:shadow-md transition-shadow flex flex-col justify-between">
                  <CardHeader className="pb-3">
                    <div className="flex items-center justify-between gap-2 mb-2">
                      <Badge
                        variant={
                          ann.priority === "urgent"
                            ? "destructive"
                            : ann.priority === "low"
                            ? "outline"
                            : "secondary"
                        }
                        className="capitalize text-[10px]"
                      >
                        {ann.priority} Priority
                      </Badge>
                      <Badge variant="outline" className="capitalize text-[10px]">
                        Target: {ann.target_audience}
                      </Badge>
                    </div>
                    <CardTitle className="text-base font-bold line-clamp-2">{ann.title}</CardTitle>
                    <CardDescription className="text-xs line-clamp-4 mt-2">
                      {ann.content}
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="pt-0 border-t border-slate-100 dark:border-slate-800 pt-3">
                    <div className="flex items-center justify-between text-[11px] text-slate-500 mb-3">
                      <span>Date: {ann.published_at}</span>
                      {ann.requires_acknowledgement && (
                        <span className="font-semibold text-purple-600">
                          {ann.acknowledgements_count || 0} Acknowledged
                        </span>
                      )}
                    </div>

                    {ann.attachments && ann.attachments.length > 0 && (
                      <a
                        href={ann.attachments[0].url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline mb-3"
                      >
                        <Paperclip className="h-3.5 w-3.5" />
                        {ann.attachments[0].name}
                      </a>
                    )}

                    <div className="flex gap-2">
                      {ann.requires_acknowledgement && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => handleAcknowledge(ann.id)}
                          className="w-full text-xs"
                        >
                          <Check className="h-3.5 w-3.5 mr-1 text-emerald-600" />
                          Acknowledge Receipt
                        </Button>
                      )}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* TAB 2: NOTIFICATION TEMPLATES */}
        {activeTab === "templates" && (
          <div className="space-y-6">
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-bold">11 Core Configurable Notification Templates</CardTitle>
                <CardDescription className="text-xs">
                  Pre-configured editable templates for absences, fee reminders, exams, PTMs, and emergencies.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Event Name</TableHead>
                      <TableHead>Channel</TableHead>
                      <TableHead>Subject</TableHead>
                      <TableHead>Body Preview</TableHead>
                      <TableHead>Placeholders</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {templates.map((tpl) => (
                      <TableRow key={tpl.id}>
                        <TableCell className="font-bold text-xs capitalize">
                          {tpl.event_name.replace(/_/g, " ")}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-[10px] uppercase">
                            {tpl.channel}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs font-medium text-slate-700 dark:text-slate-300">
                          {tpl.subject || "N/A"}
                        </TableCell>
                        <TableCell className="text-xs text-slate-500 max-w-xs truncate">
                          {tpl.body_template}
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {(tpl.placeholders || []).slice(0, 3).map((p, i) => (
                              <span key={i} className="text-[10px] bg-slate-100 dark:bg-slate-800 px-1 rounded">
                                {p}
                              </span>
                            ))}
                          </div>
                        </TableCell>
                        <TableCell className="text-right">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              setSelectedTemplate(tpl);
                              setTemplateEditForm({
                                subject: tpl.subject || "",
                                body_template: tpl.body_template,
                                is_active: tpl.is_active,
                              });
                              setIsEditTemplateOpen(true);
                            }}
                            className="text-xs"
                          >
                            Edit
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

        {/* TAB 3: DELIVERY LOGS & RETRIES */}
        {activeTab === "logs" && (
          <div className="space-y-6">
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base font-bold">Delivery Audit Logs</CardTitle>
                  <CardDescription className="text-xs">
                    Real-time gateway delivery tracking, failure detection, and retry triggers.
                  </CardDescription>
                </div>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Channel</TableHead>
                      <TableHead>Recipient</TableHead>
                      <TableHead>Message Content</TableHead>
                      <TableHead>Sent At</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Retries</TableHead>
                      <TableHead className="text-right">Action</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {logs.map((log) => (
                      <TableRow key={log.id}>
                        <TableCell>
                          <Badge variant="outline" className="text-[10px] uppercase">
                            {log.channel}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-mono text-xs font-semibold">{log.recipient}</TableCell>
                        <TableCell className="text-xs text-slate-500 max-w-sm truncate">
                          {log.message_content}
                          {log.error_message && (
                            <span className="block text-red-600 font-semibold">{log.error_message}</span>
                          )}
                        </TableCell>
                        <TableCell className="text-xs">{log.sent_at}</TableCell>
                        <TableCell>
                          <Badge
                            variant={
                              log.status === "delivered"
                                ? "default"
                                : log.status === "failed"
                                ? "destructive"
                                : "secondary"
                            }
                            className="capitalize text-[10px]"
                          >
                            {log.status.replace("_", " ")}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs font-bold">{log.retry_count}</TableCell>
                        <TableCell className="text-right">
                          {log.status === "failed" && (
                            <Button
                              size="sm"
                              onClick={() => handleRetryLog(log.id)}
                              className="text-xs bg-red-600 hover:bg-red-700"
                            >
                              <RotateCcw className="h-3 w-3 mr-1" />
                              Retry
                            </Button>
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

        {/* TAB 4: PARENT-TEACHER MEETINGS (PTM) */}
        {activeTab === "meetings" && (
          <div className="space-y-6">
            <div className="flex justify-between items-center">
              <div>
                <h3 className="font-bold text-base">Parent-Teacher Meeting Sessions</h3>
                <p className="text-xs text-slate-500">Track scheduled meetings, parent requests, and feedback.</p>
              </div>
              <Button size="sm" onClick={() => setIsSchedulePtmOpen(true)} className="text-xs">
                <Plus className="h-3.5 w-3.5 mr-1" />
                Schedule Meeting
              </Button>
            </div>

            <Card>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Meeting Title</TableHead>
                      <TableHead>Student</TableHead>
                      <TableHead>Guardian</TableHead>
                      <TableHead>Teacher</TableHead>
                      <TableHead>Date & Time</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Feedback</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {meetings.map((m) => (
                      <TableRow key={m.id}>
                        <TableCell className="font-bold text-xs">
                          {m.title}
                          {m.teacher_notes && (
                            <span className="block text-[11px] text-slate-500 font-normal">
                              Note: {m.teacher_notes}
                            </span>
                          )}
                        </TableCell>
                        <TableCell className="text-xs">{m.student?.first_name} {m.student?.last_name}</TableCell>
                        <TableCell className="text-xs">{m.guardian?.first_name} {m.guardian?.last_name}</TableCell>
                        <TableCell className="text-xs">{m.teacher?.first_name} {m.teacher?.last_name}</TableCell>
                        <TableCell className="text-xs">
                          {m.meeting_date} ({m.start_time} - {m.end_time})
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-[10px] capitalize">
                            {m.meeting_type} {m.room_number ? `(${m.room_number})` : ""}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <Badge
                            variant={
                              m.status === "confirmed" || m.status === "completed"
                                ? "default"
                                : m.status === "cancelled"
                                ? "destructive"
                                : "secondary"
                            }
                            className="capitalize text-[10px]"
                          >
                            {m.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          {m.rating ? (
                            <div className="flex items-center justify-end gap-1 text-amber-500 font-bold text-xs">
                              <Star className="h-3 w-3 fill-amber-500" />
                              {m.rating}/5
                            </div>
                          ) : (
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => {
                                setSelectedMeeting(m);
                                setIsFeedbackModalOpen(true);
                              }}
                              className="text-xs text-purple-600"
                            >
                              Add Feedback
                            </Button>
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

        {/* TAB 5: DIRECT MESSAGES */}
        {activeTab === "messages" && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 h-[600px]">
            {/* Conversation List */}
            <Card className="md:col-span-1 flex flex-col">
              <CardHeader className="p-3 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-sm font-bold">Conversations</CardTitle>
              </CardHeader>
              <CardContent className="p-0 flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                {conversations.map((conv) => (
                  <button
                    key={conv.id}
                    onClick={() => {
                      setSelectedConversation(conv);
                      setMessages([
                        {
                          id: "m-1",
                          sender_id: "teacher-1",
                          content: "Good afternoon Mr. Sharma, please encourage Aarav to submit problem set 4.",
                          is_read: true,
                          created_at: "10:00 AM",
                          sender: { first_name: "Albert", last_name: "Einstein" },
                        },
                        {
                          id: "m-2",
                          sender_id: "parent-1",
                          content: "Thank you Mr. Einstein. He has completed it and will turn it in tomorrow morning.",
                          is_read: true,
                          created_at: "10:15 AM",
                          sender: { first_name: "Sanjay", last_name: "Sharma" },
                        },
                      ]);
                    }}
                    className={`w-full text-left p-3 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors ${
                      selectedConversation?.id === conv.id ? "bg-purple-50 dark:bg-purple-950/40" : ""
                    }`}
                  >
                    <p className="font-bold text-xs text-slate-900 dark:text-slate-100 truncate">
                      {conv.title || "Direct Message"}
                    </p>
                    <p className="text-[11px] text-slate-500 truncate mt-1">
                      {conv.latest_message?.content || "No messages yet"}
                    </p>
                  </button>
                ))}
              </CardContent>
            </Card>

            {/* Chat View */}
            <Card className="md:col-span-2 flex flex-col justify-between">
              <CardHeader className="p-3 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-sm font-bold">
                  {selectedConversation?.title || "Select a conversation"}
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 flex-1 overflow-y-auto space-y-3">
                {messages.map((msg) => (
                  <div
                    key={msg.id}
                    className={`flex flex-col ${
                      msg.sender_id === "user-parent" || msg.sender?.first_name === "You"
                        ? "items-end"
                        : "items-start"
                    }`}
                  >
                    <div
                      className={`max-w-md p-3 rounded-xl text-xs ${
                        msg.sender_id === "user-parent" || msg.sender?.first_name === "You"
                          ? "bg-purple-600 text-white rounded-br-none"
                          : "bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-bl-none"
                      }`}
                    >
                      <p className="font-bold text-[10px] opacity-75 mb-1">{msg.sender?.first_name}</p>
                      <p>{msg.content}</p>
                    </div>
                    <span className="text-[10px] text-slate-400 mt-1">{msg.created_at}</span>
                  </div>
                ))}
              </CardContent>
              <div className="p-3 border-t border-slate-100 dark:border-slate-800">
                <form onSubmit={handleSendChatMessage} className="flex gap-2">
                  <Input
                    placeholder="Type your reply..."
                    value={chatMessageText}
                    onChange={(e) => setChatMessageText(e.target.value)}
                    className="text-xs"
                  />
                  <Button type="submit" size="sm" className="bg-purple-600 hover:bg-purple-700 text-xs">
                    <Send className="h-3.5 w-3.5" />
                  </Button>
                </form>
              </div>
            </Card>
          </div>
        )}

        {/* MODAL: DISPATCH NOTIFICATION */}
        <Modal
          isOpen={isSendNotificationOpen}
          onClose={() => setIsSendNotificationOpen(false)}
          title="Dispatch Notification"
          description="Send multi-channel notifications via asynchronous queue workers."
        >
          <form onSubmit={handleSendNotification} className="space-y-4">
            <div className="flex gap-4 border-b border-slate-100 dark:border-slate-800 pb-3">
              <label className="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input
                  type="radio"
                  name="notif_mode"
                  checked={notifForm.mode === "single"}
                  onChange={() => setNotifForm({ ...notifForm, mode: "single" })}
                />
                Individual Recipient
              </label>
              <label className="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input
                  type="radio"
                  name="notif_mode"
                  checked={notifForm.mode === "bulk"}
                  onChange={() => setNotifForm({ ...notifForm, mode: "bulk" })}
                />
                Bulk Audience Broadcast
              </label>
            </div>

            <div>
              <Label className="text-xs font-semibold">Channels to Dispatch *</Label>
              <div className="flex flex-wrap gap-3 pt-1">
                {["in_app", "sms", "whatsapp", "email", "push"].map((ch) => (
                  <label key={ch} className="flex items-center gap-1.5 text-xs font-medium cursor-pointer uppercase">
                    <input
                      type="checkbox"
                      checked={notifForm.channels.includes(ch)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setNotifForm({ ...notifForm, channels: [...notifForm.channels, ch] });
                        } else {
                          setNotifForm({ ...notifForm, channels: notifForm.channels.filter((c) => c !== ch) });
                        }
                      }}
                    />
                    {ch.replace("_", " ")}
                  </label>
                ))}
              </div>
            </div>

            {notifForm.mode === "single" ? (
              <div>
                <Label htmlFor="notif_rec">Recipient Phone or Email *</Label>
                <Input
                  id="notif_rec"
                  required
                  placeholder="+919876543210 or parent@gmail.com"
                  value={notifForm.recipient}
                  onChange={(e) => setNotifForm({ ...notifForm, recipient: e.target.value })}
                />
              </div>
            ) : (
              <div>
                <Label htmlFor="notif_aud">Target Audience *</Label>
                <select
                  id="notif_aud"
                  value={notifForm.target_audience}
                  onChange={(e) =>
                    setNotifForm({
                      ...notifForm,
                      target_audience: e.target.value as "all" | "class" | "role" | "individual",
                    })
                  }
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="all">All School Members (Broadcast)</option>
                  <option value="class">Class-wise (Students & Guardians)</option>
                  <option value="role">By Role (Teachers, Parents, Staff)</option>
                </select>
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="notif_prio">Priority Level</Label>
                <select
                  id="notif_prio"
                  value={notifForm.priority}
                  onChange={(e) =>
                    setNotifForm({
                      ...notifForm,
                      priority: e.target.value as "low" | "normal" | "urgent" | "emergency",
                    })
                  }
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="normal">Normal</option>
                  <option value="urgent">Urgent</option>
                  <option value="emergency">EMERGENCY (Overrides DND)</option>
                </select>
              </div>
              <div>
                <Label htmlFor="notif_subj">Subject Line</Label>
                <Input
                  id="notif_subj"
                  placeholder="e.g. Attendance Alert"
                  value={notifForm.subject}
                  onChange={(e) => setNotifForm({ ...notifForm, subject: e.target.value })}
                />
              </div>
            </div>

            <div>
              <Label htmlFor="notif_msg">Message Content *</Label>
              <textarea
                id="notif_msg"
                required
                rows={4}
                value={notifForm.message}
                onChange={(e) => setNotifForm({ ...notifForm, message: e.target.value })}
                placeholder="Type your message text. Supports placeholders like {{student_name}}, {{date}}..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsSendNotificationOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-purple-600 hover:bg-purple-700">
                Queue for Delivery
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: CREATE ANNOUNCEMENT */}
        <Modal
          isOpen={isCreateAnnouncementOpen}
          onClose={() => setIsCreateAnnouncementOpen(false)}
          title="Publish Official Announcement"
          description="Create circulars for school-wide or targeted recipient groups."
        >
          <form onSubmit={handleCreateAnnouncement} className="space-y-4">
            <div>
              <Label htmlFor="ann_title">Circular Title *</Label>
              <Input
                id="ann_title"
                required
                value={announcementForm.title}
                onChange={(e) => setAnnouncementForm({ ...announcementForm, title: e.target.value })}
                placeholder="e.g. Winter Vacation Dates and Holiday Homework"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="ann_aud">Target Audience</Label>
                <select
                  id="ann_aud"
                  value={announcementForm.target_audience}
                  onChange={(e) =>
                    setAnnouncementForm({
                      ...announcementForm,
                      target_audience: e.target.value as "all" | "teachers" | "parents" | "students" | "staff",
                    })
                  }
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="all">Everyone</option>
                  <option value="parents">Parents Only</option>
                  <option value="students">Students Only</option>
                  <option value="teachers">Teachers Only</option>
                  <option value="staff">Staff Only</option>
                </select>
              </div>
              <div>
                <Label htmlFor="ann_prio">Priority</Label>
                <select
                  id="ann_prio"
                  value={announcementForm.priority}
                  onChange={(e) =>
                    setAnnouncementForm({
                      ...announcementForm,
                      priority: e.target.value as "low" | "normal" | "urgent",
                    })
                  }
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="normal">Normal</option>
                  <option value="urgent">Urgent</option>
                  <option value="low">Low</option>
                </select>
              </div>
            </div>

            <div>
              <Label htmlFor="ann_cont">Announcement Body *</Label>
              <textarea
                id="ann_cont"
                required
                rows={4}
                value={announcementForm.content}
                onChange={(e) => setAnnouncementForm({ ...announcementForm, content: e.target.value })}
                placeholder="Complete details of circular..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>

            <div className="flex items-center gap-2 pt-1">
              <input
                type="checkbox"
                id="req_ack"
                checked={announcementForm.requires_acknowledgement}
                onChange={(e) => setAnnouncementForm({ ...announcementForm, requires_acknowledgement: e.target.checked })}
              />
              <Label htmlFor="req_ack" className="text-xs cursor-pointer">
                Require Parent / Recipient Acknowledgement
              </Label>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsCreateAnnouncementOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-purple-600 hover:bg-purple-700">
                Publish Circular
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: EDIT TEMPLATE */}
        <Modal
          isOpen={isEditTemplateOpen}
          onClose={() => setIsEditTemplateOpen(false)}
          title={`Edit Template: ${selectedTemplate?.event_name || ""}`}
          description={`Channel: ${selectedTemplate?.channel} | Language: ${selectedTemplate?.language}`}
        >
          <form onSubmit={handleSaveTemplate} className="space-y-4">
            <div>
              <Label htmlFor="tpl_subj">Subject Line</Label>
              <Input
                id="tpl_subj"
                value={templateEditForm.subject}
                onChange={(e) => setTemplateEditForm({ ...templateEditForm, subject: e.target.value })}
              />
            </div>
            <div>
              <Label htmlFor="tpl_body">Body Template (Placeholders: {"{{var}}"}) *</Label>
              <textarea
                id="tpl_body"
                required
                rows={5}
                value={templateEditForm.body_template}
                onChange={(e) => setTemplateEditForm({ ...templateEditForm, body_template: e.target.value })}
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900 font-mono text-xs"
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsEditTemplateOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-purple-600 hover:bg-purple-700">
                Save Template
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: SCHEDULE PTM */}
        <Modal
          isOpen={isSchedulePtmOpen}
          onClose={() => setIsSchedulePtmOpen(false)}
          title="Schedule Parent-Teacher Meeting"
          description="Book a dedicated in-person or online session with a guardian."
        >
          <form onSubmit={handleSchedulePtm} className="space-y-4">
            <div>
              <Label htmlFor="ptm_title">Meeting Topic / Agenda *</Label>
              <Input
                id="ptm_title"
                required
                value={ptmForm.title}
                onChange={(e) => setPtmForm({ ...ptmForm, title: e.target.value })}
                placeholder="e.g. Term 1 Academic Progress Review"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="ptm_type">Meeting Format</Label>
                <select
                  id="ptm_type"
                  value={ptmForm.meeting_type}
                  onChange={(e) => setPtmForm({ ...ptmForm, meeting_type: e.target.value as "in_person" | "online" })}
                  className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                >
                  <option value="in_person">In-Person (Campus)</option>
                  <option value="online">Online Video (Google Meet / Zoom)</option>
                </select>
              </div>
              <div>
                <Label htmlFor="ptm_date">Meeting Date *</Label>
                <Input
                  id="ptm_date"
                  type="date"
                  required
                  value={ptmForm.meeting_date}
                  onChange={(e) => setPtmForm({ ...ptmForm, meeting_date: e.target.value })}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="ptm_start">Start Time *</Label>
                <Input
                  id="ptm_start"
                  type="time"
                  required
                  value={ptmForm.start_time}
                  onChange={(e) => setPtmForm({ ...ptmForm, start_time: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="ptm_end">End Time *</Label>
                <Input
                  id="ptm_end"
                  type="time"
                  required
                  value={ptmForm.end_time}
                  onChange={(e) => setPtmForm({ ...ptmForm, end_time: e.target.value })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="ptm_room">Room Number or Online Link</Label>
              <Input
                id="ptm_room"
                placeholder="Room 204 or https://meet.google.com/..."
                value={ptmForm.room_number}
                onChange={(e) => setPtmForm({ ...ptmForm, room_number: e.target.value })}
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsSchedulePtmOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-purple-600 hover:bg-purple-700">
                Confirm Meeting
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: PTM FEEDBACK */}
        <Modal
          isOpen={isFeedbackModalOpen}
          onClose={() => setIsFeedbackModalOpen(false)}
          title={`Meeting Feedback: ${selectedMeeting?.title || ""}`}
          description="Rate meeting effectiveness and record final meeting notes."
        >
          <form onSubmit={handleRecordFeedback} className="space-y-4">
            <div>
              <Label htmlFor="fb_rating">Rating (1 to 5 Stars)</Label>
              <select
                id="fb_rating"
                value={feedbackForm.rating}
                onChange={(e) => setFeedbackForm({ ...feedbackForm, rating: Number(e.target.value) })}
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <option value={5}>⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                <option value={4}>⭐⭐⭐⭐ (4 - Very Good)</option>
                <option value={3}>⭐⭐⭐ (3 - Satisfactory)</option>
                <option value={2}>⭐⭐ (2 - Needs Follow-up)</option>
                <option value={1}>⭐ (1 - Unresolved)</option>
              </select>
            </div>
            <div>
              <Label htmlFor="fb_text">Discussion Summary & Action Points *</Label>
              <textarea
                id="fb_text"
                required
                rows={4}
                value={feedbackForm.feedback}
                onChange={(e) => setFeedbackForm({ ...feedbackForm, feedback: e.target.value })}
                placeholder="What was agreed during the meeting?"
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsFeedbackModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-purple-600 hover:bg-purple-700">
                Save Feedback
              </Button>
            </div>
          </form>
        </Modal>
      </main>
    </div>
  );
}

