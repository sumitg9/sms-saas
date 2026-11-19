# Student Management and Admission Module Architecture & Implementation Guide

## 1. Overview & Architectural Philosophy

EduCore School ERP SaaS features an enterprise-grade, student-centric **Student Management and Admission System** designed for high-throughput admissions, comprehensive 360-degree student profiles, parent-child multi-tenancy relationships, verifiable digital credentials, and academic progression tracking.

### Core Architectural Principles
1. **Historical Immutability & Academic Continuity**:
   - Enrollment history is strictly append-only. When a student is promoted from Class 9 to Class 10 or transferred between sections, the historical `student_enrollments` record is archived with status `promoted` or `transferred`. A new enrollment record is created for the target academic session.
   - Historical marks, fee ledgers, and attendance metrics remain permanently linked to their historical session context.
2. **One-Parent, Multi-Child Relationship Model**:
   - Parents with multiple children enrolled in the same or different classes/branches within an institution are linked through unified `Guardian` entities.
   - Single parent accounts authenticate once and seamlessly switch between children via role-scoped policies (`StudentPolicy`), guaranteeing that parents can access only their legally linked wards.
3. **Deterministic State Machine for Admissions**:
   - Online admissions follow a strict, auditable lifecycle: `applied` &rarr; `under_review` &rarr; `waitlisted` / `approved` / `rejected` &rarr; `enrolled`.
   - Thread-safe, non-colliding sequential identifiers (`ADM-YYYY-XXXX` for applications, `STU-YYYY-XXXX` for permanent admission numbers, and `TC-YYYY-XXXX` for Transfer Certificates) are generated atomically within tenant and branch boundaries.
4. **Pre-flight Validated Bulk Data Ingestion**:
   - Ingesting legacy school rosters supports CSV/Excel batch processing with dry-run previewing, schema mapping, duplicate detection, and row-level error reporting before committing transactions to PostgreSQL.
5. **Verifiable Digital Identity**:
   - Student ID cards include signed digital verification payloads and cryptographically verifiable QR codes for gate security, library access, and transport validation.

---

## 2. Database Domain & Relational Schema

The Student Management and Admissions domain is normalized across 10 specialized PostgreSQL tables utilizing UUID primary keys, composite indexes, and strict tenant foreign key constraints.

```mermaid
erDiagram
    TENANT ||--o{ SCHOOL_BRANCH : owns
    SCHOOL_BRANCH ||--o{ STUDENT_ADMISSION : receives
    SCHOOL_BRANCH ||--o{ STUDENT : enrolls
    STUDENT ||--o{ STUDENT_ENROLLMENT : tracks
    ACADEMIC_SESSION ||--o{ STUDENT_ENROLLMENT : contextualizes
    SCHOOL_CLASS ||--o{ STUDENT_ENROLLMENT : assigns
    SECTION ||--o{ STUDENT_ENROLLMENT : divides
    STUDENT ||--o{ STUDENT_GUARDIAN : links
    GUARDIAN ||--o{ STUDENT_GUARDIAN : associates
    USER ||--o| GUARDIAN : authenticates
    STUDENT ||--o{ STUDENT_PROMOTION : records
    STUDENT ||--o{ STUDENT_TRANSFER : clears
    STUDENT ||--o{ STUDENT_MEDICAL_RECORD : monitors
    STUDENT ||--o{ STUDENT_DOCUMENT : stores
    SCHOOL_BRANCH ||--o{ IMPORT_JOB : logs
```

### 2.1 Entity Relationship Summary

