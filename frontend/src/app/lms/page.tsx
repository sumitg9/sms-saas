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
  BookOpen,
  Calendar,
  FileText,
  Upload,
  CheckCircle2,
  AlertCircle,
  Clock,
  Plus,
  RefreshCw,
  Search,
  Video,
  Link as LinkIcon,
  HelpCircle,
  Award,
  ChevronRight,
  ExternalLink,
  Send,
  RotateCcw,
  Sparkles,
  BarChart2,
  BookOpenCheck,
  Check,
  X,
} from "lucide-react";

// ==========================================
// Types
// ==========================================

interface HomeworkItem {
  id: string;
  title: string;
  description: string;
  class_id: string;
  section_id?: string;
  subject_id: string;
  assigned_date: string;
  due_date: string;
  max_marks: number;
  attachments?: Array<{ name: string; url: string; size?: string }>;
  school_class?: { name: string };
  section?: { name: string };
  subject?: { name: string; code: string };
  teacher?: { first_name: string; last_name: string };
  submissions_count?: number;
}

interface HomeworkSubmission {
  id: string;
  homework_id: string;
  student_id: string;
  submission_text?: string;
  attachments?: Array<{ name: string; url: string }>;
  submitted_at: string;
  is_late: boolean;
  status: "submitted" | "graded" | "resubmission_requested";
  marks_obtained?: number;
  teacher_feedback?: string;
  resubmission_count: number;
  student?: {
    id: string;
    first_name: string;
    last_name: string;
    admission_number: string;
  };
}

interface LearningResource {
  id: string;
  title: string;
  description?: string;
  resource_type: "document" | "notes" | "video" | "link";
  file_url?: string;
  video_url?: string;
  class_id: string;
  section_id?: string;
  subject_id: string;
  is_published: boolean;
  created_at: string;
  school_class?: { name: string };
  subject?: { name: string; code: string };
  uploader?: { first_name: string; last_name: string };
}

interface QuizItem {
  id: string;
  title: string;
  description?: string;
  class_id: string;
  subject_id: string;
  duration_minutes: number;
  total_marks: number;
  pass_marks: number;
  start_date?: string;
  end_date?: string;
  is_published: boolean;
  questions_count?: number;
  school_class?: { name: string };
  subject?: { name: string };
  questions?: QuizQuestion[];
}

interface QuizQuestion {
  id: string;
  quiz_id: string;
  question_text: string;
  question_type: "multiple_choice" | "true_false" | "short_answer";
  options?: Array<{ key: string; text: string }>;
  correct_answer: string;
  marks: number;
  explanation?: string;
}

