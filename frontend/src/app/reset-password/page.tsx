"use client";

import React, { useState, useEffect, Suspense } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import apiClient from "@/lib/api-client";
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Lock, AlertCircle, CheckCircle2, Loader2, ArrowRight } from "lucide-react";

const resetSchema = z
  .object({
    token: z.string().min(1, "Reset token is required"),
    email: z.string().email("Please enter a valid email address"),
    password: z
      .string()
      .min(8, "Password must be at least 8 characters")
      .regex(/[a-zA-Z]/, "Password must contain at least one letter")
      .regex(/[0-9]/, "Password must contain at least one number"),
    password_confirmation: z.string().min(1, "Please confirm your password"),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
  });

type ResetFormData = z.infer<typeof resetSchema>;

function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isSuccess, setIsSuccess] = useState(false);

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<ResetFormData>({
    resolver: zodResolver(resetSchema),
  });

  useEffect(() => {
    const tokenParam = searchParams.get("token");
    const emailParam = searchParams.get("email");

    if (tokenParam) setValue("token", tokenParam);
    if (emailParam) setValue("email", emailParam);
  }, [searchParams, setValue]);

  const onSubmit = async (data: ResetFormData) => {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      await apiClient.post("/auth/reset-password", {
        token: data.token,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });

      setIsSuccess(true);
      setTimeout(() => {
        router.push("/login");
      }, 3000);
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setErrorMessage(errorObj.message || "Failed to reset password. The token may be expired or invalid.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
      <Card className="w-full max-w-md shadow-xl border-slate-200 dark:border-slate-800">
        <CardHeader className="text-center space-y-2">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
            <Lock className="h-6 w-6" />
          </div>
          <CardTitle className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
            Create New Password
          </CardTitle>
          <CardDescription className="text-slate-500 dark:text-slate-400">
            Your new password must be at least 8 characters and contain letters and numbers
          </CardDescription>
        </CardHeader>

        {errorMessage && (
          <div className="mx-6 mb-2 flex items-center gap-2 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-900">
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
            <span>{errorMessage}</span>
          </div>
        )}

        {isSuccess ? (
          <div>
            <CardContent className="space-y-4">
              <div className="flex items-start gap-3 rounded-lg bg-emerald-50 p-4 text-xs text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div className="space-y-1">
                  <p className="font-semibold text-sm">Password Reset Successful</p>
                  <p>Your password has been securely updated. All active sessions have been revoked.</p>
                </div>
              </div>
            </CardContent>

            <CardFooter>
              <Link href="/login" className="w-full">
                <Button className="w-full gap-2">
                  <span>Sign In with New Password</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
            </CardFooter>
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)}>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="token">Reset Security Token</Label>
                <Input
                  id="token"
                  placeholder="Paste your 64-character reset token"
                  className="font-mono text-xs"
                  {...register("token")}
                  disabled={isLoading}
                />
                {errors.token && <p className="text-xs text-rose-500">{errors.token.message}</p>}
              </div>

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
                <Label htmlFor="password">New Password</Label>
                <Input
                  id="password"
                  type="password"
                  placeholder="••••••••"
                  {...register("password")}
                  disabled={isLoading}
                />
                {errors.password && <p className="text-xs text-rose-500">{errors.password.message}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password_confirmation">Confirm New Password</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  placeholder="••••••••"
                  {...register("password_confirmation")}
                  disabled={isLoading}
                />
                {errors.password_confirmation && (
                  <p className="text-xs text-rose-500">{errors.password_confirmation.message}</p>
                )}
              </div>
            </CardContent>

            <CardFooter className="flex flex-col gap-3">
              <Button type="submit" className="w-full gap-2" disabled={isLoading}>
                {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Lock className="h-4 w-4" />}
                <span>Reset Password</span>
              </Button>

              <Link href="/login" className="w-full text-center">
                <span className="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                  Cancel and return to login
                </span>
              </Link>
            </CardFooter>
          </form>
        )}
      </Card>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      }
    >
      <ResetPasswordForm />
    </Suspense>
  );
}
