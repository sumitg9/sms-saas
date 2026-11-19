"use client";

import React, { useState } from "react";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import apiClient from "@/lib/api-client";
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { KeyRound, Mail, AlertCircle, CheckCircle2, Loader2, ArrowLeft, ArrowRight } from "lucide-react";

const forgotSchema = z.object({
  email: z.string().email("Please enter a valid email address"),
});

type ForgotFormData = z.infer<typeof forgotSchema>;

export default function ForgotPasswordPage() {
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successInfo, setSuccessInfo] = useState<{ message: string; email: string; token?: string } | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ForgotFormData>({
    resolver: zodResolver(forgotSchema),
  });

  const onSubmit = async (data: ForgotFormData) => {
    setIsLoading(true);
    setErrorMessage(null);

    try {
      const response = await apiClient.post("/auth/forgot-password", { email: data.email });
      setSuccessInfo({
        message: response.data.message || "Password reset instructions have been generated.",
        email: data.email,
        token: response.data.reset_token,
      });
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setErrorMessage(errorObj.message || "Failed to process password reset request. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-[calc(100vh-8rem)] items-center justify-center p-4">
      <Card className="w-full max-w-md shadow-xl border-slate-200 dark:border-slate-800">
        <CardHeader className="text-center space-y-2">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
            <KeyRound className="h-6 w-6" />
          </div>
          <CardTitle className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
            Forgot Password
          </CardTitle>
          <CardDescription className="text-slate-500 dark:text-slate-400">
            Enter your verified school account email to reset your credentials
          </CardDescription>
        </CardHeader>

        {errorMessage && (
          <div className="mx-6 mb-2 flex items-center gap-2 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-900">
            <AlertCircle className="h-4 w-4 shrink-0 text-rose-500" />
            <span>{errorMessage}</span>
          </div>
        )}

        {successInfo ? (
          <div>
            <CardContent className="space-y-4">
              <div className="flex items-start gap-3 rounded-lg bg-emerald-50 p-4 text-xs text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                <CheckCircle2 className="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div className="space-y-1">
                  <p className="font-semibold text-sm">Reset Request Dispatched</p>
                  <p>{successInfo.message}</p>
                </div>
              </div>

              {successInfo.token && (
                <div className="rounded-lg bg-slate-100 p-3 dark:bg-slate-800 space-y-1">
                  <span className="text-[11px] font-medium text-slate-500">Development Security Reset Token:</span>
                  <p className="font-mono text-xs break-all text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-900 p-2 rounded border border-slate-200 dark:border-slate-700">
                    {successInfo.token}
                  </p>
                </div>
              )}
            </CardContent>

            <CardFooter className="flex flex-col gap-3">
              <Link
                href={`/reset-password?email=${encodeURIComponent(successInfo.email)}${
                  successInfo.token ? `&token=${encodeURIComponent(successInfo.token)}` : ""
                }`}
                className="w-full"
              >
                <Button className="w-full gap-2">
                  <span>Proceed to Reset Form</span>
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
              <Link href="/login" className="w-full">
                <Button variant="ghost" size="sm" className="w-full text-xs text-slate-500 gap-1.5">
                  <ArrowLeft className="h-3.5 w-3.5" />
                  <span>Return to Sign In</span>
                </Button>
              </Link>
            </CardFooter>
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)}>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="email">Email Address</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="name@school.edu"
                    className="pl-9"
                    {...register("email")}
                    disabled={isLoading}
                  />
                </div>
                {errors.email && <p className="text-xs text-rose-500">{errors.email.message}</p>}
              </div>
            </CardContent>

            <CardFooter className="flex flex-col gap-3">
              <Button type="submit" className="w-full gap-2" disabled={isLoading}>
                {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Mail className="h-4 w-4" />}
                <span>Send Reset Link</span>
              </Button>

              <Link href="/login" className="w-full">
                <Button type="button" variant="ghost" size="sm" className="w-full text-xs text-slate-500 gap-1.5">
                  <ArrowLeft className="h-3.5 w-3.5" />
                  <span>Back to Sign In</span>
                </Button>
              </Link>
            </CardFooter>
          </form>
        )}
      </Card>
    </div>
  );
}

