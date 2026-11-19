# Teacher, Employee, HR, and Payroll Management Architecture & Implementation Guide

## 1. Overview & Architectural Philosophy

EduCore School ERP SaaS features an integrated, multi-tenant **Staff, Teacher, HR, and Payroll Management System** engineered for comprehensive human capital administration, faculty workload optimization, leave governance, and statutory-neutral configurable compensation.

### Core Architectural Principles
1. **Unified Staff Master (`employees`)**:
   - Single demographic and employment foundation for all institutional personnel: teaching faculty, administrative staff, finance officers, facilities crew, and transport drivers.
   - Distinct from system user accounts (`users`), allowing staff members to be registered with or without software credentials, while supporting granular role-based identity linkage (`user_id`).
2. **Faculty Workload & Timetable Governance**:
   - Seamless integration between teachers and the Academic Timetable engine.
   - Workload capacity analytics compute weekly teaching periods, free periods, subject distribution, and class teacher assignments.
   - Deterministic substitute allocation validates date alignment, teacher absence, and real-time room/period conflict checks to eliminate double booking.
3. **Automated Leave & Quota Lifecycle**:
   - Leave types define annual quotas, paid/unpaid classifications, and carry-forward rules per branch.
   - Application approval atomically decrements available balances in `employee_leaves` and stamps daily `employee_attendance` logs with `status = 'on_leave'`.
4. **Configurable, Statutory-Neutral Payroll Engine**:
   - Avoids hardcoded tax rates or regional statutory deductions.
   - Dynamic `salary_components` allow schools to configure fixed or percentage-based allowances (e.g., Basic Pay, HRA, DA, Special Allowance) and deductions (e.g., PF, PT, TDS, Medical Insurance).
   - Automated pro-rata attendance adjustments calculate loss of pay (`LOP`) for unpaid leaves.
   - Multi-stage lifecycle: `draft` &rarr; `processing` &rarr; `approved` (payslips published) &rarr; `paid` (disbursement recorded).
5. **Strict Multi-Tenant Scoping & Privilege Enforcement**:
   - Strict tenant and branch scoping on all records.
   - Teacher permissions strictly restrict educators to assigned classes and linked student dossiers (`StudentPolicy`).
   - HR managers and Accountants possess scoped administrative oversight.

---

## 2. Database Domain & Relational Schema

The domain spans 18 normalized PostgreSQL tables with UUID primary keys, composite uniqueness constraints, and foreign key cascades.

```mermaid
erDiagram
    TENANT ||--o{ SCHOOL_BRANCH : owns
    SCHOOL_BRANCH ||--o{ EMPLOYEE_DEPARTMENT : contains
    EMPLOYEE_DEPARTMENT ||--o{ EMPLOYEE_DESIGNATION : classifies
    SCHOOL_BRANCH ||--o{ EMPLOYEE : employs
    USER ||--o| EMPLOYEE : authenticates
    EMPLOYEE ||--o{ EMPLOYEE_CONTRACT : signs
    EMPLOYEE ||--o{ EMPLOYEE_DOCUMENT : verifies
    EMPLOYEE ||--o{ EMPLOYEE_LEAVE : tracks
    EMPLOYEE ||--o{ EMPLOYEE_ATTENDANCE : logs
    EMPLOYEE ||--o{ LEAVE_APPLICATION : requests
    EMPLOYEE ||--o| EMPLOYEE_EXIT : records
    EMPLOYEE ||--o{ TEACHER_SUBJECT : instructs
    EMPLOYEE ||--o{ CLASS_TEACHER : leads
    EMPLOYEE ||--o{ TIMETABLE_ENTRY : teaches
    TIMETABLE_ENTRY ||--o{ TIMETABLE_SUBSTITUTION : replaces
    SCHOOL_BRANCH ||--o{ SALARY_COMPONENT : configures
    SCHOOL_BRANCH ||--o{ SALARY_STRUCTURE : templates
    SALARY_STRUCTURE ||--o{ SALARY_STRUCTURE_ITEM : includes
    SALARY_COMPONENT ||--o{ SALARY_STRUCTURE_ITEM : defines
    EMPLOYEE ||--o| EMPLOYEE_SALARY_PROFILE : assigns
    SALARY_STRUCTURE ||--o{ EMPLOYEE_SALARY_PROFILE : structures
    SCHOOL_BRANCH ||--o{ PAYROLL_PERIOD : runs
    PAYROLL_PERIOD ||--o{ PAYSLIP : generates
    EMPLOYEE ||--o{ PAYSLIP : receives
```