| Model / Table | Primary Key | Key Relationships & Foreign Keys | Uniqueness / Business Constraints |
| :--- | :--- | :--- | :--- |
| `StudentAdmission` / `student_admissions` | UUID | `tenant_id`, `school_branch_id`, `academic_session_id`, `applying_for_class_id` | Unique `(tenant_id, application_number)` |
| `Student` / `students` | UUID | `tenant_id`, `school_branch_id`, `user_id` (optional student portal login) | Unique `(tenant_id, admission_number)` |
| `StudentEnrollment` / `student_enrollments` | UUID | `tenant_id`, `school_branch_id`, `student_id`, `academic_session_id`, `class_id`, `section_id` | Unique `(tenant_id, student_id, academic_session_id)` |
| `Guardian` / `guardians` | UUID | `tenant_id`, `user_id` (parent login account) | Unique `(tenant_id, mobile_phone)` / Unique `(tenant_id, email)` |
| `StudentGuardian` / `student_guardians` | UUID | `tenant_id`, `student_id`, `guardian_id` | Unique `(student_id, guardian_id)`, contains `relation_type`, `is_emergency_contact`, `has_pickup_rights`, `receives_invoices` |
| `StudentPromotion` / `student_promotions` | UUID | `tenant_id`, `student_id`, `from_session_id`, `to_session_id`, `from_class_id`, `to_class_id`, `promoted_by` | Tracks status (`promoted`, `retained`, `conditional`) and promotion date |
| `StudentTransfer` / `student_transfers` | UUID | `tenant_id`, `school_branch_id`, `student_id`, `approved_by` | Unique `(tenant_id, tc_number)`, contains clearance checklist and departure verification |
| `StudentMedicalRecord` / `student_medical_records` | UUID | `student_id`, `tenant_id` | Unique `student_id` (1:1), tracks blood group, allergies, conditions, doctor contact |
| `StudentDocument` / `student_documents` | UUID | `student_id`, `tenant_id` | Document type (`birth_certificate`, `transfer_certificate`, `aadhaar`, `marksheet`), file path, verification flag |
| `ImportJob` / `import_jobs` | UUID | `tenant_id`, `school_branch_id`, `user_id` | Tracks batch import status, total/processed/error counts, JSON error details |

---

## 3. Workflow & Lifecycle State Machines

### 3.1 Online Admission Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Applied: Online / Counter Submission (ADM-YYYY-XXXX)
    Applied --> UnderReview: Document Verification Initiated
    UnderReview --> Waitlisted: Quota Full / Merit List Standby
    UnderReview --> Rejected: Eligibility / Documentation Failure
    UnderReview --> Approved: Verification Passed & Seats Allocated
    Waitlisted --> Approved: Seat Vacated / Quota Upgrade
    Approved --> Enrolled: Section Assigned & Confirmed (STU-YYYY-XXXX)
    Rejected --> [*]
    Enrolled --> [*]
```

1. **Submission**: Applicant submits demographic details, guardian information, and preferred class. System generates atomic application tracking code: `ADM-{YEAR}-{SEQUENCE:04d}`.
2. **Review & Screening**: Admissions committee inspects certificates and assigns interview/merit scores.
3. **Waitlist Management**: Rank-ordered waitlist dynamically accommodates quota shifts.
4. **Admission Confirmation (`confirmAdmission`)**:
   - Generates official admission number: `STU-{YEAR}-{SEQUENCE:04d}`.
   - Creates demographic `Student` master record.
   - Finds or registers `Guardian` record and creates pivot link `StudentGuardian`.
   - Seeds initial active `StudentEnrollment` for current `AcademicSession` and assigned `Section`.
   - Transitions `StudentAdmission` status to `enrolled`.

---

### 3.2 Academic Progression Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Enrolled: Current Academic Year
    Enrolled --> Promoted: Pass Examination / Promotion Criteria
    Enrolled --> Retained: Academic Detention / Repeater
    Enrolled --> Withdrawn: Parent Withdrawal Notice
    Enrolled --> Transferred: Transfer Certificate Request
    
    Promoted --> NextSessionEnrollment: New Session Active (New Record Created)
    Withdrawn --> ReAdmitted: Return & Reinstatement
    Transferred --> TC_Issued: Clearance Checklist Verified
```

- When `promoteStudent` executes:
  1. The student's current enrollment status is updated to `promoted`.
  2. A row is inserted into `student_promotions` capturing source/destination sessions, classes, and administrative remarks.
  3. A new `StudentEnrollment` is inserted for the new academic session, leaving past academic records completely uncorrupted.

---

## 4. REST API Specification

All endpoints are prefixed with `/api/v1` and protected by Sanctum token authentication, `TenantContext` resolution, and Spatie granular permission gates.

