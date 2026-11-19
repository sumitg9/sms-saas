# Authentication & Granular Authorization Architecture

## 1. Overview

EduCore School ERP SaaS implements an enterprise-grade, multi-tenant authentication and granular authorization system built on **Laravel 12 Sanctum** and **Spatie Laravel Permission** (v6.25), integrated seamlessly with a **Next.js 16** frontend client.

### Core Security Principles
1. **Zero Trust Tenancy**: All user actions, roles, and permissions are scoped to verified tenant memberships (`tenant_memberships`). No client-supplied tenant ID is trusted without authentication and tenancy verification.
2. **Backend Authoritative**: Frontend UI elements (buttons, navigation tabs) are conditionally rendered for UX only; every API request is strictly guarded by Laravel middleware (`permission:xxx`, `role:xxx`) and tenant scopes.
3. **Privilege Escalation Defense**: Users cannot assign roles higher than their own authority, and cross-tenant role assignments are blocked by database constraints and composite lookups.
4. **Defense-in-Depth**: Combines rate-limiting, session revocation, device telemetry tracking, and optional RFC 6238 TOTP two-factor authentication.

---

## 2. Authentication Features & Protocols

### 2.1 Login & Rate Limiting
- **Endpoint**: `POST /api/v1/auth/login`
- **Throttling**: 5 failed login attempts per minute per email/IP pair via `RateLimiter::tooManyAttempts()`. When exceeded, HTTP 429 `RATE_LIMIT_EXCEEDED` is returned with remaining retry seconds.
- **Account Verification**: Inactive accounts (`is_active = false`) are rejected immediately with HTTP 403 `ACCOUNT_DEACTIVATED`.
- **Credential Validation**: Passwords hashed with `bcrypt` (or Argon2id).
- **Session & Telemetry Logging**:
  - Every login attempt (success, failed password, unverified account) writes an immutable audit record to `login_histories` capturing IP address, user agent, failure reason, and timestamp.
  - Successful logins track the active hardware device in `user_sessions`.

### 2.2 Two-Factor Authentication (TOTP + Recovery Codes)
- **Standard**: RFC 6238 Time-Based One-Time Password algorithm (SHA1, 30s step, 6-digit output).
- **Activation Flow**:
  1. User calls `POST /api/v1/auth/2fa/enable` (Sanctum authenticated).
  2. Server generates a random 160-bit secret (RFC 3548 Base32 encoded), 8 cryptographically secure single-use recovery codes, and an `otpauth://totp/...` URI.
  3. Secret and recovery codes are encrypted at rest using Laravel's `Crypt::encryptString()` in `users.two_factor_secret` and `users.two_factor_recovery_codes`.
  4. User enters 6-digit code to `POST /api/v1/auth/2fa/confirm`. Upon verification, `users.two_factor_confirmed_at` is timestamped.
- **Login Interception**:
  - When a user with confirmed 2FA enters correct email/password, the server does *not* issue a Sanctum token.
  - Instead, it returns `requires_2fa: true` and an encrypted, short-lived (5 minute) `challenge_token`.
  - The client submits `{ challenge_token, code }` to `POST /api/v1/auth/2fa/challenge`.
  - Verification accepts either the current/adjacent TOTP code (±1 drift window) or a valid recovery code (which is permanently revoked upon use).
- **Deactivation**:
  - User submits current password to `POST /api/v1/auth/2fa/disable`.

### 2.3 Password Reset Workflow
- **Request Link**: `POST /api/v1/auth/forgot-password`
  - Throttled to 5 requests per 5 minutes.
  - Generates a 64-character cryptographic token stored hashed in `password_reset_tokens`.
  - Generic success message returned to prevent user enumeration attacks.
- **Reset Password**: `POST /api/v1/auth/reset-password`
  - Validates token with 60-minute expiration window.
  - Updates password hash and revokes all active Sanctum tokens (`$user->tokens()->delete()`).

### 2.4 Device & Session Management
- **Endpoints**:
  - `GET /api/v1/auth/sessions`: Lists active tracked user sessions with IP, user agent, device category (desktop/mobile/tablet), and last activity.
  - `DELETE /api/v1/auth/sessions/{id}`: Revokes a specific remote session.
  - `DELETE /api/v1/auth/sessions/others`: Revokes all tracked devices other than the current session.

---

## 3. Role-Based Access Control (RBAC) Architecture

### 3.1 Role Hierarchy & Scopes

The system defines **17 standardized roles** segregated into Platform Administration and School Operations:

