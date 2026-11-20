# Project Rules & Architectural Standards
**School ERP & Multi-Tenant SaaS Platform**  
**Version:** 1.0.0 | **Effective Date:** November 20, 2026

All agents and developers working on this codebase **MUST strictly follow these rules** across all phases of architecture, implementation, security hardening, testing, and deployment.

---

## 1. Core Philosophy & Architectural Identity

1. **Multi-Tenant by Default:** The application operates on a shared-database, pooled-schema multi-tenancy model. Every model, table, query, file, and queue job must respect tenant boundaries.
2. **Defense-in-Depth:** Tenancy is enforced at three distinct layers:
   - **Application Layer:** Eloquent global scopes (`TenantScoped` trait).
   - **Kernel/Database Layer:** PostgreSQL Row-Level Security (`tenant_isolation_policy`).
   - **Routing/Middleware Layer:** `IdentifyTenant` and `VerifyTenantMembership`.
3. **Decoupled Architecture:** The Next.js 16 frontend and Laravel 11 API backend operate as separate services. Communication occurs strictly over HTTPS/REST with cookie/Bearer token authentication and CORS validation.

---

## 2. Multi-Tenancy Guardrails (Strict Enforcement)

- **Rule 2.1 — Model Scoping:** Every Eloquent model that stores tenant-specific data **MUST** use the `App\Traits\TenantScoped` trait.
- **Rule 2.2 — Database Columns & Indexing:**
  - Every tenant-scoped database table **MUST** include a `tenant_id` column of type `UUID` referencing `tenants(id)`.
  - Every tenant-scoped table **MUST** feature a B-Tree composite index on `(tenant_id, created_at DESC)` or single-column index on `(tenant_id)`.
- **Rule 2.3 — PostgreSQL Row-Level Security (RLS):**
  - All tenant tables **MUST** have PostgreSQL RLS enabled (`ALTER TABLE ... ENABLE ROW LEVEL SECURITY;`).
  - The `tenant_isolation_policy` must be attached checking:
    ```sql
    USING (
        tenant_id = current_tenant_id()
        OR current_setting('app.is_super_admin', true) = 'true'
        OR current_tenant_id() IS NULL
    )
    ```
- **Rule 2.4 — Never Trust Client `tenant_id`:** Never accept `tenant_id` from client request bodies (`$request->input('tenant_id')`). The active tenant ID must always be resolved server-side from `TenantContext` or authenticated user memberships.
- **Rule 2.5 — Global Scope Bypasses:** Never call `withoutGlobalScopes()` or `withoutGlobalScope(TenantScope::class)` on tenant models unless executing inside an explicit platform super-admin service or validated system cron.
- **Rule 2.6 — Reserved Subdomains:** The following subdomains are strictly reserved for platform infrastructure and cannot be claimed by tenant schools:
  `['www', 'api', 'admin', 'app', 'platform', 'mail', 'staging', 'dev', 'localhost']`.

---

## 3. Database & Financial Integrity Standards

- **Rule 3.1 — Decimal Precision:** All monetary amounts (`fee_invoices`, `fee_payments`, `fee_refunds`, `salary_components`, `expenses`, `subscriptions`) **MUST** use `DECIMAL(12, 2)` or `DECIMAL(14, 2)`. Floats are strictly prohibited.
- **Rule 3.2 — Atomic Transactions:** Any operation modifying multiple database tables (e.g. admission confirmation, batch attendance marking, fee collection & invoice allocation, payroll disbursement, subscription purchases) **MUST** be wrapped in `DB::transaction()`.
- **Rule 3.3 — Concurrency & Double-Submission Defense:** Use database row locking (`lockForUpdate()`) and atomic upserts (`updateOrCreate()`) to prevent race conditions during concurrent online payments and roster updates.
- **Rule 3.4 — Cascading Deletes:** Unsafe cascading deletes on historical academic and financial records are prohibited. Use soft deletes (`SoftDeletes`) and restricted foreign keys on:
  - `students`, `employees`, `fee_invoices`, `fee_payments`, `exam_results`.
- **Rule 3.5 — Supabase Connection Mode:**
  - Web workers and API threads **MUST** use Supabase Connection Pooler (`PORT 6543`, Transaction Mode) with `DB_EMULATE_PREPARES=true`.
  - Migrations and schema changes **MUST** use Direct Session Connection (`PORT 5432`).

---

## 4. Security & Cryptographic Standards

- **Rule 4.1 — Personal Access Token Lifetimes:** Sanctum personal access tokens must never have infinite lifetimes. Always enforce `'expiration' => env('SANCTUM_EXPIRATION', 10080)` (7 days default).
- **Rule 4.2 — Cross-Subdomain Session Cookies:** Session cookies must be configured with:
  - `SESSION_DOMAIN=.example.com` (leading dot for subdomain sharing)
  - `SESSION_SECURE_COOKIE=true` (HTTPS only)
  - `SESSION_SAME_SITE=lax`
- **Rule 4.3 — Rate Limiting:** All API endpoints must be governed by rate limiters:
  - `RateLimiter::for('api', 120 req/min)`
  - `RateLimiter::for('auth', 15 req/min)`
  - `RateLimiter::for('exports', 10 req/min)`