### 4.1 Admission Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/admissions` | `admissions.view` | Paginated listing of applications with status/class/search filters |
| `POST` | `/admissions` | `admissions.create` | Submit new online/offline admission inquiry (`ADM-YYYY-XXXX`) |
| `GET` | `/admissions/{id}` | `admissions.view` | Detailed application dossier and documents |
| `PUT` | `/admissions/{id}/status` | `admissions.edit` | Transition status (`under_review`, `waitlisted`, `approved`, `rejected`) |
| `POST` | `/admissions/{id}/confirm` | `admissions.approve` | Confirm enrollment, create Student, link Guardian, assign section |
| `POST` | `/admissions/{id}/waitlist` | `admissions.edit` | Place applicant on prioritized waitlist with rank |

### 4.2 Student Profile & Lifecycle Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/students` | `students.view` | Directory of students with class, section, status, and search filters |
| `POST` | `/students` | `students.create` | Direct enrollment creation |
| `GET` | `/students/{id}` | `students.view` | Student 360 view (enrollments, guardians, medical, documents) |
| `PUT` | `/students/{id}` | `students.edit` | Update demographic and personal info |
| `DELETE` | `/students/{id}` | `students.delete` | Soft delete student record |
| `GET` | `/students/{id}/id-card` | `students.print` | Generate digital ID card payload with signed verification QR code |
| `POST` | `/students/{id}/withdraw` | `students.edit` | Process student withdrawal with exit reason and date |
| `POST` | `/students/{id}/re-admit` | `students.edit` | Re-admit withdrawn student back into active status |
| `POST` | `/students/{id}/transfer` | `students.edit` | Issue Transfer Certificate (`TC-YYYY-XXXX`) with clearance check |
| `GET` | `/students/{id}/timeline` | `students.view` | Fetch comprehensive academic progression audit history |
| `POST` | `/students/{id}/promote` | `students.edit` | Promote single student to next class/session |
| `POST` | `/students/bulk-promote` | `students.edit` | Batch promote students with class and section mapping |

### 4.3 Parent & Guardian Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/guardians` | `students.view` | List guardians with linked children count |
| `POST` | `/guardians` | `students.create` | Register or update guardian record |
| `GET` | `/guardians/my-children` | Authenticated Parent | Fetch all linked children for the logged-in parent (sibling portal) |

### 4.4 Bulk Ingestion & Export Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/students/import/template` | `students.view` | Download standardized CSV import template with sample data |
| `POST` | `/students/import/preview` | `students.create` | Validate import payload, detect duplicates, return row errors |
| `POST` | `/students/import/process` | `students.create` | Commit batch import with transactional rollback on failure |
| `GET` | `/students/import/history` | `students.view` | Audit history of batch import jobs |
| `GET` | `/students/export` | `students.export` | Export filtered student rosters to CSV |

---

## 5. Frontend UI Implementation

The frontend is built inside `frontend/src/app/students/page.tsx` utilizing Next.js 16 App Router, TypeScript, Tailwind CSS, Lucide icons, and accessible modal dialogs.

### Tab 1: Student Directory (Student 360)
- **High-Performance Filter Bar**: Class, section, enrollment status (`Active`, `Withdrawn`, `Transferred`, `Graduated`), and live full-text search.
- **Roster Table**: Renders student avatar, admission number, roll number, class/section badge, gender, phone, and status indicator.
- **Student 360 Drawer/Modal**: Tabbed drilldown displaying:
  1. *Personal & Demographic*: Full name, DOB, blood group, address, emergency contact, previous school.
  2. *Academic History*: Past and present sessions, classes, sections, roll numbers, and promotion timestamps.
  3. *Linked Guardians*: Father, mother, and legal guardians with relationship badges, emergency flags, and pickup privileges.
  4. *Medical & Health*: Chronic conditions, allergies, and emergency doctor details.
- **Digital ID Card Generator**: Visual preview of institution badge including student photo, admission details, blood group, barcode, and live QR code payload.
- **Single & Bulk Promotion Wizards**: Seamless transition of students to upcoming academic years with destination class/section selectors.