| Category | Role Code | Role Name | Tenancy Scope | Description |
| :--- | :--- | :--- | :--- | :--- |
| **Platform** | `super_admin` | Super Admin | Global Platform | Full bypass across all tenants for platform health, audits, and billing |
| **Platform** | `support_admin` | Support Admin | Global Platform | Read-only and diagnostic access across tenants for customer support |
| **School** | `school_owner` | School Owner | Tenant-Scoped | Proprietor / Trust Trustee with complete ownership of tenant organization |
| **School** | `school_admin` | School Admin | Tenant-Scoped | Operational administrator managing branches, staff, fees, and academics |
| **School** | `principal` | Principal | Branch/Tenant | Head of institution approving results, policies, promotions, and staff leaves |
| **School** | `vice_principal` | Vice Principal | Branch/Tenant | Academic supervisor assisting principal in operations and curriculum |
| **School** | `accountant` | Accountant | Branch/Tenant | Financial officer collecting fees, managing invoices, issuing receipts |
| **School** | `admission_officer` | Admission Officer | Branch/Tenant | Manages prospective leads, applications, and student enrollment |
| **School** | `teacher` | Teacher | Branch/Tenant | Enters student marks, homework, attendance for assigned subjects |
| **School** | `class_teacher` | Class Teacher | Branch/Tenant | Primary supervisor for assigned section (attendance, report cards) |
| **School** | `librarian` | Librarian | Branch/Tenant | Manages book catalogue, issuing, returns, and library fines |
| **School** | `transport_manager` | Transport Manager | Branch/Tenant | Oversees vehicles, drivers, bus routes, and student transport rosters |
| **School** | `hr_manager` | HR Manager | Branch/Tenant | Staff recruitment, attendance, payroll records, and contracts |
| **School** | `receptionist` | Receptionist | Branch/Tenant | Visitor passes, front desk inquiries, and generic admissions |
| **School** | `parent` | Parent | Student-Linked | Views linked children's attendance, report cards, and fee invoices |
| **School** | `student` | Student | Individual | Views personal timetable, homework, attendance, and exam results |
| **School** | `driver` | Driver | Vehicle-Linked | Views assigned route, pickup stops, and passenger manifest |

---

## 4. Permission Matrix

The platform seeds **104 granular permissions** across **14 functional modules**. Every permission adheres to the `module.action` standard:

### 4.1 Granular Actions
- `view`: Read-only access to module resources.
- `create`: Ability to create draft or pending records.
- `edit`: Modify non-locked records.
- `delete`: Soft-delete or archive records.
- `approve`: Workflow sign-off (leaves, admission offers, concessions).
- `reject`: Reject workflow requests with comment.
- `export`: Generate CSV/Excel/PDF exports.
- `print`: Print invoices, hall tickets, ID cards, report cards.
- `manage`: Full administrative configuration for the module.

### 4.2 Module Permission Breakdown

| Domain | Permissions | Key Capabilities |
| :--- | :--- | :--- |
| **1. SaaS Tenancy** | `tenants.view`, `tenants.create`, `tenants.edit`, `tenants.delete`, `tenants.manage` | School registration, domain routing, subscription provisioning |
| **2. Academics** | `academics.view`, `academics.create`, `academics.edit`, `academics.delete`, `academics.manage` | Academic years, terms, classes, sections, subjects, timetables |
| **3. Students** | `students.view`, `students.create`, `students.edit`, `students.delete`, `students.export`, `students.manage` | Student records, roll numbers, transfers, health records |
| **4. Admissions** | `admissions.view`, `admissions.create`, `admissions.edit`, `admissions.delete`, `admissions.approve`, `admissions.reject` | Application intake, documents, entrance tests, enrollment confirmation |
| **5. Attendance** | `attendance.view`, `attendance.create`, `attendance.edit`, `attendance.manage`, `attendance.export` | Daily student & employee check-in, biometric syncing, leave passes |
| **6. Fees & Finance** | `fees.view`, `fees.create`, `fees.edit`, `fees.delete`, `fees.collect`, `fees.refund`, `fees.manage`, `fees.export`, `fees.print` | Fee structures, student fee assignments, invoice generation, payment gateway reconciliation |
| **7. Examinations** | `examinations.view`, `examinations.create`, `examinations.edit`, `examinations.delete`, `examinations.publish`, `examinations.manage`, `examinations.print` | Exam schedules, marks entry, grade conversions, report card publishing |
| **8. Staff & HR** | `staff.view`, `staff.create`, `staff.edit`, `staff.delete`, `staff.manage`, `staff.export` | Employee directories, leave balances, contracts, payroll |
| **9. Communication** | `communication.view`, `communication.create`, `communication.delete`, `communication.manage` | School broadcasts, SMS/WhatsApp notifications, circulars |
| **10. Operations** | `transport.view`, `transport.manage`, `library.view`, `library.manage`, `inventory.view`, `inventory.manage`, `hostel.view`, `hostel.manage` | Fleet tracking, book loans, asset inventories, dormitories |
| **11. Reports** | `reports.view`, `reports.export`, `reports.print` | Financial P&L, fee defalcations, CBSE/state compliance filings |
| **12. Settings** | `settings.view`, `settings.edit`, `settings.manage` | School branding, grade schemes, period timings, SMS gateway keys |

