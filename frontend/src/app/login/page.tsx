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
import { GraduationCap, ShieldCheck, KeyRound, AlertCircle, Loader2, ArrowRight } from "lucide-react";

const loginSchema = z.object({
  email: z.string().email("Please enter a valid email address"),
  password: z.string().min(1, "Password is required"),
});

type LoginFormData = z.infer<typeof loginSchema>;

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const redirectUrl = searchParams.get("redirect") || "/";
  const { login, complete2FaChallenge } = useAuth();

  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // 2FA Challenge state
  const [requires2Fa, setRequires2Fa] = useState(false);
  const [challengeToken, setChallengeToken] = useState<string | null>(null);
  const [twoFactorCode, setTwoFactorCode] = useState("");

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
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
    <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
      <Card className="w-full max-w-md shadow-xl border-slate-200 dark:border-slate-800">
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
                {isLoading ? (
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