- **Rule 4.4 — File Upload Sanitization:**
  - Files must only be uploaded via the centralized `FileUploadController`.
  - Allowed MIME whitelist: `pdf, doc, docx, xls, xlsx, csv, png, jpg, jpeg, webp, svg, zip`.
  - Max file size: 10MB (10240 KB).
  - Executable extensions (`.php`, `.exe`, `.sh`, `.phtml`, `.js`) must be rejected with HTTP 422.
  - Storage paths must be isolated per tenant: `tenants/{tenant_id}/{folder}/{uuid}.{ext}`.
- **Rule 4.5 — Payment Webhook Verification:** Razorpay webhooks **MUST** verify the HMAC SHA-256 signature using `RAZORPAY_WEBHOOK_SECRET` and log event IDs to `payment_webhook_events` for replay/idempotency protection.
- **Rule 4.6 — IDOR Prevention:** All resource endpoints (e.g. `/api/v1/students/{id}`) must verify that the resource belongs to the active tenant via Eloquent scoping and model policies (`Gate::authorize()`).

---

## 5. Backend API & Coding Conventions (Laravel 11)

- **Rule 5.1 — Standard API Response Envelope:** All controller responses must conform to the unified JSON envelope:
  ```json
  // Success Response
  {
    "success": true,
    "message": "Optional human-readable message",
    "data": { ... },
    "meta": { "current_page": 1, "total": 50 } // When paginated
  }

  // Error Response
  {
    "success": false,
    "code": "ERROR_CODE_ENUM",
    "message": "Human readable explanation",
    "errors": { "field": ["Validation error message"] },
    "timestamp": "2026-11-20T19:30:00Z"
  }
  ```
- **Rule 5.2 — HTTP Status Codes:**
  - `200 OK`: Successful retrieval or update.
  - `201 Created`: Successful creation of a new entity.
  - `400 Bad Request`: Malformed request or state conflict.
  - `401 Unauthenticated`: Missing or expired Bearer token.
  - `403 Forbidden`: Unauthorized tenant or insufficient RBAC permission.
  - `404 Not Found`: Entity does not exist within the active tenant.
  - `422 Unprocessable Content`: Validation failure.
  - `500 Internal Server Error`: Unhandled server exception (stack trace hidden in production).
- **Rule 5.3 — Eager Loading & N+1 Prevention:** Always eager load relationships (`with(['relation1', 'relation2'])`) on list endpoints. Use `withCount()` for aggregate counters.
- **Rule 5.4 — Input Validation:** Always validate incoming inputs using `$request->validate()` or a dedicated `FormRequest`. Never pass raw `$request->all()` into `Model::create()` or `Model::update()`.

---

## 6. Frontend Development Standards (Next.js 16)

- **Rule 6.1 — Route Completeness:** Every navigation link in `Sidebar.tsx` or dropdown menus **MUST** link to an existing, valid page component in `frontend/src/app`. Zero 404 broken routes permitted.
- **Rule 6.2 — API Communication:** All API requests must use the singleton `apiClient` (`frontend/src/lib/api-client.ts`), which automatically handles:
  - Base URL configuration (`NEXT_PUBLIC_API_URL`).
  - Bearer token header injection.
  - Tenant context headers (`X-Tenant-ID`, `X-School-ID`).
  - `withCredentials: true` for cookie transmission.
- **Rule 6.3 — Production Build Verification:** Every frontend change must compile cleanly with `npm run build` with zero TypeScript errors.
- **Rule 6.4 — Responsive & Accessible UI:** All pages must support:
  - Responsive layouts (mobile drawer, desktop sidebar).
  - Dark mode and light mode contrast compliance.
  - Loading skeletons/spinners for async states.
  - Clear success banners and error alerts for user feedback.

---

## 7. Testing & Quality Assurance Mandates

- **Rule 7.1 — Automated Test Coverage:** Every newly created API endpoint, service, or business workflow **MUST** have an automated Pest feature test in `backend/tests/Feature/`.
- **Rule 7.2 — Multi-Tenancy Isolation Test:** Every domain test suite must include a test asserting that Tenant A credentials or queries cannot access or modify Tenant B records.
- **Rule 7.3 — 100% Test Suite Pass Requirement:** Before declaring any task complete or preparing a release, the full test suite (`php artisan test`) must pass with **zero failures and zero flakes**.

---

## 8. Git & Operational Guardrails

- **Rule 8.1 — No Secrets in Git:** Never commit `.env`, `.env.production`, private keys, or API credentials.
- **Rule 8.2 — Template Synchronization:** When introducing a new environment variable, immediately update both:
  - `backend/.env.production.example` (or `frontend/.env.production.example`).
  - `docs/ENVIRONMENT_VARIABLES.md`.
- **Rule 8.3 — No Unauthorized Data Migrations:** Never migrate customer data or switch production DNS records without explicit, written stakeholder approval.

---

**Follow these rules at all times to maintain security, high performance, and enterprise reliability across the platform.**