### Tab 2: Admissions Pipeline
- **KPI Metrics Ribbon**: Real-time counter cards showing Total Inquiries, Under Review, Waitlisted, Approved, and Confirmed Enrolled.
- **Application Dossier Modal**: Comprehensive review interface for evaluating applicants, viewing uploaded credentials, assigning waitlist priorities, or approving candidates.
- **Enrollment Confirmation Modal**: Assigns approved applicants to specific academic sessions, classes, and sections with instant `STU-` number allocation.

### Tab 3: Guardians & Siblings
- **Multi-Child Sibling Roster**: Demonstrates 1:N parent-to-student architecture. Parents can be linked to multiple children across different grade levels, with pickup rights and emergency priority flags clearly displayed.

### Tab 4: Bulk Import System
- **Template Downloader**: One-click download of compliant CSV structure (`first_name`, `last_name`, `class_name`, `section_name`, `date_of_birth`, `gender`, `parent_name`, `parent_phone`, `parent_email`).
- **Interactive Dry-Run Pre-flight**: Validates columns, maps foreign keys (class/section), checks for admission number collisions, and flags invalid values before ingestion.
- **Transactional Commit**: Ingests confirmed records in batches and updates real-time progress counters.

---

## 6. Security, Tenancy, and RBAC Matrix

### 6.1 Multi-Tenant Isolation
- Every query is implicitly scoped by `TenantContext::getTenantId()` via the `TenantScoped` global Eloquent scope.
- Composite unique indexes on `(tenant_id, admission_number)` and `(tenant_id, application_number)` guarantee that school branches can maintain isolated numbering schemes without global collisions.
- Cross-tenant queries return HTTP 404 (Not Found) rather than 403, preventing resource enumeration and IDOR attacks.

### 6.2 Granular Permissions Matrix
| Role | View Students | Edit/Create Students | Delete Students | Admissions Manage | Approve Admissions | Export / Print |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Super Admin** | &check; | &check; | &check; | &check; | &check; | &check; |
| **School Admin** | &check; | &check; | &check; | &check; | &check; | &check; |
| **Principal** | &check; | &check; | &cross; | &check; | &check; | &check; |
| **Admission Officer**| &check; | &check; | &cross; | &check; | &check; | &check; |
| **Accountant** | &check; | &cross; | &cross; | &check; | &cross; | &check; |
| **Class Teacher** | &check; (Assigned) | &check; (Assigned) | &cross; | &cross; | &cross; | &check; |
| **Teacher** | &check; (Assigned) | &cross; | &cross; | &cross; | &cross; | &cross; |
| **Parent** | &check; (Own Wards) | &cross; | &cross; | &cross; | &cross; | &check; (ID Card) |
| **Student** | &check; (Own Profile) | &cross; | &cross; | &cross; | &cross; | &check; (ID Card) |

---

## 7. Automated Test Suite & Verification

The Student Management and Admissions module is backed by comprehensive Pest/PHPUnit feature tests in `backend/tests/Feature/StudentManagementTest.php`.

### Test Coverage Highlights
1. `admission application lifecycle submission review and waitlist`: Verifies online application ingestion, status transitions, and waitlist ranking.
2. `confirm admission creates student profile guardian and enrollment`: Verifies atomic transaction converting an applicant to a permanent student with guardian links and active enrollment.
3. `student 360 profile and id card generation with qr code`: Validates profile aggregation and digital ID card token and QR code payload creation.
4. `one parent account supports multiple children and blocks unrelated children`: Validates parent sibling access and strict IDOR boundary blocking unrelated students.
5. `academic progression preserves historical enrollment records`: Verifies that promoting a student creates a new enrollment without modifying or destroying past academic history.
6. `student withdrawal readmission and transfer certificate`: Tests withdrawal workflows, reinstatement, and Transfer Certificate (`TC-YYYY-XXXX`) generation with clearance checks.
7. `bulk import system template preview validation and processing`: Tests dry-run CSV parsing, missing column validation, and transactional batch persistence.
8. `tenant isolation prevents access to another school students`: Validates that a School Admin from Tenant A cannot view or manipulate students belonging to Tenant B.

**Total Automated Suite Verification:**
- **52 Passed Tests, 458 Assertions** across the full backend suite.
- **Zero Failures, Zero Deprecations.**