### 2.1 Entity Relationship Summary

| Model / Table | Primary Key | Key Relationships & Foreign Keys | Uniqueness / Business Constraints |
| :--- | :--- | :--- | :--- |
| `EmployeeDepartment` / `employee_departments` | UUID | `tenant_id`, `branch_id` | Unique `(branch_id, code)` |
| `EmployeeDesignation` / `employee_designations` | UUID | `tenant_id`, `branch_id`, `department_id` | Unique `(branch_id, code)` |
| `Employee` / `employees` | UUID | `tenant_id`, `school_id`, `branch_id`, `user_id`, `department_id`, `designation_id` | Unique `(branch_id, employee_code)` |
| `EmployeeContract` / `employee_contracts` | UUID | `tenant_id`, `employee_id` | Tracks contract type, start/end dates, gross salary, active flag |
| `EmployeeDocument` / `employee_documents` | UUID | `tenant_id`, `employee_id` | Type (`degree`, `contract`, `police_verification`, `pan`), verification status |
| `LeaveType` / `leave_types` | UUID | `tenant_id`, `branch_id` | Unique `(branch_id, code)`, annual quota, paid/unpaid flag |
| `EmployeeLeave` / `employee_leaves` | UUID | `tenant_id`, `employee_id`, `academic_session_id` | Unique `(employee_id, academic_session_id, leave_type)` |
| `LeaveApplication` / `leave_applications` | UUID | `tenant_id`, `branch_id`, `applicant_id`, `approved_by` | Tracks dates, total days, status (`pending`, `approved`, `rejected`) |
| `EmployeeAttendance` / `employee_attendance` | UUID | `tenant_id`, `branch_id`, `employee_id` | Unique `(employee_id, date)`, status (`present`, `absent`, `on_leave`) |
| `EmployeeExit` / `employee_exits` | UUID | `tenant_id`, `branch_id`, `employee_id`, `approved_by` | Unique `employee_id`, clearance checklist, notice/last working dates |
| `TeacherSubject` / `teacher_subjects` | UUID | `tenant_id`, `branch_id`, `employee_id`, `class_subject_id` | Unique `(employee_id, class_subject_id)` |
| `ClassTeacher` / `class_teachers` | UUID | `tenant_id`, `branch_id`, `class_id`, `section_id`, `employee_id`, `academic_session_id` | Unique `(section_id, academic_session_id)` |
| `TimetableSubstitution` / `timetable_substitutions` | UUID | `tenant_id`, `branch_id`, `timetable_entry_id`, `original_employee_id`, `substitute_employee_id` | Unique `(timetable_entry_id, date)` |
| `SalaryComponent` / `salary_components` | UUID | `tenant_id`, `branch_id` | Unique `(branch_id, code)`, fixed or percentage formula |
| `SalaryStructure` / `salary_structures` | UUID | `tenant_id`, `branch_id` | Unique `(branch_id, code)`, base salary template |
| `SalaryStructureItem` / `salary_structure_items` | UUID | `tenant_id`, `salary_structure_id`, `salary_component_id` | Unique `(salary_structure_id, salary_component_id)` |
| `EmployeeSalaryProfile` / `employee_salary_profiles` | UUID | `tenant_id`, `branch_id`, `employee_id`, `salary_structure_id` | Unique `employee_id`, payment method, custom component overrides |
| `PayrollPeriod` / `payroll_periods` | UUID | `tenant_id`, `branch_id`, `processed_by`, `approved_by` | Unique `(branch_id, start_date, end_date)` |
| `Payslip` / `payslips` | UUID | `tenant_id`, `branch_id`, `payroll_period_id`, `employee_id` | Unique `(tenant_id, payslip_number)`, unique `(payroll_period_id, employee_id)` |

---

## 3. Workflow & Lifecycle State Machines