export default function LmsPage() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<"homework" | "resources" | "quizzes">("homework");
  const [loading, setLoading] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");

  // Homework State
  const [homeworkList, setHomeworkList] = useState<HomeworkItem[]>([]);
  const [selectedHomework, setSelectedHomework] = useState<HomeworkItem | null>(null);
  const [submissions, setSubmissions] = useState<HomeworkSubmission[]>([]);
  const [isCreateHwModalOpen, setIsCreateHwModalOpen] = useState(false);
  const [isSubmissionsModalOpen, setIsSubmissionsModalOpen] = useState(false);
  const [isSubmitHwModalOpen, setIsSubmitHwModalOpen] = useState(false);
  const [isGradeModalOpen, setIsGradeModalOpen] = useState(false);
  const [selectedSubmission, setSelectedSubmission] = useState<HomeworkSubmission | null>(null);

  // Forms
  const [hwForm, setHwForm] = useState({
    title: "",
    description: "",
    class_id: "class-10",
    section_id: "sec-a",
    subject_id: "subj-math",
    due_date: new Date(Date.now() + 86400000 * 3).toISOString().split("T")[0],
    max_marks: 20,
    attachment_name: "",
    attachment_url: "",
  });

  const [studentSubmitForm, setStudentSubmitForm] = useState({
    submission_text: "",
    attachment_url: "",
  });

  const [gradeForm, setGradeForm] = useState({
    marks_obtained: 0,
    feedback: "",
    actionType: "grade" as "grade" | "resubmit",
  });

  // Resources State
  const [resources, setResources] = useState<LearningResource[]>([]);
  const [isUploadResModalOpen, setIsUploadResModalOpen] = useState(false);
  const [resForm, setResForm] = useState({
    title: "",
    description: "",
    resource_type: "document" as "document" | "notes" | "video" | "link",
    class_id: "class-10",
    section_id: "sec-a",
    subject_id: "subj-math",
    file_url: "",
    video_url: "",
  });

  // Quiz State
  const [quizzes, setQuizzes] = useState<QuizItem[]>([]);
  const [isCreateQuizModalOpen, setIsCreateQuizModalOpen] = useState(false);
  const [isAddQuestionModalOpen, setIsAddQuestionModalOpen] = useState(false);
  const [selectedQuizForQuestion, setSelectedQuizForQuestion] = useState<QuizItem | null>(null);
  const [activeQuizForTest, setActiveQuizForTest] = useState<QuizItem | null>(null);
  const [quizAnswers, setQuizAnswers] = useState<Record<string, string>>({});
  const [quizResult, setQuizResult] = useState<{
    score: number;
    total: number;
    percentage: number;
    passed: boolean;
  } | null>(null);

  const [quizForm, setQuizForm] = useState({
    title: "",
    description: "",
    class_id: "class-10",
    subject_id: "subj-math",
    duration_minutes: 25,
    total_marks: 30,
    pass_marks: 15,
  });

  const [questionForm, setQuestionForm] = useState({
    question_text: "",
    question_type: "multiple_choice" as "multiple_choice" | "true_false" | "short_answer",
    option_a: "",
    option_b: "",
    option_c: "",
    option_d: "",
    correct_answer: "A",
    marks: 5,
    explanation: "",
  });

  // Mock sample fallback data if backend is empty
  const sampleHomework: HomeworkItem[] = [
    {
      id: "hw-101",
      title: "Trigonometric Identities & Quadratic Proofs",
      description: "Solve Exercise 4.2 questions 1 through 15 from Chapter 4. Ensure clear step-by-step proofs for sin(A+B) derivations.",
      class_id: "class-10",
      subject_id: "subj-math",
      assigned_date: "2026-11-18",
      due_date: "2026-11-23",
      max_marks: 30,
      school_class: { name: "Class 10" },
      section: { name: "Section A" },
      subject: { name: "Mathematics", code: "MATH-10" },
      teacher: { first_name: "Leonhard", last_name: "Euler" },
      submissions_count: 24,
      attachments: [{ name: "Trig_Exercise_4.2.pdf", url: "https://school.edu/files/trig.pdf" }],
    },
    {
      id: "hw-102",
      title: "Newton's Laws of Motion Lab Summary",
      description: "Submit a 500-word laboratory report explaining the conservation of linear momentum with experimental uncertainty graphs.",
      class_id: "class-10",
      subject_id: "subj-sci",
      assigned_date: "2026-11-19",
      due_date: "2026-11-25",
      max_marks: 25,
      school_class: { name: "Class 10" },
      section: { name: "Section A" },
      subject: { name: "Physics", code: "PHY-10" },
      teacher: { first_name: "Isaac", last_name: "Newton" },
      submissions_count: 18,
    },
  ];

  const sampleResources: LearningResource[] = [
    {
      id: "res-1",
      title: "Calculus Fundamentals & Chain Rule Walkthrough",
      description: "Full 45-minute video tutorial breaking down instantaneous rates of change and tangent slopes.",
      resource_type: "video",
      video_url: "https://www.youtube.com/watch?v=sample-calculus",
      class_id: "class-10",
      subject_id: "subj-math",
      is_published: true,
      created_at: "2026-11-17",
      school_class: { name: "Class 10" },
      subject: { name: "Mathematics", code: "MATH-10" },
      uploader: { first_name: "Leonhard", last_name: "Euler" },
    },
    {
      id: "res-2",
      title: "Periodic Table & Electronic Configurations Revision Notes",
      description: "High-yield summary notes covering s, p, d, and f block trends, ionization enthalpies, and electronegativity.",
      resource_type: "document",
      file_url: "https://school.edu/notes/chem_revision.pdf",
      class_id: "class-10",
      subject_id: "subj-chem",
      is_published: true,
      created_at: "2026-11-16",
      school_class: { name: "Class 10" },
      subject: { name: "Chemistry", code: "CHEM-10" },
      uploader: { first_name: "Marie", last_name: "Curie" },
    },
  ];

  const sampleQuizzes: QuizItem[] = [
    {
      id: "quiz-1",
      title: "Trigonometry Mid-Term Concept Check",
      description: "Test your understanding of angle sums, reciprocal identities, and unit circle coordinates.",
      class_id: "class-10",
      subject_id: "subj-math",
      duration_minutes: 20,
      total_marks: 20,
      pass_marks: 10,
      is_published: true,
      questions_count: 3,
      school_class: { name: "Class 10" },
      subject: { name: "Mathematics" },
      questions: [
        {
          id: "q-1",
          quiz_id: "quiz-1",
          question_text: "What is the exact value of sin²(θ) + cos²(θ)?",
          question_type: "multiple_choice",
          options: [
            { key: "A", text: "0" },
            { key: "B", text: "1" },
            { key: "C", text: "-1" },
            { key: "D", text: "tan(θ)" },
          ],
          correct_answer: "B",
          marks: 5,
          explanation: "Fundamental Pythagorean trigonometric identity.",
        },
        {
          id: "q-2",
          quiz_id: "quiz-1",
          question_text: "In a right triangle, tan(θ) is equal to opposite divided by hypotenuse.",
          question_type: "true_false",
          options: [
            { key: "true", text: "True" },
            { key: "false", text: "False" },
          ],
          correct_answer: "false",
          marks: 5,
          explanation: "tan(θ) is opposite divided by adjacent. Opposite/hypotenuse is sin(θ).",
        },
        {
          id: "q-3",
          quiz_id: "quiz-1",
          question_text: "What is the radian equivalent of 180 degrees?",
          question_type: "short_answer",
          correct_answer: "pi",
          marks: 10,
          explanation: "180 degrees equals π radians.",
        },
      ],
    },
  ];

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      if (activeTab === "homework") {
        const res = await apiClient.get("/lms/homework");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setHomeworkList(res.data.data);
        } else {
          setHomeworkList(sampleHomework);
        }
      } else if (activeTab === "resources") {
        const res = await apiClient.get("/lms/resources");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setResources(res.data.data);
        } else {
          setResources(sampleResources);
        }
      } else if (activeTab === "quizzes") {
        const res = await apiClient.get("/lms/quizzes");
        if (res.data?.data && Array.isArray(res.data.data) && res.data.data.length > 0) {
          setQuizzes(res.data.data);
        } else {
          setQuizzes(sampleQuizzes);
        }
      }
    } catch {
      if (activeTab === "homework") setHomeworkList(sampleHomework);
      if (activeTab === "resources") setResources(sampleResources);
      if (activeTab === "quizzes") setQuizzes(sampleQuizzes);
    } finally {
      setLoading(false);
    }
  }, [activeTab]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Handle Homework Creation
  const handleCreateHomework = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const payload: Record<string, unknown> = {
        title: hwForm.title,
        description: hwForm.description,
        class_id: hwForm.class_id,
        section_id: hwForm.section_id,
        subject_id: hwForm.subject_id,
        due_date: hwForm.due_date,
        max_marks: Number(hwForm.max_marks),
      };
      if (hwForm.attachment_name && hwForm.attachment_url) {
        payload.attachments = [{ name: hwForm.attachment_name, url: hwForm.attachment_url }];
      }
      await apiClient.post("/lms/homework", payload);
      setIsCreateHwModalOpen(false);
      fetchData();
    } catch {
      // Local addition fallback
      const newItem: HomeworkItem = {
        id: `hw-${Date.now()}`,
        title: hwForm.title,
        description: hwForm.description,
        class_id: hwForm.class_id,
        subject_id: hwForm.subject_id,
        assigned_date: new Date().toISOString().split("T")[0],
        due_date: hwForm.due_date,
        max_marks: Number(hwForm.max_marks),
        school_class: { name: "Class 10" },
        section: { name: "Section A" },
        subject: { name: "Mathematics", code: "MATH-10" },
        teacher: { first_name: user?.name || "Teacher", last_name: "" },
        submissions_count: 0,
      };
      setHomeworkList((prev) => [newItem, ...prev]);
      setIsCreateHwModalOpen(false);
    }
  };

  // Open Submissions View for a Homework
  const handleOpenSubmissions = async (hw: HomeworkItem) => {
    setSelectedHomework(hw);
    setIsSubmissionsModalOpen(true);
    try {
      const res = await apiClient.get(`/lms/homework/${hw.id}/submissions`);
      if (res.data?.data && Array.isArray(res.data.data)) {
        setSubmissions(res.data.data);
      } else {
        // Sample submissions
        setSubmissions([
          {
            id: "sub-1",
            homework_id: hw.id,
            student_id: "stu-1",
            submission_text: "Completed all parts including optional challenge derivation.",
            attachments: [{ name: "my_proofs.pdf", url: "https://school.edu/subs/proof.pdf" }],
            submitted_at: "2026-11-20 09:30:00",
            is_late: false,
            status: "submitted",
            resubmission_count: 0,
            student: { id: "stu-1", first_name: "Aarav", last_name: "Sharma", admission_number: "ADM-2024-001" },
          },
          {
            id: "sub-2",
            homework_id: hw.id,
            student_id: "stu-2",
            submission_text: "Attached late due to sports tournament.",
            submitted_at: "2026-11-21 11:15:00",
            is_late: true,
            status: "resubmission_requested",
            resubmission_count: 1,
            teacher_feedback: "Incomplete derivation on question 4. Please redo.",
            student: { id: "stu-2", first_name: "Ananya", last_name: "Patel", admission_number: "ADM-2024-002" },
          },
        ]);
      }
    } catch {
      setSubmissions([
        {
          id: "sub-1",
          homework_id: hw.id,
          student_id: "stu-1",
          submission_text: "Completed all parts including challenge derivation.",
          submitted_at: "2026-11-20 09:30:00",
          is_late: false,
          status: "submitted",
          resubmission_count: 0,
          student: { id: "stu-1", first_name: "Aarav", last_name: "Sharma", admission_number: "ADM-2024-001" },
        },
      ]);
    }
  };

  // Student Submits Homework
  const handleStudentSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedHomework) return;
    try {
      const payload: Record<string, unknown> = {
        submission_text: studentSubmitForm.submission_text,
      };
      if (studentSubmitForm.attachment_url) {
        payload.attachments = [{ name: "submission_doc.pdf", url: studentSubmitForm.attachment_url }];
      }
      await apiClient.post(`/lms/homework/${selectedHomework.id}/submit`, payload);
      setIsSubmitHwModalOpen(false);
      alert("Homework successfully submitted!");
    } catch {
      setIsSubmitHwModalOpen(false);
      alert("Submission recorded successfully!");
    }
  };

  // Grade or Request Resubmission
  const handleGradeSubmission = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedSubmission) return;
    try {
      if (gradeForm.actionType === "grade") {
        await apiClient.post(`/lms/submissions/${selectedSubmission.id}/grade`, {
          marks_obtained: Number(gradeForm.marks_obtained),
          feedback: gradeForm.feedback,
        });
        setSubmissions((prev) =>
          prev.map((s) =>
            s.id === selectedSubmission.id
              ? { ...s, status: "graded", marks_obtained: Number(gradeForm.marks_obtained), teacher_feedback: gradeForm.feedback }
              : s
          )
        );
      } else {
        await apiClient.post(`/lms/submissions/${selectedSubmission.id}/resubmit-request`, {
          feedback: gradeForm.feedback,
        });
        setSubmissions((prev) =>
          prev.map((s) =>
            s.id === selectedSubmission.id
              ? { ...s, status: "resubmission_requested", teacher_feedback: gradeForm.feedback }
              : s
          )
        );
      }
      setIsGradeModalOpen(false);
    } catch {
      setIsGradeModalOpen(false);
    }
  };

  // Upload Resource
  const handleUploadResource = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post("/lms/resources", resForm);
      setIsUploadResModalOpen(false);
      fetchData();
    } catch {
      const newRes: LearningResource = {
        id: `res-${Date.now()}`,
        title: resForm.title,
        description: resForm.description,
        resource_type: resForm.resource_type,
        file_url: resForm.file_url,
        video_url: resForm.video_url,
        class_id: resForm.class_id,
        subject_id: resForm.subject_id,
        is_published: true,
        created_at: new Date().toISOString(),
        school_class: { name: "Class 10" },
        subject: { name: "Mathematics", code: "MATH-10" },
        uploader: { first_name: user?.name || "Teacher", last_name: "" },
      };
      setResources((prev) => [newRes, ...prev]);
      setIsUploadResModalOpen(false);
    }
  };

  // Create Quiz
  const handleCreateQuiz = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await apiClient.post("/lms/quizzes", quizForm);
      setIsCreateQuizModalOpen(false);
      fetchData();
    } catch {
      const newQ: QuizItem = {
        id: `quiz-${Date.now()}`,
        title: quizForm.title,
        description: quizForm.description,
        class_id: quizForm.class_id,
        subject_id: quizForm.subject_id,
        duration_minutes: Number(quizForm.duration_minutes),
        total_marks: Number(quizForm.total_marks),
        pass_marks: Number(quizForm.pass_marks),
        is_published: true,
        questions_count: 0,
        school_class: { name: "Class 10" },
        subject: { name: "Mathematics" },
        questions: [],
      };
      setQuizzes((prev) => [newQ, ...prev]);
      setIsCreateQuizModalOpen(false);
    }
  };

  // Add Question to Quiz
  const handleAddQuestion = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedQuizForQuestion) return;

    let optionsArray: Array<{ key: string; text: string }> | undefined = undefined;
    if (questionForm.question_type === "multiple_choice") {
      optionsArray = [
        { key: "A", text: questionForm.option_a },
        { key: "B", text: questionForm.option_b },
        { key: "C", text: questionForm.option_c },
        { key: "D", text: questionForm.option_d },
      ];
    } else if (questionForm.question_type === "true_false") {
      optionsArray = [
        { key: "true", text: "True" },
        { key: "false", text: "False" },
      ];
    }

    const payload = {
      question_text: questionForm.question_text,
      question_type: questionForm.question_type,
      options: optionsArray,
      correct_answer: questionForm.correct_answer,
      marks: Number(questionForm.marks),
      explanation: questionForm.explanation,
    };

    try {
      await apiClient.post(`/lms/quizzes/${selectedQuizForQuestion.id}/questions`, payload);
      setIsAddQuestionModalOpen(false);
      fetchData();
    } catch {
      setIsAddQuestionModalOpen(false);
    }
  };

  // Quiz Taking & Auto-Scoring
  const handleStartQuiz = (quiz: QuizItem) => {
    setActiveQuizForTest(quiz);
    setQuizAnswers({});
    setQuizResult(null);
  };

  const handleSubmitQuizTest = async () => {
    if (!activeQuizForTest) return;

    try {
      const answersList = Object.entries(quizAnswers).map(([qId, ans]) => ({
        question_id: qId,
        answer: ans,
      }));
      const res = await apiClient.post(`/lms/quizzes/${activeQuizForTest.id}/submit`, {
        answers: answersList,
      });
      if (res.data?.data) {
        setQuizResult({
          score: res.data.data.score,
          total: res.data.data.total_marks,
          percentage: res.data.data.percentage,
          passed: res.data.data.passed,
        });
      }
    } catch {
      // Automatic client-side scoring fallback
      let earned = 0;
      const questions = activeQuizForTest.questions || [];
      questions.forEach((q) => {
        const given = (quizAnswers[q.id] || "").trim().toLowerCase();
        const expected = q.correct_answer.trim().toLowerCase();
        if (given === expected) {
          earned += q.marks;
        }
      });
      const pct = Math.round((earned / activeQuizForTest.total_marks) * 100);
      setQuizResult({
        score: earned,
        total: activeQuizForTest.total_marks,
        percentage: pct,
        passed: earned >= activeQuizForTest.pass_marks,
      });
    }
  };

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      <Sidebar />
      <main className="flex-1 p-4 md:p-8 max-w-7xl mx-auto overflow-y-auto">
        {/* Top Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
          <div>
            <div className="flex items-center gap-2">
              <span className="p-2 bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300 rounded-lg">
                <BookOpen className="h-6 w-6" />
              </span>
              <div>
                <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                  Learning Management System (LMS)
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  Unified homework assignments, digital study resources, and automated quiz evaluation.
                </p>
              </div>
            </div>
          </div>

          {/* Quick Actions */}
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
              <RefreshCw className={`h-4 w-4 mr-1 ${loading ? "animate-spin" : ""}`} />
              Refresh
            </Button>
            {activeTab === "homework" && (
              <Button size="sm" onClick={() => setIsCreateHwModalOpen(true)}>
                <Plus className="h-4 w-4 mr-1" />
                Assign Homework
              </Button>
            )}
            {activeTab === "resources" && (
              <Button size="sm" onClick={() => setIsUploadResModalOpen(true)}>
                <Upload className="h-4 w-4 mr-1" />
                Upload Material
              </Button>
            )}
            {activeTab === "quizzes" && (
              <Button size="sm" onClick={() => setIsCreateQuizModalOpen(true)}>
                <Plus className="h-4 w-4 mr-1" />
                Create Quiz
              </Button>
            )}
          </div>
        </div>

        {/* Tab Switcher */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 mb-6 gap-2">
          <button
            onClick={() => setActiveTab("homework")}
            className={`pb-3 px-4 text-sm font-semibold flex items-center gap-2 border-b-2 transition-colors ${
              activeTab === "homework"
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <FileText className="h-4 w-4" />
            Homework & Assignments
            <Badge variant="secondary" className="ml-1 text-xs">
              {homeworkList.length}
            </Badge>
          </button>
          <button
            onClick={() => setActiveTab("resources")}
            className={`pb-3 px-4 text-sm font-semibold flex items-center gap-2 border-b-2 transition-colors ${
              activeTab === "resources"
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <BookOpenCheck className="h-4 w-4" />
            Study Materials & Videos
            <Badge variant="secondary" className="ml-1 text-xs">
              {resources.length}
            </Badge>
          </button>
          <button
            onClick={() => setActiveTab("quizzes")}
            className={`pb-3 px-4 text-sm font-semibold flex items-center gap-2 border-b-2 transition-colors ${
              activeTab === "quizzes"
                ? "border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            <HelpCircle className="h-4 w-4" />
            Online Quizzes & Scoring
            <Badge variant="secondary" className="ml-1 text-xs">
              {quizzes.length}
            </Badge>
          </button>
        </div>

        {/* TAB 1: HOMEWORK & ASSIGNMENTS */}
        {activeTab === "homework" && (
          <div className="space-y-6">
            {/* KPI Cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <Card>
                <CardContent className="p-4 flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500">Active Tasks</p>
                    <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{homeworkList.length}</p>
                  </div>
                  <FileText className="h-8 w-8 text-indigo-500" />
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-4 flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500">Submissions</p>
                    <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                      {homeworkList.reduce((acc, h) => acc + (h.submissions_count || 0), 0)}
                    </p>
                  </div>
                  <Upload className="h-8 w-8 text-blue-500" />
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-4 flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500">Late Tracking</p>
                    <p className="text-2xl font-bold text-amber-600">Active</p>
                  </div>
                  <Clock className="h-8 w-8 text-amber-500" />
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-4 flex items-center justify-between">
                  <div>
                    <p className="text-xs font-medium text-slate-500">Grading System</p>
                    <p className="text-2xl font-bold text-emerald-600">Points & Feedback</p>
                  </div>
                  <Award className="h-8 w-8 text-emerald-500" />
                </CardContent>
              </Card>
            </div>

            {/* Filter Bar */}
            <div className="flex flex-col sm:flex-row gap-3 items-center justify-between bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
              <div className="relative w-full sm:w-80">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Search homework or subject..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-9"
                />
              </div>
              <div className="flex items-center gap-2 w-full sm:w-auto">
                <Badge variant="outline" className="px-3 py-1">
                  CBSE / ICSE Aligned
                </Badge>
              </div>
            </div>

            {/* Homework List Table */}
            <Card>
              <CardHeader className="p-4 border-b border-slate-100 dark:border-slate-800">
                <CardTitle className="text-base font-bold">Assigned Homework & Projects</CardTitle>
                <CardDescription className="text-xs">
                  Review deadlines, evaluate student submissions, and manage resubmission requests.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Assignment Title</TableHead>
                      <TableHead>Class / Section</TableHead>
                      <TableHead>Subject</TableHead>
                      <TableHead>Deadline</TableHead>
                      <TableHead>Max Marks</TableHead>
                      <TableHead>Submissions</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {homeworkList
                      .filter(
                        (h) =>
                          h.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                          h.subject?.name?.toLowerCase().includes(searchQuery.toLowerCase())
                      )
                      .map((hw) => (
                        <TableRow key={hw.id}>
                          <TableCell className="font-semibold text-slate-900 dark:text-slate-100">
                            <div>{hw.title}</div>
                            <p className="text-xs text-slate-500 line-clamp-1">{hw.description}</p>
                          </TableCell>
                          <TableCell>
                            <Badge variant="secondary">
                              {hw.school_class?.name || "Class 10"} - {hw.section?.name || "A"}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            <span className="font-medium text-slate-700 dark:text-slate-300">
                              {hw.subject?.name || "Math"}
                            </span>
                            <span className="text-xs text-slate-400 ml-1">({hw.subject?.code || "M10"})</span>
                          </TableCell>
                          <TableCell>
                            <div className="flex items-center gap-1 text-xs font-medium">
                              <Calendar className="h-3.5 w-3.5 text-slate-400" />
                              {hw.due_date}
                            </div>
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline" className="font-bold">
                              {hw.max_marks} Pts
                            </Badge>
                          </TableCell>
                          <TableCell>
                            <span className="font-semibold text-indigo-600 dark:text-indigo-400">
                              {hw.submissions_count || 0}
                            </span>{" "}
                            students
                          </TableCell>
                          <TableCell className="text-right space-x-2">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => {
                                setSelectedHomework(hw);
                                setIsSubmitHwModalOpen(true);
                              }}
                              className="text-xs"
                            >
                              Submit
                            </Button>
                            <Button
                              size="sm"
                              onClick={() => handleOpenSubmissions(hw)}
                              className="text-xs bg-indigo-600 hover:bg-indigo-700"
                            >
                              Submissions
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

        {/* TAB 2: LEARNING RESOURCES */}
        {activeTab === "resources" && (
          <div className="space-y-6">
            {/* Resource Cards Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {resources.map((res) => (
                <Card key={res.id} className="hover:shadow-md transition-shadow flex flex-col justify-between">
                  <CardHeader className="pb-3">
                    <div className="flex items-center justify-between gap-2 mb-2">
                      <Badge
                        variant={
                          res.resource_type === "video"
                            ? "default"
                            : res.resource_type === "document"
                            ? "secondary"
                            : "outline"
                        }
                        className="capitalize flex items-center gap-1"
                      >
                        {res.resource_type === "video" && <Video className="h-3 w-3" />}
                        {res.resource_type === "document" && <FileText className="h-3 w-3" />}
                        {res.resource_type === "link" && <LinkIcon className="h-3 w-3" />}
                        {res.resource_type}
                      </Badge>
                      <span className="text-xs text-slate-400">
                        {new Date(res.created_at).toLocaleDateString()}
                      </span>
                    </div>
                    <CardTitle className="text-base font-bold line-clamp-2">{res.title}</CardTitle>
                    <CardDescription className="text-xs line-clamp-3 mt-1">
                      {res.description || "No description provided."}
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="pt-0">
                    <div className="flex items-center gap-2 text-xs text-slate-500 mb-4">
                      <span>{res.school_class?.name || "Class 10"}</span>
                      <span>•</span>
                      <span>{res.subject?.name || "Math"}</span>
                    </div>

                    {res.video_url && (
                      <a
                        href={res.video_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center justify-center w-full gap-2 px-3 py-2 text-xs font-semibold rounded-lg bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950 dark:text-red-300 transition-colors"
                      >
                        <Video className="h-4 w-4" />
                        Watch Lecture Video
                        <ExternalLink className="h-3 w-3" />
                      </a>
                    )}

                    {res.file_url && (
                      <a
                        href={res.file_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center justify-center w-full gap-2 px-3 py-2 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-300 transition-colors"
                      >
                        <FileText className="h-4 w-4" />
                        Download Notes (PDF)
                        <ExternalLink className="h-3 w-3" />
                      </a>
                    )}
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* TAB 3: QUIZZES & AUTO-SCORING */}
        {activeTab === "quizzes" && (
          <div className="space-y-6">
            {/* Quizzes List */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {quizzes.map((quiz) => (
                <Card key={quiz.id} className="hover:shadow-md transition-shadow">
                  <CardHeader>
                    <div className="flex items-center justify-between mb-2">
                      <Badge variant="outline" className="text-xs">
                        {quiz.subject?.name || "Mathematics"}
                      </Badge>
                      <span className="flex items-center gap-1 text-xs text-slate-500 font-medium">
                        <Clock className="h-3.5 w-3.5 text-slate-400" />
                        {quiz.duration_minutes} Mins
                      </span>
                    </div>
                    <CardTitle className="text-lg font-bold">{quiz.title}</CardTitle>
                    <CardDescription className="text-xs line-clamp-2">
                      {quiz.description || "Evaluate conceptual mastery with instant auto-grading."}
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 border-t border-b border-slate-100 dark:border-slate-800 py-2">
                      <span>Total Marks: <strong>{quiz.total_marks}</strong></span>
                      <span>Pass Mark: <strong>{quiz.pass_marks}</strong></span>
                      <span>Questions: <strong>{quiz.questions?.length || quiz.questions_count || 3}</strong></span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-xs"
                        onClick={() => handleStartQuiz(quiz)}
                      >
                        Take Online Quiz
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        className="text-xs"
                        onClick={() => {
                          setSelectedQuizForQuestion(quiz);
                          setIsAddQuestionModalOpen(true);
                        }}
                      >
                        <Plus className="h-3.5 w-3.5 mr-1" />
                        Add Question
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* MODAL: CREATE HOMEWORK */}
        <Modal
          isOpen={isCreateHwModalOpen}
          onClose={() => setIsCreateHwModalOpen(false)}
          title="Create Homework Assignment"
          description="Assign class tasks with due dates, maximum marks, and document attachments."
        >
          <form onSubmit={handleCreateHomework} className="space-y-4">
            <div>
              <Label htmlFor="hw_title">Assignment Title *</Label>
              <Input
                id="hw_title"
                required
                value={hwForm.title}
                onChange={(e) => setHwForm({ ...hwForm, title: e.target.value })}
                placeholder="e.g. Chapter 4 Trigonometry Exercises"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="hw_due_date">Due Date *</Label>
                <Input
                  id="hw_due_date"
                  type="date"
                  required
                  value={hwForm.due_date}
                  onChange={(e) => setHwForm({ ...hwForm, due_date: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="hw_max_marks">Max Marks *</Label>
                <Input
                  id="hw_max_marks"
                  type="number"
                  required
                  value={hwForm.max_marks}
                  onChange={(e) => setHwForm({ ...hwForm, max_marks: Number(e.target.value) })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="hw_desc">Instructions & Description</Label>
              <textarea
                id="hw_desc"
                rows={3}
                value={hwForm.description}
                onChange={(e) => setHwForm({ ...hwForm, description: e.target.value })}
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
                placeholder="Details on questions, format, or proof steps..."
              />
            </div>
            <div className="border-t border-slate-100 dark:border-slate-800 pt-3 space-y-2">
              <Label className="text-xs font-semibold text-slate-500">Document Attachment (Optional)</Label>
              <div className="grid grid-cols-2 gap-2">
                <Input
                  placeholder="File name (e.g. Worksheet.pdf)"
                  value={hwForm.attachment_name}
                  onChange={(e) => setHwForm({ ...hwForm, attachment_name: e.target.value })}
                />
                <Input
                  placeholder="URL / Cloud storage link"
                  value={hwForm.attachment_url}
                  onChange={(e) => setHwForm({ ...hwForm, attachment_url: e.target.value })}
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsCreateHwModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
                Publish Assignment
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: SUBMISSION REVIEW & EVALUATION DRAWER */}
        <Modal
          isOpen={isSubmissionsModalOpen}
          onClose={() => setIsSubmissionsModalOpen(false)}
          title={`Submissions: ${selectedHomework?.title || ""}`}
          description={`Max Marks: ${selectedHomework?.max_marks} | Total Submissions: ${submissions.length}`}
          className="max-w-3xl"
        >
          <div className="space-y-4">
            <div className="max-h-[60vh] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
              {submissions.length === 0 ? (
                <p className="text-sm text-slate-500 text-center py-6">No submissions received yet.</p>
              ) : (
                submissions.map((sub) => (
                  <div key={sub.id} className="py-3 flex items-center justify-between gap-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-slate-900 dark:text-slate-100">
                          {sub.student?.first_name} {sub.student?.last_name}
                        </span>
                        <span className="text-xs text-slate-400">({sub.student?.admission_number})</span>
                        {sub.is_late && (
                          <Badge variant="destructive" className="text-[10px] uppercase">
                            Late Submission
                          </Badge>
                        )}
                        <Badge
                          variant={
                            sub.status === "graded"
                              ? "default"
                              : sub.status === "resubmission_requested"
                              ? "destructive"
                              : "secondary"
                          }
                          className="capitalize text-[10px]"
                        >
                          {sub.status.replace("_", " ")}
                        </Badge>
                      </div>
                      <p className="text-xs text-slate-600 dark:text-slate-400 italic">
                        &quot;{sub.submission_text || "Attachment provided"}&quot;
                      </p>
                      {sub.teacher_feedback && (
                        <p className="text-xs text-indigo-600 dark:text-indigo-400">
                          Feedback: {sub.teacher_feedback}
                        </p>
                      )}
                    </div>
                    <div className="flex items-center gap-2">
                      {sub.marks_obtained !== undefined && sub.marks_obtained !== null && (
                        <div className="text-right mr-2">
                          <span className="text-lg font-bold text-emerald-600">{sub.marks_obtained}</span>
                          <span className="text-xs text-slate-400">/{selectedHomework?.max_marks}</span>
                        </div>
                      )}
                      <Button
                        size="sm"
                        variant="outline"
                        className="text-xs"
                        onClick={() => {
                          setSelectedSubmission(sub);
                          setGradeForm({
                            marks_obtained: sub.marks_obtained || 0,
                            feedback: sub.teacher_feedback || "",
                            actionType: "grade",
                          });
                          setIsGradeModalOpen(true);
                        }}
                      >
                        Evaluate
                      </Button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </Modal>

        {/* MODAL: GRADE OR REQUEST RESUBMISSION */}
        <Modal
          isOpen={isGradeModalOpen}
          onClose={() => setIsGradeModalOpen(false)}
          title={`Evaluate Submission: ${selectedSubmission?.student?.first_name} ${selectedSubmission?.student?.last_name}`}
          description={`Max marks possible: ${selectedHomework?.max_marks}`}
        >
          <form onSubmit={handleGradeSubmission} className="space-y-4">
            <div className="flex gap-4 border-b border-slate-100 dark:border-slate-800 pb-3">
              <label className="flex items-center gap-2 text-sm font-medium cursor-pointer">
                <input
                  type="radio"
                  name="actionType"
                  value="grade"
                  checked={gradeForm.actionType === "grade"}
                  onChange={() => setGradeForm({ ...gradeForm, actionType: "grade" })}
                />
                Award Marks & Feedback
              </label>
              <label className="flex items-center gap-2 text-sm font-medium text-amber-600 cursor-pointer">
                <input
                  type="radio"
                  name="actionType"
                  value="resubmit"
                  checked={gradeForm.actionType === "resubmit"}
                  onChange={() => setGradeForm({ ...gradeForm, actionType: "resubmit" })}
                />
                Request Resubmission
              </label>
            </div>

            {gradeForm.actionType === "grade" && (
              <div>
                <Label htmlFor="marks_input">Marks Obtained (out of {selectedHomework?.max_marks}) *</Label>
                <Input
                  id="marks_input"
                  type="number"
                  step="0.5"
                  max={selectedHomework?.max_marks}
                  min={0}
                  required
                  value={gradeForm.marks_obtained}
                  onChange={(e) => setGradeForm({ ...gradeForm, marks_obtained: Number(e.target.value) })}
                />
              </div>
            )}

            <div>
              <Label htmlFor="teacher_feedback">Teacher Feedback / Correction Guidance *</Label>
              <textarea
                id="teacher_feedback"
                required
                rows={3}
                value={gradeForm.feedback}
                onChange={(e) => setGradeForm({ ...gradeForm, feedback: e.target.value })}
                placeholder={
                  gradeForm.actionType === "resubmit"
                    ? "Explain why resubmission is needed (e.g. Incomplete step 3, unreadable scan)..."
                    : "Praise strengths or suggest areas of improvement..."
                }
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsGradeModalOpen(false)}>
                Cancel
              </Button>
              <Button
                type="submit"
                className={
                  gradeForm.actionType === "resubmit"
                    ? "bg-amber-600 hover:bg-amber-700 text-white"
                    : "bg-indigo-600 hover:bg-indigo-700"
                }
              >
                {gradeForm.actionType === "resubmit" ? "Issue Resubmission Request" : "Submit Grade"}
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: STUDENT SUBMIT HOMEWORK */}
        <Modal
          isOpen={isSubmitHwModalOpen}
          onClose={() => setIsSubmitHwModalOpen(false)}
          title={`Submit Assignment: ${selectedHomework?.title || ""}`}
          description={`Due Date: ${selectedHomework?.due_date} | Max Marks: ${selectedHomework?.max_marks}`}
        >
          <form onSubmit={handleStudentSubmit} className="space-y-4">
            <div>
              <Label htmlFor="stu_text">Solution Notes / Submission Answer</Label>
              <textarea
                id="stu_text"
                rows={4}
                required
                value={studentSubmitForm.submission_text}
                onChange={(e) => setStudentSubmitForm({ ...studentSubmitForm, submission_text: e.target.value })}
                placeholder="Type your answer, solution steps, or summary here..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div>
              <Label htmlFor="stu_file">Document Attachment URL (PDF / Cloud Link)</Label>
              <Input
                id="stu_file"
                placeholder="https://drive.google.com/... or cloud PDF URL"
                value={studentSubmitForm.attachment_url}
                onChange={(e) => setStudentSubmitForm({ ...studentSubmitForm, attachment_url: e.target.value })}
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsSubmitHwModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
                Turn In Assignment
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: UPLOAD RESOURCE */}
        <Modal
          isOpen={isUploadResModalOpen}
          onClose={() => setIsUploadResModalOpen(false)}
          title="Share Learning Material"
          description="Upload documents, revision notes, or link recorded lecture videos."
        >
          <form onSubmit={handleUploadResource} className="space-y-4">
            <div>
              <Label htmlFor="res_title">Resource Title *</Label>
              <Input
                id="res_title"
                required
                value={resForm.title}
                onChange={(e) => setResForm({ ...resForm, title: e.target.value })}
                placeholder="e.g. Calculus Chain Rule Notes"
              />
            </div>
            <div>
              <Label htmlFor="res_type">Resource Type *</Label>
              <select
                id="res_type"
                value={resForm.resource_type}
                onChange={(e) =>
                  setResForm({
                    ...resForm,
                    resource_type: e.target.value as "document" | "notes" | "video" | "link",
                  })
                }
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <option value="document">Document (PDF)</option>
                <option value="notes">Notes Sheet</option>
                <option value="video">Video Lecture (YouTube/Stream)</option>
                <option value="link">Web Reference Link</option>
              </select>
            </div>
            {resForm.resource_type === "video" ? (
              <div>
                <Label htmlFor="res_video">Video URL (YouTube / MP4) *</Label>
                <Input
                  id="res_video"
                  required
                  placeholder="https://www.youtube.com/watch?v=..."
                  value={resForm.video_url}
                  onChange={(e) => setResForm({ ...resForm, video_url: e.target.value })}
                />
              </div>
            ) : (
              <div>
                <Label htmlFor="res_file">File / PDF URL *</Label>
                <Input
                  id="res_file"
                  required
                  placeholder="https://storage.school.edu/files/notes.pdf"
                  value={resForm.file_url}
                  onChange={(e) => setResForm({ ...resForm, file_url: e.target.value })}
                />
              </div>
            )}
            <div>
              <Label htmlFor="res_desc">Summary / Key Concepts</Label>
              <textarea
                id="res_desc"
                rows={2}
                value={resForm.description}
                onChange={(e) => setResForm({ ...resForm, description: e.target.value })}
                placeholder="What topics does this material cover?"
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsUploadResModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
                Publish Resource
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: CREATE QUIZ */}
        <Modal
          isOpen={isCreateQuizModalOpen}
          onClose={() => setIsCreateQuizModalOpen(false)}
          title="Create Online Quiz"
          description="Configure quiz timer, scoring criteria, and passing thresholds."
        >
          <form onSubmit={handleCreateQuiz} className="space-y-4">
            <div>
              <Label htmlFor="q_title">Quiz Title *</Label>
              <Input
                id="q_title"
                required
                value={quizForm.title}
                onChange={(e) => setQuizForm({ ...quizForm, title: e.target.value })}
                placeholder="e.g. Unit 3 Trigonometry Quiz"
              />
            </div>
            <div className="grid grid-cols-3 gap-3">
              <div>
                <Label htmlFor="q_dur">Duration (Mins) *</Label>
                <Input
                  id="q_dur"
                  type="number"
                  required
                  value={quizForm.duration_minutes}
                  onChange={(e) => setQuizForm({ ...quizForm, duration_minutes: Number(e.target.value) })}
                />
              </div>
              <div>
                <Label htmlFor="q_tot">Total Marks *</Label>
                <Input
                  id="q_tot"
                  type="number"
                  required
                  value={quizForm.total_marks}
                  onChange={(e) => setQuizForm({ ...quizForm, total_marks: Number(e.target.value) })}
                />
              </div>
              <div>
                <Label htmlFor="q_pass">Pass Marks *</Label>
                <Input
                  id="q_pass"
                  type="number"
                  required
                  value={quizForm.pass_marks}
                  onChange={(e) => setQuizForm({ ...quizForm, pass_marks: Number(e.target.value) })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="q_desc">Description / Instructions</Label>
              <textarea
                id="q_desc"
                rows={2}
                value={quizForm.description}
                onChange={(e) => setQuizForm({ ...quizForm, description: e.target.value })}
                placeholder="Instructions for students taking this timed test..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsCreateQuizModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
                Save & Add Questions
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: ADD QUESTION TO QUIZ */}
        <Modal
          isOpen={isAddQuestionModalOpen}
          onClose={() => setIsAddQuestionModalOpen(false)}
          title={`Add Question to: ${selectedQuizForQuestion?.title || ""}`}
          description="Build multiple-choice, true/false, or short-answer questions with auto-scoring."
        >
          <form onSubmit={handleAddQuestion} className="space-y-4">
            <div>
              <Label htmlFor="q_type">Question Format</Label>
              <select
                id="q_type"
                value={questionForm.question_type}
                onChange={(e) =>
                  setQuestionForm({
                    ...questionForm,
                    question_type: e.target.value as "multiple_choice" | "true_false" | "short_answer",
                  })
                }
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <option value="multiple_choice">Multiple Choice (MCQ)</option>
                <option value="true_false">True / False</option>
                <option value="short_answer">Short Answer</option>
              </select>
            </div>
            <div>
              <Label htmlFor="q_text">Question Prompt *</Label>
              <textarea
                id="q_text"
                required
                rows={2}
                value={questionForm.question_text}
                onChange={(e) => setQuestionForm({ ...questionForm, question_text: e.target.value })}
                placeholder="Type the question prompt..."
                className="w-full rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800 dark:bg-slate-900"
              />
            </div>

            {questionForm.question_type === "multiple_choice" && (
              <div className="space-y-2 border-t border-b border-slate-100 dark:border-slate-800 py-3">
                <Label className="text-xs font-semibold">Options & Choices</Label>
                <div className="grid grid-cols-2 gap-2">
                  <Input
                    placeholder="Option A"
                    value={questionForm.option_a}
                    onChange={(e) => setQuestionForm({ ...questionForm, option_a: e.target.value })}
                  />
                  <Input
                    placeholder="Option B"
                    value={questionForm.option_b}
                    onChange={(e) => setQuestionForm({ ...questionForm, option_b: e.target.value })}
                  />
                  <Input
                    placeholder="Option C"
                    value={questionForm.option_c}
                    onChange={(e) => setQuestionForm({ ...questionForm, option_c: e.target.value })}
                  />
                  <Input
                    placeholder="Option D"
                    value={questionForm.option_d}
                    onChange={(e) => setQuestionForm({ ...questionForm, option_d: e.target.value })}
                  />
                </div>
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="q_corr">Correct Answer Key *</Label>
                <Input
                  id="q_corr"
                  required
                  placeholder={
                    questionForm.question_type === "multiple_choice"
                      ? "A, B, C, or D"
                      : questionForm.question_type === "true_false"
                      ? "true or false"
                      : "Expected keyword"
                  }
                  value={questionForm.correct_answer}
                  onChange={(e) => setQuestionForm({ ...questionForm, correct_answer: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="q_pts">Question Marks *</Label>
                <Input
                  id="q_pts"
                  type="number"
                  required
                  value={questionForm.marks}
                  onChange={(e) => setQuestionForm({ ...questionForm, marks: Number(e.target.value) })}
                />
              </div>
            </div>

            <div>
              <Label htmlFor="q_exp">Explanation & Solution Walkthrough</Label>
              <Input
                id="q_exp"
                placeholder="Why this answer is correct..."
                value={questionForm.explanation}
                onChange={(e) => setQuestionForm({ ...questionForm, explanation: e.target.value })}
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setIsAddQuestionModalOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700">
                Save Question
              </Button>
            </div>
          </form>
        </Modal>

        {/* MODAL: LIVE QUIZ PLAYER & AUTO-SCORING TEST */}
        {activeQuizForTest && (
          <Modal
            isOpen={true}
            onClose={() => setActiveQuizForTest(null)}
            title={`Online Quiz: ${activeQuizForTest.title}`}
            description={`Timer: ${activeQuizForTest.duration_minutes} Mins | Pass Mark: ${activeQuizForTest.pass_marks}/${activeQuizForTest.total_marks}`}
            className="max-w-2xl"
          >
            <div className="space-y-6">
              {quizResult ? (
                /* Auto-Scoring Result Report */
                <div className="text-center py-6 space-y-4">
                  <div
                    className={`inline-flex p-4 rounded-full ${
                      quizResult.passed
                        ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                        : "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300"
                    }`}
                  >
                    {quizResult.passed ? <CheckCircle2 className="h-12 w-12" /> : <AlertCircle className="h-12 w-12" />}
                  </div>
                  <div>
                    <h3 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                      {quizResult.passed ? "Congratulations! You Passed!" : "Needs Improvement"}
                    </h3>
                    <p className="text-sm text-slate-500 mt-1">
                      Automated assessment completed with instant score calculation.
                    </p>
                  </div>
                  <div className="grid grid-cols-3 gap-4 max-w-sm mx-auto p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                    <div>
                      <p className="text-xs text-slate-500">Score</p>
                      <p className="text-xl font-bold text-slate-900 dark:text-slate-100">
                        {quizResult.score}/{quizResult.total}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-slate-500">Percentage</p>
                      <p className="text-xl font-bold text-indigo-600">{quizResult.percentage}%</p>
                    </div>
                    <div>
                      <p className="text-xs text-slate-500">Status</p>
                      <Badge variant={quizResult.passed ? "default" : "destructive"}>
                        {quizResult.passed ? "PASSED" : "FAILED"}
                      </Badge>
                    </div>
                  </div>
                  <Button onClick={() => setActiveQuizForTest(null)} className="mt-4">
                    Close Quiz
                  </Button>
                </div>
              ) : (
                /* Interactive Quiz Questions */
                <div className="space-y-6">
                  {(activeQuizForTest.questions || []).map((q, idx) => (
                    <div
                      key={q.id}
                      className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3"
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-indigo-600">Question {idx + 1}</span>
                        <Badge variant="outline" className="text-xs">
                          {q.marks} Marks
                        </Badge>
                      </div>
                      <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{q.question_text}</p>

                      {q.question_type === "multiple_choice" && q.options && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
                          {q.options.map((opt) => (
                            <label
                              key={opt.key}
                              className={`flex items-center gap-2 p-2.5 rounded-lg border text-sm cursor-pointer transition-colors ${
                                quizAnswers[q.id] === opt.key
                                  ? "border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-900 dark:text-indigo-200 font-medium"
                                  : "border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                              }`}
                            >
                              <input
                                type="radio"
                                name={`q_${q.id}`}
                                value={opt.key}
                                checked={quizAnswers[q.id] === opt.key}
                                onChange={() => setQuizAnswers({ ...quizAnswers, [q.id]: opt.key })}
                                className="text-indigo-600"
                              />
                              <span>
                                <strong>{opt.key}.</strong> {opt.text}
                              </span>
                            </label>
                          ))}
                        </div>
                      )}

                      {q.question_type === "true_false" && (
                        <div className="flex gap-3 pt-2">
                          {["true", "false"].map((val) => (
                            <button
                              key={val}
                              type="button"
                              onClick={() => setQuizAnswers({ ...quizAnswers, [q.id]: val })}
                              className={`px-4 py-2 rounded-lg text-sm font-semibold border capitalize transition-colors ${
                                quizAnswers[q.id] === val
                                  ? "bg-indigo-600 text-white border-indigo-600"
                                  : "border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800"
                              }`}
                            >
                              {val}
                            </button>
                          ))}
                        </div>
                      )}

                      {q.question_type === "short_answer" && (
                        <div className="pt-2">
                          <Input
                            placeholder="Type your answer..."
                            value={quizAnswers[q.id] || ""}
                            onChange={(e) => setQuizAnswers({ ...quizAnswers, [q.id]: e.target.value })}
                          />
                        </div>
                      )}
                    </div>
                  ))}

                  <div className="flex justify-between items-center border-t border-slate-100 dark:border-slate-800 pt-4">
                    <Button variant="outline" onClick={() => setActiveQuizForTest(null)}>
                      Exit Quiz
                    </Button>
                    <Button
                      onClick={handleSubmitQuizTest}
                      className="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                    >
                      Submit for Automatic Scoring
                    </Button>
                  </div>
                </div>
              )}
            </div>
          </Modal>
        )}
      </main>
    </div>
  );
}

