# Module 10: Advanced Reports, Analytics and Certificate Management

## Executive Overview
The **Advanced Reports, Analytics and Certificate Management** system provides enterprise-grade data intelligence, multi-format reporting, and tamper-evident credential lifecycle management across the multi-tenant School ERP SaaS platform.

This module guarantees strict multi-tenant data isolation, non-repudiation audit logging for all exports and credentials, and robust background queue execution for high-volume data workloads.

---

## 1. Dashboard & Platform Analytics

### 1.1 Architectural Distinction: Actual Revenue vs. Estimated Projections
To adhere strictly to statutory compliance and prevent misleading financial reporting, the platform architecture fundamentally segregates settled cash collections from calculated recurring projections:
- **Actual Settled Revenue (`is_estimate: false`)**:
  - Computed from successful, settled subscription invoice payments (`SubscriptionPayment` with `status: 'succeeded'`).
  - Represents real, auditable, non-speculative cash received by the SaaS platform.
- **Estimated Projections (`is_estimate: true`)**:
  - Computed from active recurring subscription plan pricing (`SubscriptionPlan` price mapped to monthly/annual rates).
  - Explicitly marked in all payloads and UI widgets as an estimate to assist executive forecasting without misrepresenting financial actuals.

### 1.2 Multi-Persona Role Routing
The `/api/v1/analytics/dashboard` endpoint dynamically detects the authenticated user's role and renders tailored intelligence:
- **Platform Super Admin**: Total registered institutions, active/trial/expired subscriptions, cancellations/churn, plan distribution, tenant growth trajectories, storage volume (MB), and platform API activity.
- **School Admin & Principal**: Enrolled student count, active faculty and staff count, daily and monthly attendance rates, fee collection totals vs. outstanding balances, class-wise distribution breakdown, admission application pipeline, and examination performance averages.
- **Accountant**: Daily collections, monthly collections, total arrears, disbursements and expenses grouped by category, payment method distribution (Cash, Online, Card, UPI, Cheque), and fee defaulters ledger.
- **Teacher, Parent & Student**: Timetable workloads, individual attendance history, assigned academic materials, and personal performance summaries.

---

## 2. Advanced Multi-Domain Reporting Engine

### 2.1 Supported Business Domains
The reporting engine provides query filters, pagination, and multi-format exports across **11 core enterprise domains**:
1. `students`: Full student directory, admission date, class enrollment, gender, and status.
2. `admissions`: New admission applications, application dates, and approval workflow status.
3. `attendance`: Daily student attendance records, present/absent/late status, and attendance remarks.
4. `teachers`: Teaching faculty roster, academic departments, designations, and employee codes.
5. `staff`: Non-teaching administration and support staff directory.
6. `fees`: Fee payments, receipt numbers, payment methods, transaction timestamps, and amounts paid.
7. `expenses`: Institutional disbursements, expense categories, vouchers, and vendor details.
8. `examinations`: Exam results, grades, marks obtained, percentages, and pass/fail rankings.
9. `transport`: Fleet vehicles, driver rosters, route allocations, and bus stop coverage.
10. `library`: Book catalog, ISBN, stock quantity, and current circulation loans.
11. `inventory`: Inventory items, category taxonomy, current stock levels, and reorder alert thresholds.

### 2.2 Export Modes & Non-Repudiation Audit Trails
- **Synchronous CSV Export (`GET /api/v1/reports/{reportType}/export/csv`)**: Generates streamed RFC 4180 compliant CSV files with domain-specific headers.
- **Printable HTML Document (`GET /api/v1/reports/{reportType}/export/print`)**: Clean, formatted tabular view equipped with JavaScript auto-trigger `window.print()`.
- **Asynchronous Background Queue (`POST /api/v1/reports/{reportType}/export/async`)**: Dispatches `ProcessExportJob` to the background queue via `ExportJob` model. Ideal for large datasets (10,000+ rows) without blocking PHP worker threads.
- **Audit Logging**: Every export operation records an entry in `audit_logs` capturing `user_id`, `user_email`, `action: 'exported'`, `report_type`, `format`, and exact query parameters.

---

## 3. Certificate Management & Verification System

### 3.1 Supported Certificate Templates
The platform includes built-in system templates and allows custom template definition per tenant/branch:
- **Transfer Certificate (TC)**: Official student exit credential detailing conduct, attendance, fee clearance, and reason for withdrawal.
- **Bonafide Certificate**: Verification of bona fide student status in a given standard and section.
- **Character Certificate**: Testimonial of exemplary moral conduct and disciplinary record.
- **Study Certificate**: Proof of study duration and date of birth as per school admission registers.
- **Fee Clearance Certificate**: Formal clearance of tuition, examination, and ancillary liabilities.
- **Custom / Merit Certificates**: Customizable templates for sports, Olympiads, and academic honors.