### 3.1 Payroll Computation & Disbursement Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Draft: Open Period (Dates Set)
    Draft --> Processing: Run Batch Calculation
    Processing --> Processing: Recalculate / Attendance Adjustment
    Processing --> Approved: Review & Publish Payslips (Locked)
    Approved --> Paid: Bank Transfer / Disbursed (Payment Ref)
    Paid --> [*]
```

1. **Initialization (`createPayrollPeriod`)**: Sets cycle dates (e.g., Nov 1 to Nov 30).
2. **Calculation (`calculatePayroll`)**:
   - Evaluates basic salary from profile.
   - Computes configurable allowances (fixed amount or `%` of basic).
   - Computes configurable deductions (fixed amount or `%` of basic/gross).
   - Detects employee attendance records: unpaid leaves generate pro-rata Loss of Pay (`LOP = (basic / working_days) * unpaid_days`).
   - Generates sequential itemized payslip number: `PAY-YYYY-MM-XXXX`.
3. **Approval (`approvePayrollPeriod`)**: Locks period totals, marks payslips as `published`.
4. **Disbursement (`markPayrollPaid`)**: Sets status `paid`, stores transaction payment reference and date.

---

### 3.2 Leave Application & Attendance Linkage Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Pending: Staff Application Submitted
    Pending --> Approved: HR / Principal Authorization
    Pending --> Rejected: Ineligibility / Exam Duty Conflict
    
    state Approved {
        [*] --> BalanceDeduction: Used Days Incremented
        BalanceDeduction --> AttendanceMarking: Daily Logs Marked 'on_leave'
    }
```

---

## 4. REST API Specification

All endpoints are prefixed with `/api/v1` and protected by Sanctum token authentication, `TenantContext` resolution, and Spatie granular permission gates.

### 4.1 Employee Management Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/employees` | `staff.view` | Filterable employee directory (department, designation, status) |
| `POST` | `/employees` | `staff.create` | Register employee (`EMP-YYYY-XXXX`) with initial contract & leaves |
| `GET` | `/employees/{id}` | `staff.view` | Employee 360 profile, contracts, documents, and quotas |
| `PUT` | `/employees/{id}` | `staff.edit` | Update demographic, banking, and employment information |
| `DELETE` | `/employees/{id}` | `staff.delete` | Soft delete employee record |
| `POST` | `/employees/{id}/contracts` | `staff.edit` | Record or renew employment contract |
| `POST` | `/employees/{id}/documents` | `staff.edit` | Upload verified certificate or credentials |
| `POST` | `/employees/{id}/exit` | `staff.manage` | Process resignation/termination with clearance checklist |

### 4.2 Teacher Management Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/teachers` | `staff.view` | Faculty directory with class teacher statuses |
| `GET` | `/teachers/{id}` | `staff.view` | Teacher dossier with assigned subjects and classes |
| `GET` | `/teachers/{id}/workload` | `staff.view` | Workload analytics: periods/week, free periods, utilization % |
| `GET` | `/teachers/{id}/timetable` | `staff.view` | Full weekly timetable slot schedule (Mon–Sat) |
| `GET` | `/teachers/{id}/attendance` | `staff.view` | Attendance metrics (present, absent, late, on-leave) |
| `POST` | `/teachers/substitutions` | `staff.manage` | Allocate substitute teacher with strict collision checks |

### 4.3 Departments, Designations & Leave Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/departments` | `staff.view` | List departments with employee headcounts |
| `POST` | `/departments` | `staff.manage` | Create organizational department |
| `GET` | `/designations` | `staff.view` | List staff designations and levels |
| `POST` | `/designations` | `staff.manage` | Create organizational designation |
| `GET` | `/leaves/types` | `staff.view` | List leave types and annual quotas |
| `POST` | `/leaves/types` | `staff.manage` | Create branch leave type |
| `GET` | `/leaves/balances/{id}` | `staff.view` | Fetch employee remaining leave quotas |
| `GET` | `/leaves/applications` | `staff.view` | Paginated leave requests pipeline |
| `POST` | `/leaves/applications` | `staff.view` | Submit staff leave application |
| `PUT` | `/leaves/applications/{id}/review` | `staff.manage` | Approve/reject leave, deduct quota, mark attendance |