---

## 5. Security & Isolation Enforcement

### 5.1 Eloquent Tenant Scoping (`HasTenantRoles`)
The `User` model leverages `App\Traits\HasTenantRoles`:
- Roles are looked up with tenant affinity: `$user->hasRole($role, $tenantId)`.
- When inside an active tenant HTTP context (`TenantContext`), permissions automatically evaluate against the user's role mappings within that specific tenant.
- Platform Super Admin (`super_admin`) bypasses tenant-level checks via `Gate::before()` callback in `AppServiceProvider`.

### 5.2 Middleware Architecture
- `auth:sanctum`: Ensures standard bearer token validity and user identity.
- `tenant.require`: Verifies presence and legitimacy of tenant context.
- `tenant.membership`: Confirms user is an active member of the resolved tenant organization.
- `branch.access`: Enforces branch-level restrictions (multi-branch isolation).
- `permission:xxx`: Verifies that authenticated user holds the required granular capability in the current tenant.
- `role:xxx`: Verifies that authenticated user possesses the specific administrative role.

### 5.3 PostgreSQL Row-Level Security (RLS)
As a secondary layer of defense behind Eloquent scopes:
- RLS policies on tenant-owned tables enforce `tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid`.
- Any raw query or bypassed scope fails at the PostgreSQL database engine layer.

---

## 6. Frontend Integration (Next.js)

### 6.1 Authentication State & Client Hook
- `AuthProvider` (`src/providers/auth-provider.tsx`): Hydrates user profile, active tenant, roles, and permissions on load.
- `useAuth()` Hook exposes:
  - `isAuthenticated`: Boolean status
  - `user`: Authenticated user entity
  - `activeTenant`: Current school tenant
  - `roles` & `permissions`: Active tenant authorization list
  - `hasRole(role)` & `hasPermission(permission)`: Evaluators
  - `login()`, `logout()`, `complete2FaChallenge()`, `refreshProfile()`

### 6.2 Granular UI Authorization Component (`<Can />`)
```tsx
import { Can } from "@/components/auth/Can";

// Conditionally render action button based on granular permission
<Can permission="fees.collect">
  <Button onClick={openFeeModal}>Collect Fee</Button>
</Can>

// Conditionally render administrative link based on role
<Can role="school_admin">
  <Link href="/settings/branches">Branch Settings</Link>
</Can>
```

### 6.3 Dedicated Auth Pages
- `/login`: Email + password credentials login with seamless 2FA TOTP / recovery challenge modal.
- `/forgot-password`: Email token dispatch with rate-limit protection.
- `/reset-password`: 64-char token verification with strong password criteria.
- `/profile`: Contact profile updates, password change, TOTP 2FA configuration with recovery code vault, and real-time session device revocation table.
- `/unauthorized`: 403 Forbidden page detailing missing permissions and tenant context.

---

## 7. Verification & Automated Test Suite

The authentication and authorization system has 100% test pass coverage across 33 automated Pest/PHPUnit tests:

```text
PASS Tests\Feature\AuthenticationAndAuthorizationTest
  ✓ user can register with school organization, creating tenant, branch, and owner role
  ✓ user login validates credentials and records login history
  ✓ deactivated account cannot log in
  ✓ brute force login is throttled after 5 failed attempts
  ✓ user can log out and revoke active access token
  ✓ password reset flow generates token and resets password securely
  ✓ authenticated user can change password and other active sessions are revoked
  ✓ session management lists tracked devices and allows remote session revocation
  ✓ two-factor authentication flow with TOTP confirmation and login challenge
  ✓ granular permissions restrict access and role middleware enforces authorization
  ✓ privilege escalation is prevented when assigning roles

PASS Tests\Feature\TenantIsolationTest (11 passed)
PASS Tests\Feature\DatabaseSchemaTest (6 passed)
PASS Tests\Feature\ApiHealthTest (3 passed)
PASS Tests\Unit\ExampleTest (2 passed)

Total: 33 passed (328 assertions)
```

