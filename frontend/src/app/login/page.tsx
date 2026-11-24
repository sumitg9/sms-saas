"use client";

import React, { useState, Suspense } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { useAuth } from "@/providers/auth-provider";
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
  GraduationCap,
  ShieldCheck,
  KeyRound,
  AlertCircle,
  Loader2,
  ArrowRight,
  ShieldAlert,
  Building2,
  BookOpen,
  Receipt,
  User,
  Users,
  Sparkles,
  Zap,
} from "lucide-react";

const loginSchema = z.object({
  email: z.string().email("Please enter a valid email address"),
  password: z.string().min(1, "Password is required"),
});

type LoginFormData = z.infer<typeof loginSchema>;

interface DemoAccount {
  roleTitle: string;
  name: string;
  email: string;
  description: string;
  badge: string;
  badgeClass: string;
  icon: React.ComponentType<{ className?: string }>;
}

interface SchoolDemoGroup {
  id: string;
  name: string;
  shortName: string;
  board: string;
  campus: string;
  accounts: DemoAccount[];
}

const SCHOOL_DEMOS: SchoolDemoGroup[] = [
  {
    id: "apex",
    name: "Apex International Public School",
    shortName: "Apex Int'l",
    board: "CBSE Affiliated",
    campus: "North Main Campus (MAIN-01)",
    accounts: [
      {
        roleTitle: "Platform Super Admin",
        name: "Antigravity Admin",
        email: "admin@schoolerpsaas.com",
        description: "Global multi-tenant platform governance & subscriptions",
        badge: "Platform Admin",
        badgeClass: "bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800",
        icon: ShieldAlert,
      },
      {
        roleTitle: "School Owner",
        name: "Rajendra Verma",
        email: "owner@apexschool.edu",
        description: "Apex Educational Trust owner, executive settings & finance",
        badge: "School Owner",
        badgeClass: "bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800",
        icon: Building2,
      },
      {
        roleTitle: "School Administrator",
        name: "Vikram Aditya",
        email: "admin@apexschool.edu",
        description: "Branch administration, academic session & staff setup",
        badge: "School Admin",
        badgeClass: "bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 dark:border-sky-800",
        icon: ShieldCheck,
      },
      {
        roleTitle: "Principal",
        name: "Sunil Sharma",
        email: "principal@apexschool.edu",
        description: "Academic approvals, faculty supervision & report cards",
        badge: "Principal",
        badgeClass: "bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800",
        icon: GraduationCap,
      },
      {
        roleTitle: "Teacher",
        name: "Ananya Iyer",
        email: "teacher@apexschool.edu",
        description: "Class attendance, marks entry, homework & LMS",
        badge: "Teacher",
        badgeClass: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800",
        icon: BookOpen,
      },
      {
        roleTitle: "Accountant",
        name: "Ramesh Gupta",
        email: "accountant@apexschool.edu",
        description: "Fee collection, invoices, refunds, expense tracking & payroll",
        badge: "Accountant",
        badgeClass: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800",
        icon: Receipt,
      },
      {
        roleTitle: "Student",
        name: "Aarav Sharma",
        email: "student@apexschool.edu",
        description: "Grade 10-A student portal, timetable, homework & exam results",
        badge: "Student",
        badgeClass: "bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300 border-teal-200 dark:border-teal-800",
        icon: User,
      },
      {
        roleTitle: "Parent / Guardian",
        name: "Sanjay Sharma",
        email: "parent@apexschool.edu",
        description: "Child attendance, fee payments, report cards & notices",
        badge: "Parent",
        badgeClass: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-900",
        icon: Users,
      },
    ],
  },
  {
    id: "beacon",
    name: "Beacon Heights World School",
    shortName: "Beacon Heights",
    board: "ICSE / ISC Affiliated",
    campus: "South Central Campus (SOUTH-01)",
    accounts: [
      {
        roleTitle: "Platform Super Admin",
        name: "Antigravity Admin",
        email: "admin@schoolerpsaas.com",
        description: "Global multi-tenant platform governance & subscriptions",
        badge: "Platform Admin",
        badgeClass: "bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800",
        icon: ShieldAlert,
      },
      {
        roleTitle: "School Owner",
        name: "Dr. Meenakshi Sundaram",
        email: "owner@beaconschool.edu",
        description: "Beacon Heights Trust owner, institutional vision & compliance",
        badge: "School Owner",
        badgeClass: "bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800",
        icon: Building2,
      },
      {
        roleTitle: "School Administrator",
        name: "Rohan Mehra",
        email: "admin@beaconschool.edu",
        description: "Branch operations, staff lifecycle & system configuration",
        badge: "School Admin",
        badgeClass: "bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 dark:border-sky-800",
        icon: ShieldCheck,
      },
      {
        roleTitle: "Principal",
        name: "Margaret D'Souza",
        email: "principal@beaconschool.edu",
        description: "ICSE curriculum lead, exam controller & academic supervision",
        badge: "Principal",
        badgeClass: "bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800",
        icon: GraduationCap,
      },
      {
        roleTitle: "Teacher",
        name: "Devendra Joshi",
        email: "teacher@beaconschool.edu",
        description: "Grade 10 faculty, subject mentor, grades & study material",
        badge: "Teacher",
        badgeClass: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800",
        icon: BookOpen,
      },
      {
        roleTitle: "Accountant",
        name: "Kavita Nair",
        email: "accountant@beaconschool.edu",
        description: "Tuition collection, ledger reconciliations & vendor billing",
        badge: "Accountant",
        badgeClass: "bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800",
        icon: Receipt,
      },
      {
        roleTitle: "Student",
        name: "Rhea Sundaram",
        email: "student@beaconschool.edu",
        description: "Grade 10 student portal, attendance, tests & assignments",
        badge: "Student",
        badgeClass: "bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300 border-teal-200 dark:border-teal-800",
        icon: User,
      },
      {
        roleTitle: "Parent / Guardian",
        name: "Sundaram Natarajan",
        email: "parent@beaconschool.edu",
        description: "Student progress monitoring, fee payments & bus tracking",
        badge: "Parent",
        badgeClass: "bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-900",
        icon: Users,
      },
    ],
  },
];

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const redirectUrl = searchParams.get("redirect") || "/";
  const { login, complete2FaChallenge } = useAuth();

  const [isLoading, setIsLoading] = useState(false);
  const [loadingRole, setLoadingRole] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [selectedSchoolId, setSelectedSchoolId] = useState<string>("apex");

  const activeSchool = SCHOOL_DEMOS.find((s) => s.id === selectedSchoolId) || SCHOOL_DEMOS[0];

  // 2FA Challenge state
  const [requires2Fa, setRequires2Fa] = useState(false);
  const [challengeToken, setChallengeToken] = useState<string | null>(null);
  const [twoFactorCode, setTwoFactorCode] = useState("");

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: "",
      password: "",
    },
  });

  const onSubmit = async (data: LoginFormData) => {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      const result = await login({
        email: data.email,
        password: data.password,
        device_name: "Web Browser",
      });

      if (result.requires2fa && result.challengeToken) {
        setRequires2Fa(true);
        setChallengeToken(result.challengeToken);
      } else {
        router.push(redirectUrl);
      }
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setErrorMessage(errorObj.message || "Invalid email or password. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  const handleQuickLogin = async (account: DemoAccount) => {
    setValue("email", account.email);
    setValue("password", "Password123!");
    setIsLoading(true);
    setLoadingRole(account.email);
    setErrorMessage(null);

    try {
      const result = await login({
        email: account.email,
        password: "Password123!",
        device_name: `Quick Login (${account.roleTitle})`,
      });

      if (result.requires2fa && result.challengeToken) {
        setRequires2Fa(true);
        setChallengeToken(result.challengeToken);
      } else {
        router.push(redirectUrl);
      }
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setErrorMessage(errorObj.message || `Failed to log in as ${account.roleTitle}.`);
    } finally {
      setIsLoading(false);
      setLoadingRole(null);
    }
  };

  const onVerify2Fa = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!challengeToken || !twoFactorCode.trim()) return;

    setIsLoading(true);
    setErrorMessage(null);

    try {
      await complete2FaChallenge(challengeToken, twoFactorCode.trim());
      router.push(redirectUrl);
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setErrorMessage(errorObj.message || "Invalid two-factor code or recovery token.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="container max-w-6xl mx-auto px-4 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* Credentials Form Card */}
        <div className="lg:col-span-5">
          <Card className="shadow-xl border-slate-200 dark:border-slate-800">
            <CardHeader className="text-center space-y-2">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-500/25">
                <GraduationCap className="h-6 w-6" />
              </div>
              <CardTitle className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                {requires2Fa ? "Two-Factor Verification" : "Sign in to EduCore"}
              </CardTitle>
              <CardDescription className="text-slate-500 dark:text-slate-400">
                {requires2Fa
                  ? "Enter your 6-digit authenticator code or 8-character recovery code"
                  : "Enter your credentials to access your school portal"}
              </CardDescription>
            </CardHeader>

            {errorMessage && (
              <div className="mx-6 mb-2 flex items-center gap-2 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-900">
                <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
                <span>{errorMessage}</span>
              </div>
            )}

            {requires2Fa ? (
              <form onSubmit={onVerify2Fa}>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="twoFactorCode">Verification Code</Label>
                    <div className="relative">
                      <KeyRound className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                      <Input
                        id="twoFactorCode"
                        type="text"
                        placeholder="123456 or recovery code"
                        className="pl-9 tracking-widest font-mono text-center text-lg"
                        value={twoFactorCode}
                        onChange={(e) => setTwoFactorCode(e.target.value)}
                        autoFocus
                        required
                      />
                    </div>
                  </div>
                </CardContent>

                <CardFooter className="flex flex-col gap-3">
                  <Button type="submit" className="w-full gap-2" disabled={isLoading}>
                    {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />}
                    <span>Verify &amp; Continue</span>
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full text-xs text-slate-500"
                    onClick={() => setRequires2Fa(false)}
                  >
                    Back to credentials login
                  </Button>
                </CardFooter>
              </form>
            ) : (
              <form onSubmit={handleSubmit(onSubmit)}>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="email">Email Address</Label>
                    <Input
                      id="email"
                      type="email"
                      placeholder="name@school.edu"
                      {...register("email")}
                      disabled={isLoading}
                    />
                    {errors.email && <p className="text-xs text-rose-500">{errors.email.message}</p>}
                  </div>

                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <Label htmlFor="password">Password</Label>
                      <Link
                        href="/forgot-password"
                        className="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                      >
                        Forgot password?
                      </Link>
                    </div>
                    <Input
                      id="password"
                      type="password"
                      placeholder="••••••••"
                      {...register("password")}
                      disabled={isLoading}
                    />
                    {errors.password && <p className="text-xs text-rose-500">{errors.password.message}</p>}
                  </div>
                </CardContent>

                <CardFooter className="flex flex-col gap-4">
                  <Button type="submit" className="w-full gap-2" disabled={isLoading}>
                    {isLoading && !loadingRole ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <>
                        <span>Sign In</span>
                        <ArrowRight className="h-4 w-4" />
                      </>
                    )}
                  </Button>

                  <div className="text-center text-xs text-slate-500">
                    School Administrator?{" "}
                    <Link href="/register" className="font-semibold text-blue-600 hover:underline dark:text-blue-400">
                      Register your institution
                    </Link>
                  </div>
                </CardFooter>
              </form>
            )}
          </Card>
        </div>

        {/* Quick Testing Demo Logins Card */}
        <div className="lg:col-span-7">
          <Card className="shadow-xl border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900/70 backdrop-blur">
            <CardHeader className="pb-3 border-b border-slate-100 dark:border-slate-800 space-y-3">
              <div className="flex items-center justify-between flex-wrap gap-2">
                <div className="flex items-center gap-2">
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <Zap className="h-4 w-4" />
                  </div>
                  <div>
                    <CardTitle className="text-base font-bold flex items-center gap-1.5 text-slate-900 dark:text-slate-100">
                      <span>One-Click Test Logins</span>
                      <Sparkles className="h-3.5 w-3.5 text-amber-500" />
                    </CardTitle>
                    <CardDescription className="text-xs">
                      Instant testing accounts for every role &mdash; clicks log in immediately.
                    </CardDescription>
                  </div>
                </div>

                <Badge variant="outline" className="font-mono text-[10px] text-slate-500">
                  Password: Password123!
                </Badge>
              </div>

              {/* School Tenant Selector Tabs */}
              <div className="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800/60 rounded-lg text-xs font-medium">
                {SCHOOL_DEMOS.map((sch) => {
                  const isSelected = sch.id === activeSchool.id;
                  return (
                    <button
                      key={sch.id}
                      type="button"
                      onClick={() => setSelectedSchoolId(sch.id)}
                      className={`flex-1 flex items-center justify-center gap-2 py-1.5 px-3 rounded-md transition-all ${
                        isSelected
                          ? "bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm font-semibold"
                          : "text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200"
                      }`}
                    >
                      <Building2 className="h-3.5 w-3.5" />
                      <span>{sch.name}</span>
                      <span className="text-[10px] px-1.5 py-0.2 rounded bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        {sch.board}
                      </span>
                    </button>
                  );
                })}
              </div>
            </CardHeader>

            <CardContent className="pt-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {activeSchool.accounts.map((acc) => {
                  const Icon = acc.icon;
                  const isCurrentLoading = isLoading && loadingRole === acc.email;

                  return (
                    <div
                      key={acc.email}
                      className="group relative flex flex-col justify-between rounded-xl border border-slate-200/90 dark:border-slate-800 p-3.5 bg-white dark:bg-slate-950/60 hover:border-blue-500/50 hover:shadow-md transition-all"
                    >
                      <div>
                        <div className="flex items-center justify-between gap-1 mb-1.5">
                          <span className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold ${acc.badgeClass}`}>
                            <Icon className="h-3 w-3" />
                            {acc.badge}
                          </span>
                        </div>

                        <div className="font-semibold text-xs text-slate-900 dark:text-slate-100">
                          {acc.name}
                        </div>
                        <div className="font-mono text-[11px] text-slate-500 dark:text-slate-400 truncate">
                          {acc.email}
                        </div>
                        <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400 leading-snug line-clamp-2">
                          {acc.description}
                        </p>
                      </div>

                      <div className="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800/80">
                        <Button
                          type="button"
                          size="sm"
                          variant="secondary"
                          disabled={isLoading}
                          onClick={() => handleQuickLogin(acc)}
                          className="w-full h-8 text-xs font-medium gap-1.5 group-hover:bg-blue-600 group-hover:text-white transition-colors"
                        >
                          {isCurrentLoading ? (
                            <>
                              <Loader2 className="h-3 w-3 animate-spin" />
                              <span>Signing in...</span>
                            </>
                          ) : (
                            <>
                              <span>Log In as {acc.badge}</span>
                              <ArrowRight className="h-3 w-3 opacity-70 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                            </>
                          )}
                        </Button>
                      </div>
                    </div>
                  );
                })}
              </div>
            </CardContent>

            <CardFooter className="pt-2 pb-4 text-xs text-slate-500 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
              <span>All 8 roles configured with granular permissions &amp; tenant isolation.</span>
              <span className="font-mono text-[11px] text-slate-400">
                {activeSchool.name} &bull; {activeSchool.campus}
              </span>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      }
    >
      <LoginForm />
    </Suspense>
  );
}