### 4.4 Payroll & Compensation Endpoints
| HTTP Method | Endpoint | Permission Required | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/payroll/components` | `payroll.view` | List configurable allowances and deductions |
| `POST` | `/payroll/components` | `payroll.manage` | Create component (fixed or % formula) |
| `GET` | `/payroll/structures` | `payroll.view` | List salary structure bands and component items |
| `POST` | `/payroll/structures` | `payroll.manage` | Create salary structure template |
| `POST` | `/payroll/employees/{id}/profile` | `payroll.manage` | Assign salary structure and payment method |
| `GET` | `/payroll/periods` | `payroll.view` | List monthly payroll cycles |
| `POST` | `/payroll/periods` | `payroll.manage` | Initialize new payroll period |
| `POST` | `/payroll/periods/{id}/calculate` | `payroll.manage` | Execute batch calculation for all active employees |
| `POST` | `/payroll/periods/{id}/approve` | `payroll.manage` | Approve period and publish employee payslips |
| `POST` | `/payroll/periods/{id}/pay` | `payroll.manage` | Record disbursement and transaction reference |
| `GET` | `/payroll/payslips` | `payroll.view` | Searchable list of generated payslips |
| `GET` | `/payroll/payslips/{id}` | `payroll.view` | Detailed itemized payslip dossier |

---

## 5. Frontend UI Implementation

The frontend workspace is built in `frontend/src/app/staff/page.tsx` utilizing Next.js 16 App Router, TypeScript, Tailwind CSS, and accessible modals:
1. **Employee Directory**:
   - Search bar and dropdown filters by department, designation, and employment status.
   - Comprehensive staff roster table with employee avatar, code, contact, employment type, and quick action buttons.
   - "Register Employee" modal with demographic, contract, and banking inputs.
   - "Staff 360" modal displaying contracts, documents, and leave balances.
   - "Contract Renewal" and "Exit / Resignation" modal with departmental clearance checklist (IT, Library, Finance, Admin).
2. **Teacher Workload & Substitutions**:
   - Faculty summary cards (Faculty Count, Class Teachers, 40 Period Weekly Capacity).
   - Workload analysis modal with utilization percentage bar, subject breakdown, and weekly capacity.
   - Timetable view modal displaying weekly period slots.
   - "Allocate Substitute" modal with real-time schedule conflict prevention.
3. **Leave Management**:
   - Annual quota overview cards (CL, SL, EL, ML).
   - Interactive leave applications table with status badges (`pending`, `approved`, `rejected`).
   - "Apply for Leave" modal and administrative "Review Application" modal with automated quota decrement.
4. **Payroll & Compensation**:
   - Active payroll cycles table with real-time calculation progress, gross/deduction totals, and payout counters.
   - Batch calculate, approve, and disburse actions.
   - Generated payslips table with one-click printable official payslip dossier with institution letterhead, earnings breakdown, statutory deductions, and net salary.
5. **Organization & Roles**:
   - Departments and designations configuration tables with live headcounts.

---

## 6. Automated Test Suite & Verification

The module is verified with 10 comprehensive feature tests in `backend/tests/Feature/StaffAndPayrollManagementTest.php`:
1. `employee registration generates code and initializes leave quotas` (0.95s)
2. `employee contract management and document attachment` (0.40s)
3. `employee exit processing handles resignation and clearance` (0.30s)
4. `teacher workload calculation and weekly timetable grid` (0.26s)
5. `substitute teacher allocation validates conflicts and prevents double booking` (0.28s)
6. `leave application lifecycle approval deducts balance and logs attendance` (0.30s)
7. `configurable payroll calculates allowances deductions and unpaid leave` (0.57s)
8. `payroll period approval and disbursement lifecycle` (0.33s)
9. `teacher access restriction ensures teachers only access assigned students` (0.51s)
10. `tenant isolation prevents access to another school staff and payroll` (0.40s)

**Automated Test Suite Summary:**
- **62 Passed Tests, 526 Assertions** across the full backend suite.
- **Frontend Typecheck:** 0 errors (`tsc --noEmit`).
- **Frontend Production Build:** Successful static generation of all 12 App Router routes including `/staff`.

