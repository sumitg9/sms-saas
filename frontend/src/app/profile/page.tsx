"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/providers/auth-provider";
import apiClient from "@/lib/api-client";
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from "@/components/ui/table";
import {
  User,
  Shield,
  KeyRound,
  Laptop,
  Smartphone,
  Globe,
  Trash2,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Lock,
  QrCode,
  Copy,
  RefreshCw,
} from "lucide-react";

interface UserSessionItem {
  id: string;
  ip_address: string;
  user_agent: string;
  device_type: string;
  last_activity_at: string;
  is_current: boolean;
}

export default function ProfilePage() {
  const router = useRouter();
  const { user, roles, permissions, activeTenant, isTwoFactorEnabled, refreshProfile, isAuthenticated, isLoading } =
    useAuth();

  // Profile Form State
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [phone, setPhone] = useState("");
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);
  const [profileMessage, setProfileMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Password Change State
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [isChangingPassword, setIsChangingPassword] = useState(false);
  const [passwordMessage, setPasswordMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // 2FA Management State
  const [twoFactorStep, setTwoFactorStep] = useState<"idle" | "enabling" | "disabling">("idle");
  const [twoFactorSecret, setTwoFactorSecret] = useState<string | null>(null);
  const [twoFactorUri, setTwoFactorUri] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [totpCode, setTotpCode] = useState("");
  const [disablePassword, setDisablePassword] = useState("");
  const [is2FaLoading, setIs2FaLoading] = useState(false);
  const [twoFactorMessage, setTwoFactorMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  // Sessions Management State
  const [sessions, setSessions] = useState<UserSessionItem[]>([]);
  const [isLoadingSessions, setIsLoadingSessions] = useState(false);

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      router.push("/login?redirect=/profile");
    }
  }, [isLoading, isAuthenticated, router]);

  useEffect(() => {
    if (user) {
      setFirstName(user.first_name || "");
      setLastName(user.last_name || "");
      setPhone(user.phone || "");
    }
  }, [user]);

  const fetchSessions = useCallback(async () => {
    setIsLoadingSessions(true);
    try {
      const response = await apiClient.get("/auth/sessions");
      setSessions(response.data.data || []);
    } catch {
      // Failed to load sessions
    } finally {
      setIsLoadingSessions(false);
    }
  }, []);

  useEffect(() => {
    if (isAuthenticated) {
      fetchSessions();
    }
  }, [isAuthenticated, fetchSessions]);

  // Handle Profile Update
  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsUpdatingProfile(true);
    setProfileMessage(null);

    try {
      await apiClient.put("/auth/profile", {
        first_name: firstName,
        last_name: lastName,
        phone: phone || null,
      });
      await refreshProfile();
      setProfileMessage({ type: "success", text: "Profile information updated successfully." });
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setProfileMessage({ type: "error", text: errorObj.message || "Failed to update profile." });
    } finally {
      setIsUpdatingProfile(false);
    }
  };

  // Handle Password Change
  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsChangingPassword(true);
    setPasswordMessage(null);

    if (newPassword !== confirmPassword) {
      setPasswordMessage({ type: "error", text: "New passwords do not match." });
      setIsChangingPassword(false);
      return;
    }

    try {
      await apiClient.post("/auth/change-password", {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      setPasswordMessage({
        type: "success",
        text: "Password updated successfully. Other active sessions have been invalidated.",
      });
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      fetchSessions();
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setPasswordMessage({ type: "error", text: errorObj.message || "Failed to change password." });
    } finally {
      setIsChangingPassword(false);
    }
  };

  // Handle 2FA Setup Initiation
  const startEnable2Fa = async () => {
    setIs2FaLoading(true);
    setTwoFactorMessage(null);

    try {
      const response = await apiClient.post("/auth/2fa/enable");
      const { secret, otpauth_uri, recovery_codes } = response.data.data;
      setTwoFactorSecret(secret);
      setTwoFactorUri(otpauth_uri);
      setRecoveryCodes(recovery_codes || []);
      setTwoFactorStep("enabling");
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setTwoFactorMessage({ type: "error", text: errorObj.message || "Failed to initiate 2FA setup." });
    } finally {
      setIs2FaLoading(false);
    }
  };

  // Confirm and activate 2FA
  const confirm2Fa = async (e: React.FormEvent) => {
    e.preventDefault();
    setIs2FaLoading(true);
    setTwoFactorMessage(null);

    try {
      await apiClient.post("/auth/2fa/confirm", { code: totpCode.trim() });
      await refreshProfile();
      setTwoFactorStep("idle");
      setTwoFactorMessage({
        type: "success",
        text: "Two-factor authentication has been successfully activated on your account.",
      });
      setTotpCode("");
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setTwoFactorMessage({ type: "error", text: errorObj.message || "Invalid verification code. Please try again." });
    } finally {
      setIs2FaLoading(false);
    }
  };

  // Disable 2FA
  const handleDisable2Fa = async (e: React.FormEvent) => {
    e.preventDefault();
    setIs2FaLoading(true);
    setTwoFactorMessage(null);

    try {
      await apiClient.post("/auth/2fa/disable", { password: disablePassword });
      await refreshProfile();
      setTwoFactorStep("idle");
      setDisablePassword("");
      setTwoFactorMessage({
        type: "success",
        text: "Two-factor authentication has been disabled.",
      });
    } catch (err: unknown) {
      const errorObj = err as { message?: string };
      setTwoFactorMessage({ type: "error", text: errorObj.message || "Incorrect password." });
    } finally {
      setIs2FaLoading(false);
    }
  };

  // Revoke Single Session
  const revokeSession = async (sessionId: string) => {
    try {
      await apiClient.delete(`/auth/sessions/${sessionId}`);
      fetchSessions();
    } catch {
      // Failed to revoke session
    }
  };

  // Revoke All Other Sessions
  const revokeOtherSessions = async () => {
    try {
      await apiClient.delete("/auth/sessions/others");
      fetchSessions();
    } catch {
      // Failed to revoke other sessions
    }
  };

  if (isLoading || !user) {
    return (
      <div className="flex h-96 items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  return (
    <div className="container mx-auto max-w-5xl px-4 py-8 space-y-8">
      {/* Page Header */}
      <div>
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
          Account &amp; Security Settings
        </h1>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Manage your personal profile, security credentials, two-factor authentication, and active device sessions.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {/* Left Column: Role & Organization Context */}
        <div className="md:col-span-1 space-y-6">
          <Card className="border-slate-200 dark:border-slate-800">
            <CardHeader className="text-center pb-2">
              <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-blue-100 text-2xl font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                {user.first_name[0]}
                {user.last_name[0]}
              </div>
              <CardTitle className="text-lg mt-3">{user.name}</CardTitle>
              <CardDescription className="text-xs">{user.email}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
              <div>
                <span className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">Active Tenant:</span>
                <span className="font-medium text-slate-900 dark:text-slate-100">
                  {activeTenant?.name || "Global Platform"}
                </span>
              </div>
              <div>
                <span className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">Assigned Roles:</span>
                <div className="flex flex-wrap gap-1">
                  {roles.map((r) => (
                    <Badge key={r} variant="outline" className="capitalize text-[10px]">
                      {r.replace("_", " ")}
                    </Badge>
                  ))}
                </div>
              </div>
              <div>
                <span className="font-semibold text-slate-700 dark:text-slate-300 block mb-1">Permissions Count:</span>
                <Badge variant="secondary" className="text-[11px]">
                  {permissions.length} granular capabilities active
                </Badge>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Right Column: Settings Forms */}
        <div className="md:col-span-2 space-y-6">
          {/* Profile Form Card */}
          <Card className="border-slate-200 dark:border-slate-800">
            <CardHeader>
              <CardTitle className="text-lg flex items-center gap-2">
                <User className="h-5 w-5 text-blue-600" />
                <span>Personal Information</span>
              </CardTitle>
              <CardDescription>Update your personal contact details</CardDescription>
            </CardHeader>

            <form onSubmit={handleUpdateProfile}>
              <CardContent className="space-y-4">
                {profileMessage && (
                  <div
                    className={`flex items-center gap-2 rounded-lg p-3 text-xs ${
                      profileMessage.type === "success"
                        ? "bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                        : "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-900"
                    }`}
                  >
                    {profileMessage.type === "success" ? (
                      <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                    ) : (
                      <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                    )}
                    <span>{profileMessage.text}</span>
                  </div>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="firstName">First Name</Label>
                    <Input
                      id="firstName"
                      value={firstName}
                      onChange={(e) => setFirstName(e.target.value)}
                      required
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="lastName">Last Name</Label>
                    <Input id="lastName" value={lastName} onChange={(e) => setLastName(e.target.value)} required />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="email">Email Address</Label>
                  <Input id="email" value={user.email} disabled className="bg-slate-50 dark:bg-slate-800 cursor-not-allowed" />
                  <span className="text-[11px] text-slate-400">Email address cannot be changed directly</span>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="phone">Phone Number</Label>
                  <Input
                    id="phone"
                    placeholder="+91 9876543210"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                  />
                </div>
              </CardContent>

              <CardFooter className="border-t border-slate-100 dark:border-slate-800 pt-4">
                <Button type="submit" size="sm" disabled={isUpdatingProfile} className="gap-2">
                  {isUpdatingProfile && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  <span>Save Changes</span>
                </Button>
              </CardFooter>
            </form>
          </Card>

          {/* Change Password Card */}
          <Card className="border-slate-200 dark:border-slate-800">
            <CardHeader>
              <CardTitle className="text-lg flex items-center gap-2">
                <Lock className="h-5 w-5 text-blue-600" />
                <span>Change Password</span>
              </CardTitle>
              <CardDescription>
                Ensure your account is using a strong password. Changing your password will invalidate other sessions.
              </CardDescription>
            </CardHeader>

            <form onSubmit={handleChangePassword}>
              <CardContent className="space-y-4">
                {passwordMessage && (
                  <div
                    className={`flex items-center gap-2 rounded-lg p-3 text-xs ${
                      passwordMessage.type === "success"
                        ? "bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                        : "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-900"
                    }`}
                  >
                    {passwordMessage.type === "success" ? (
                      <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                    ) : (
                      <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                    )}
                    <span>{passwordMessage.text}</span>
                  </div>
                )}

                <div className="space-y-1.5">
                  <Label htmlFor="currentPassword">Current Password</Label>
                  <Input
                    id="currentPassword"
                    type="password"
                    value={currentPassword}
                    onChange={(e) => setCurrentPassword(e.target.value)}
                    required
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="space-y-1.5">
                    <Label htmlFor="newPassword">New Password</Label>
                    <Input
                      id="newPassword"
                      type="password"
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      required
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="confirmPassword">Confirm New Password</Label>
                    <Input
                      id="confirmPassword"
                      type="password"
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      required
                    />
                  </div>
                </div>
              </CardContent>

              <CardFooter className="border-t border-slate-100 dark:border-slate-800 pt-4">
                <Button type="submit" size="sm" disabled={isChangingPassword} className="gap-2">
                  {isChangingPassword && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  <span>Update Password</span>
                </Button>
              </CardFooter>
            </form>
          </Card>

          {/* Two-Factor Authentication Card */}
          <Card className="border-slate-200 dark:border-slate-800">
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-lg flex items-center gap-2">
                    <Shield className="h-5 w-5 text-blue-600" />
                    <span>Two-Factor Authentication (2FA)</span>
                  </CardTitle>
                  <CardDescription>
                    Add an extra layer of security using standard TOTP authenticator apps (Google Authenticator, Authy)
                  </CardDescription>
                </div>
                <Badge variant={isTwoFactorEnabled ? "success" : "secondary"}>
                  {isTwoFactorEnabled ? "2FA Active" : "2FA Disabled"}
                </Badge>
              </div>
            </CardHeader>

            <CardContent className="space-y-4">
              {twoFactorMessage && (
                <div
                  className={`flex items-center gap-2 rounded-lg p-3 text-xs ${
                    twoFactorMessage.type === "success"
                      ? "bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                      : "bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-900"
                  }`}
                >
                  {twoFactorMessage.type === "success" ? (
                    <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" />
                  ) : (
                    <AlertCircle className="h-4 w-4 shrink-0 text-rose-600" />
                  )}
                  <span>{twoFactorMessage.text}</span>
                </div>
              )}

              {twoFactorStep === "idle" && (
                <div>
                  {isTwoFactorEnabled ? (
                    <div className="space-y-3">
                      <p className="text-xs text-slate-600 dark:text-slate-400">
                        Two-factor authentication is currently safeguarding your account. On login, you will be prompted
                        for an authenticator code.
                      </p>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setTwoFactorStep("disabling")}
                        className="text-rose-600 border-rose-200 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
                      >
                        Disable Two-Factor Authentication
                      </Button>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      <p className="text-xs text-slate-600 dark:text-slate-400">
                        When enabled, sign-ins will require your password along with a time-sensitive 6-digit TOTP code
                        generated by your mobile device.
                      </p>
                      <Button size="sm" onClick={startEnable2Fa} disabled={is2FaLoading} className="gap-2">
                        {is2FaLoading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <KeyRound className="h-3.5 w-3.5" />}
                        <span>Enable Two-Factor Authentication</span>
                      </Button>
                    </div>
                  )}
                </div>
              )}

              {twoFactorStep === "enabling" && (
                <div className="space-y-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 p-4 border border-slate-200 dark:border-slate-700">
                  <div className="space-y-2">
                    <span className="text-xs font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                      <QrCode className="h-4 w-4 text-blue-600" />
                      1. Scan QR / Setup Authenticator App
                    </span>
                    <p className="text-xs text-slate-600 dark:text-slate-400">
                      Add the secret key below into your TOTP application:
                    </p>
                    <div className="flex items-center gap-2">
                      <code className="rounded bg-white dark:bg-slate-900 px-3 py-1.5 font-mono text-sm tracking-wider font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-slate-700">
                        {twoFactorSecret}
                      </code>
                    </div>
                    {twoFactorUri && (
                      <span className="text-[11px] font-mono text-slate-400 truncate block">
                        URI: {twoFactorUri}
                      </span>
                    )}
                  </div>

                  {recoveryCodes.length > 0 && (
                    <div className="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-700">
                      <span className="text-xs font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                        <Copy className="h-4 w-4 text-emerald-600" />
                        2. Emergency Recovery Codes (Save Securely)
                      </span>
                      <p className="text-xs text-slate-500">
                        Each code can be used once to access your account if you lose your authenticator device.
                      </p>
                      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs">
                        {recoveryCodes.map((code) => (
                          <div
                            key={code}
                            className="bg-white dark:bg-slate-900 p-1.5 text-center rounded border border-slate-200 dark:border-slate-700 font-bold"
                          >
                            {code}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  <form onSubmit={confirm2Fa} className="space-y-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <div className="space-y-1.5">
                      <Label htmlFor="totpCode">3. Enter 6-Digit Code from App to Confirm</Label>
                      <Input
                        id="totpCode"
                        placeholder="123456"
                        className="font-mono text-lg text-center tracking-widest max-w-xs"
                        maxLength={6}
                        value={totpCode}
                        onChange={(e) => setTotpCode(e.target.value)}
                        required
                      />
                    </div>
                    <div className="flex items-center gap-2">
                      <Button type="submit" size="sm" disabled={is2FaLoading || totpCode.length < 6} className="gap-2">
                        {is2FaLoading && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        <span>Confirm &amp; Activate 2FA</span>
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setTwoFactorStep("idle")}
                        disabled={is2FaLoading}
                      >
                        Cancel
                      </Button>
                    </div>
                  </form>
                </div>
              )}

              {twoFactorStep === "disabling" && (
                <form
                  onSubmit={handleDisable2Fa}
                  className="space-y-3 rounded-lg bg-rose-50/50 dark:bg-rose-950/20 p-4 border border-rose-200 dark:border-rose-900"
                >
                  <p className="text-xs text-rose-700 dark:text-rose-300 font-medium">
                    Confirm your current password to disable two-factor authentication:
                  </p>
                  <div className="space-y-1.5 max-w-xs">
                    <Label htmlFor="disablePassword">Account Password</Label>
                    <Input
                      id="disablePassword"
                      type="password"
                      value={disablePassword}
                      onChange={(e) => setDisablePassword(e.target.value)}
                      required
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <Button type="submit" size="sm" variant="destructive" disabled={is2FaLoading} className="gap-2">
                      {is2FaLoading && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                      <span>Disable 2FA</span>
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setTwoFactorStep("idle")}
                      disabled={is2FaLoading}
                    >
                      Cancel
                    </Button>
                  </div>
                </form>
              )}
            </CardContent>
          </Card>

          {/* Active Sessions & Device Tracking Card */}
          <Card className="border-slate-200 dark:border-slate-800">
            <CardHeader className="flex flex-row items-center justify-between pb-3">
              <div>
                <CardTitle className="text-lg flex items-center gap-2">
                  <Laptop className="h-5 w-5 text-blue-600" />
                  <span>Active Sessions &amp; Devices</span>
                </CardTitle>
                <CardDescription>Track and manage active logins across devices and locations</CardDescription>
              </div>

              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={fetchSessions}
                  disabled={isLoadingSessions}
                  className="h-8 gap-1.5 text-xs"
                >
                  <RefreshCw className={`h-3 w-3 ${isLoadingSessions ? "animate-spin" : ""}`} />
                  <span>Refresh</span>
                </Button>

                {sessions.length > 1 && (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={revokeOtherSessions}
                    className="h-8 gap-1.5 text-xs text-rose-600 border-rose-200 hover:bg-rose-50 dark:border-rose-900"
                  >
                    <span>Revoke Other Sessions</span>
                  </Button>
                )}
              </div>
            </CardHeader>

            <CardContent>
              {isLoadingSessions ? (
                <div className="flex h-32 items-center justify-center">
                  <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
                </div>
              ) : sessions.length === 0 ? (
                <p className="text-xs text-slate-500 py-4 text-center">No active sessions tracked.</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Device</TableHead>
                      <TableHead>IP Address</TableHead>
                      <TableHead>Last Active</TableHead>
                      <TableHead className="text-right">Action</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sessions.map((sess) => {
                      const isMobile = sess.device_type === "mobile";
                      return (
                        <TableRow key={sess.id}>
                          <TableCell className="font-medium">
                            <div className="flex items-center gap-2">
                              {isMobile ? (
                                <Smartphone className="h-4 w-4 text-slate-500" />
                              ) : (
                                <Laptop className="h-4 w-4 text-slate-500" />
                              )}
                              <div className="flex flex-col">
                                <span className="text-xs capitalize flex items-center gap-1.5">
                                  {sess.device_type || "Browser"}
                                  {sess.is_current && (
                                    <Badge variant="success" className="text-[9px] py-0 px-1">
                                      Current Device
                                    </Badge>
                                  )}
                                </span>
                                <span className="text-[10px] text-slate-400 truncate max-w-xs">
                                  {sess.user_agent}
                                </span>
                              </div>
                            </div>
                          </TableCell>
                          <TableCell className="text-xs font-mono text-slate-600 dark:text-slate-400">
                            <div className="flex items-center gap-1.5">
                              <Globe className="h-3 w-3 text-slate-400" />
                              <span>{sess.ip_address || "127.0.0.1"}</span>
                            </div>
                          </TableCell>
                          <TableCell className="text-xs text-slate-500">
                            {new Date(sess.last_activity_at).toLocaleString()}
                          </TableCell>
                          <TableCell className="text-right">
                            {!sess.is_current && (
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => revokeSession(sess.id)}
                                className="h-7 w-7 p-0 text-slate-400 hover:text-rose-600"
                                title="Revoke session"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