### 3.2 Dynamic Placeholder Replacement
Templates support dynamic string placeholders replaced automatically upon issuance:
- `{{student_name}}` -> Student's full name.
- `{{admission_number}}` -> Permanent school admission number.
- `{{class_name}}` & `{{section_name}}` -> Current enrollment grade and section.
- `{{academic_year}}` -> Academic session name (e.g., "2026-2027").
- `{{father_name}}` & `{{mother_name}}` -> Parent/Guardian names resolved from relations.
- `{{dob}}` -> Formatted date of birth.
- `{{conduct}}` & `{{reason}}` -> Administrator-specified conduct and departure reason.
- `{{school_name}}` -> Institutional name.

### 3.3 Numbering & Anti-Fraud Verification
- **Automated Certificate Number**: Formatted as `CERT-{BRANCH_CODE}-{TYPE}-{YEAR}-{RANDOM_SEQ}` (e.g., `CERT-HSB-TRANSFER-2026-4821`).
- **Unique Verification Code**: High-entropy verification string `VER-{12_ALPHANUMERIC}` embedded into the certificate header and encoded in physical QR codes.
- **Public Verification Endpoint (`GET /api/v1/certificates/verify/{verificationCode}`)**:
  - Publicly accessible without authentication.
  - Allows universities, employers, and immigration bodies to verify certificate authenticity in real-time.
  - Returns school name, recipient name, title, issue date, and authentic status.

### 3.4 Permanent Revocation Lifecycle
If a certificate was issued erroneously or in bad faith:
- Authorized administrators can invoke `POST /api/v1/certificates/{id}/revoke` with a mandatory revocation reason.
- Status immediately transitions to `revoked`, recording `revocation_reason`, `revoked_at`, and `revoked_by`.
- Subsequent public QR code scans return HTTP `410 Gone` with a prominent red revoked banner explaining the exact revocation rationale and date.
- Complete audit trail recorded in `audit_logs` under action `certificate_revoked`.

---

## 4. API Endpoints Reference

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `GET` | `/api/v1/analytics/dashboard` | Role-routed analytics overview | Yes |
| `GET` | `/api/v1/analytics/platform` | Cross-tenant Super Admin metrics | Yes (Super Admin) |
| `GET` | `/api/v1/analytics/school` | Academic & operational executive metrics | Yes |
| `GET` | `/api/v1/analytics/financial` | Accountant collections, cash flow & defaulters | Yes |
| `GET` | `/api/v1/reports/{reportType}` | Paginated domain report data | Yes |
| `GET` | `/api/v1/reports/{reportType}/export/csv` | Download domain CSV export | Yes |
| `GET` | `/api/v1/reports/{reportType}/export/print` | Printable HTML tabular document | Yes |
| `POST` | `/api/v1/reports/{reportType}/export/async` | Queue async background export job | Yes |
| `GET` | `/api/v1/reports/export-jobs/{id}` | Poll export job status & download path | Yes |
| `GET` | `/api/v1/certificates/templates` | List certificate templates | Yes |
| `POST` | `/api/v1/certificates/templates` | Create certificate template | Yes |
| `GET` | `/api/v1/certificates` | List issued certificates with search & status filters | Yes |
| `POST` | `/api/v1/certificates/issue` | Issue certificate with placeholder replacement | Yes |
| `GET` | `/api/v1/certificates/{id}` | Retrieve single certificate details | Yes |
| `GET` | `/api/v1/certificates/{id}/print` | Official watermarked printable credential | Yes |
| `POST` | `/api/v1/certificates/{id}/revoke` | Revoke certificate with mandatory reason | Yes |
| `GET` | `/api/v1/certificates/verify/{code}` | Public QR code verification endpoint | **No (Public)** |

---

## 5. Verification & Test Suite Summary
The system has been verified using automated PHPUnit/Pest feature tests against a PostgreSQL multi-tenant database:
- `DashboardAnalyticsTest.php`: 5/5 passed (34 assertions)
- `ReportingEngineTest.php`: 5/5 passed (28 assertions)
- `CertificateManagementTest.php`: 5/5 passed (32 assertions)
- **Full Project Test Suite**: **173 passed (1,162 assertions)** with zero failures or regressions.

