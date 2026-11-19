# Entity Relationship Diagram (ERD) Specification
## School ERP SaaS (EduCore Platform)

This document provides complete, modular Entity-Relationship Diagrams (ERD) using Mermaid for all 12 domains across the 133 database tables.

---

## 1. SaaS Core & Subscription Domain

```mermaid
erDiagram
    TENANT ||--o{ SCHOOL : "owns"
    TENANT ||--o{ TENANT_DOMAIN : "configures"
    TENANT ||--o{ TENANT_SETTING : "manages"
    TENANT ||--o{ TENANT_MEMBERSHIP : "associates"
    TENANT ||--o{ SUBSCRIPTION : "subscribes"
    SCHOOL ||--o{ SCHOOL_BRANCH : "operates"
    
    SUBSCRIPTION_PLAN ||--o{ PLAN_FEATURE : "includes"
    SUBSCRIPTION_PLAN ||--o{ SUBSCRIPTION : "binds"
    SUBSCRIPTION ||--o{ SUBSCRIPTION_INVOICE : "bills"
    SUBSCRIPTION_INVOICE ||--o{ SUBSCRIPTION_PAYMENT : "settles"
    SUBSCRIPTION ||--o{ USAGE_RECORD : "tracks"
    COUPON ||--o{ SUBSCRIPTION : "applies_discount"

    TENANT {
        uuid id PK
        string name
        string slug UK
        string organization_type
        string contact_email
        string contact_phone
        jsonb billing_address
        string currency
        string status
        timestamptz trial_ends_at
    }

    SCHOOL {
        uuid id PK
        uuid tenant_id FK
        string name
        string code
        string education_board
        boolean is_active
    }

    SCHOOL_BRANCH {
        uuid id PK
        uuid tenant_id FK
        uuid school_id FK
        string name
        string branch_code UK
        string campus_type
        boolean is_main_branch
        boolean is_active
    }

    SUBSCRIPTION_PLAN {
        uuid id PK
        string name
        string slug UK
        decimal monthly_price
        decimal annual_price
        int max_students
        int max_staff
        int max_branches
        int storage_limit_gb
        boolean is_active
    }

    SUBSCRIPTION {
        uuid id PK
        uuid tenant_id FK
        uuid plan_id FK
        string billing_interval
        string status
        timestamptz current_period_start
        timestamptz current_period_end
    }

    SUBSCRIPTION_INVOICE {
        uuid id PK
        uuid subscription_id FK
        string invoice_number UK
        decimal total_amount
        string status
    }

    SUBSCRIPTION_PAYMENT {
        uuid id PK
        uuid invoice_id FK
        string payment_gateway
        string gateway_payment_id
        decimal amount
        string status
    }
```

---

## 2. Authentication, Users & Role-Based Access Control (RBAC)

```mermaid
erDiagram
    TENANT ||--o{ USER_ROLE : "scopes"
    USER ||--o{ USER_ROLE : "has"
    ROLE ||--o{ USER_ROLE : "assigned_to"
    ROLE ||--o{ ROLE_PERMISSION : "contains"
    PERMISSION ||--o{ ROLE_PERMISSION : "granted_in"
    USER ||--o{ USER_SESSION : "establishes"
    USER ||--o{ LOGIN_HISTORY : "records"
    TENANT ||--o{ TENANT_MEMBERSHIP : "contains"
    USER ||--o{ TENANT_MEMBERSHIP : "holds"

    USER {
        uuid id PK
        string name
        string email UK
        string phone
        string password
        string status
        boolean two_factor_enabled
    }

    ROLE {
        uuid id PK
        uuid tenant_id FK
        string name
        string display_name
        boolean is_system_defined
    }

    PERMISSION {
        uuid id PK
        string name UK
        string module
        string display_name
    }

    ROLE_PERMISSION {
        uuid id PK
        uuid role_id FK
        uuid permission_id FK
    }

    USER_ROLE {
        uuid id PK
        uuid user_id FK
        uuid role_id FK
        uuid tenant_id FK
        uuid branch_id FK
    }

    USER_SESSION {
        uuid id PK
        uuid user_id FK
        string ip_address
        string user_agent
        timestamptz last_activity_at
    }

    LOGIN_HISTORY {
        uuid id PK
        uuid user_id FK
        string status
        string ip_address
        timestamptz login_at
    }
```

---

## 3. Academic Management Domain

```mermaid
erDiagram
    SCHOOL ||--o{ CLASS_LEVEL : "categorizes"
    SCHOOL_BRANCH ||--o{ ACADEMIC_SESSION : "runs"
    SCHOOL_BRANCH ||--o{ SCHOOL_CLASS : "hosts"
    CLASS_LEVEL ||--o{ SCHOOL_CLASS : "groups"
    BOARD ||--o{ SCHOOL_CLASS : "governs"
    SCHOOL_CLASS ||--o{ SECTION : "splits_into"
    SCHOOL_BRANCH ||--o{ SUBJECT : "offers"
    SCHOOL_CLASS ||--o{ CLASS_SUBJECT : "assigns"
    SUBJECT ||--o{ CLASS_SUBJECT : "taught_in"
    
    ACADEMIC_SESSION ||--o{ ACADEMIC_CALENDAR : "schedules"
    ACADEMIC_CALENDAR ||--o{ ACADEMIC_EVENT : "contains"
    SCHOOL_BRANCH ||--o{ TIMETABLE : "plans"
    TIMETABLE ||--o{ TIMETABLE_PERIOD : "slots"
    TIMETABLE_PERIOD ||--o{ TIMETABLE_ENTRY : "schedules"

    ACADEMIC_SESSION {
        uuid id PK
        uuid tenant_id FK
        uuid school_id FK
        uuid branch_id FK
        string name
        date start_date
        date end_date
        boolean is_current
    }

    BOARD {
        uuid id PK
        string code UK
        string name
    }

    CLASS_LEVEL {
        uuid id PK
        uuid school_id FK
        string name
        int order_index
    }

    SCHOOL_CLASS {
        uuid id PK
        uuid tenant_id FK
        uuid school_id FK
        uuid branch_id FK
        uuid class_level_id FK
        string name
        string code
        int order_index
    }

    SECTION {
        uuid id PK
        uuid branch_id FK
        uuid class_id FK
        string name
        int max_capacity
    }

    SUBJECT {
        uuid id PK
        uuid branch_id FK
        string name
        string code
        string subject_type
    }

    CLASS_SUBJECT {
        uuid id PK
        uuid class_id FK
        uuid subject_id FK
        boolean is_elective
        int weekly_periods
    }
```

---

## 4. Student Management & Admissions Domain

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ STUDENT : "enrolls_in"
    STUDENT ||--o{ STUDENT_ENROLLMENT : "maintains_history"
    ACADEMIC_SESSION ||--o{ STUDENT_ENROLLMENT : "academic_year"
    SCHOOL_CLASS ||--o{ STUDENT_ENROLLMENT : "grades"
    SECTION ||--o{ STUDENT_ENROLLMENT : "places"
    
    STUDENT ||--o{ STUDENT_GUARDIAN : "linked_to"
    GUARDIAN ||--o{ STUDENT_GUARDIAN : "cares_for"
    
    STUDENT ||--o{ STUDENT_DOCUMENT : "owns"
    STUDENT ||--o{ STUDENT_MEDICAL_RECORD : "has"
    STUDENT ||--o{ STUDENT_PROMOTION : "promoted_by"
    STUDENT ||--o{ STUDENT_TRANSFER : "transferred_by"
    
    SCHOOL_BRANCH ||--o{ STUDENT_ADMISSION : "processes"
    STUDENT_ADMISSION ||--o{ STUDENT : "creates_on_admit"

    STUDENT {
        uuid id PK
        uuid tenant_id FK
        uuid school_id FK
        uuid branch_id FK
        uuid user_id FK
        string admission_number UK
        date admission_date
        string first_name
        string last_name
        string gender
        date date_of_birth
        string status
    }

    STUDENT_ENROLLMENT {
        uuid id PK
        uuid tenant_id FK
        uuid branch_id FK
        uuid student_id FK
        uuid academic_session_id FK
        uuid class_id FK
        uuid section_id FK
        string roll_number
        date enrollment_date
        string status
    }

    GUARDIAN {
        uuid id PK
        uuid tenant_id FK
        uuid user_id FK
        string first_name
        string last_name
        string relationship
        string phone
        string email
    }

    STUDENT_GUARDIAN {
        uuid id PK
        uuid student_id FK
        uuid guardian_id FK
        boolean is_primary_contact
        boolean can_pickup
    }

    STUDENT_ADMISSION {
        uuid id PK
        uuid branch_id FK
        uuid academic_session_id FK
        string application_number UK
        string applicant_first_name
        string applicant_last_name
        string status
    }
```

---

## 5. Staff, Employees & Teacher Allocation

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ EMPLOYEE : "employs"
    EMPLOYEE_DEPARTMENT ||--o{ EMPLOYEE : "categorizes"
    EMPLOYEE_DESIGNATION ||--o{ EMPLOYEE : "ranks"
    USER ||--o{ EMPLOYEE : "access_identity"
    
    EMPLOYEE ||--o{ EMPLOYEE_DOCUMENT : "stores"
    EMPLOYEE ||--o{ EMPLOYEE_CONTRACT : "binds"
    EMPLOYEE ||--o{ EMPLOYEE_LEAVE : "requests"
    EMPLOYEE ||--o{ TEACHER_SUBJECT : "instructs"
    SUBJECT ||--o{ TEACHER_SUBJECT : "taught_by"
    
    EMPLOYEE ||--o{ CLASS_TEACHER : "leads"
    SECTION ||--o{ CLASS_TEACHER : "supervised_by"
    ACADEMIC_SESSION ||--o{ CLASS_TEACHER : "assigned_in"

    EMPLOYEE {
        uuid id PK
        uuid tenant_id FK
        uuid school_id FK
        uuid branch_id FK
        uuid user_id FK
        string employee_number UK
        string first_name
        string last_name
        date date_of_joining
        string employment_status
    }

    EMPLOYEE_DEPARTMENT {
        uuid id PK
        uuid branch_id FK
        string name
        string code
    }

    EMPLOYEE_DESIGNATION {
        uuid id PK
        uuid branch_id FK
        string name
    }

    TEACHER_SUBJECT {
        uuid id PK
        uuid employee_id FK
        uuid class_id FK
        uuid section_id FK
        uuid subject_id FK
    }

    CLASS_TEACHER {
        uuid id PK
        uuid employee_id FK
        uuid class_id FK
        uuid section_id FK
        uuid academic_session_id FK
    }
```

---

## 6. Attendance & Leave Applications

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ ATTENDANCE_SESSION : "records_daily"
    SECTION ||--o{ ATTENDANCE_SESSION : "attendance_for"
    ATTENDANCE_SESSION ||--o{ STUDENT_ATTENDANCE : "logs"
    STUDENT ||--o{ STUDENT_ATTENDANCE : "status_of"
    STUDENT_ATTENDANCE ||--o{ ATTENDANCE_CORRECTION : "rectifies"
    
    SCHOOL_BRANCH ||--o{ STAFF_ATTENDANCE : "tracks_daily"
    EMPLOYEE ||--o{ STAFF_ATTENDANCE : "punch_record"
    
    STUDENT ||--o{ LEAVE_APPLICATION : "student_leave"
    EMPLOYEE ||--o{ LEAVE_APPLICATION : "staff_leave"

    ATTENDANCE_SESSION {
        uuid id PK
        uuid branch_id FK
        uuid section_id FK
        uuid academic_session_id FK
        date attendance_date
        string session_type
        uuid taken_by FK
    }

    STUDENT_ATTENDANCE {
        uuid id PK
        uuid attendance_session_id FK
        uuid student_id FK
        string status
        string remarks
    }

    STAFF_ATTENDANCE {
        uuid id PK
        uuid branch_id FK
        uuid employee_id FK
        date attendance_date
        timestamptz check_in
        timestamptz check_out
        string status
    }

    LEAVE_APPLICATION {
        uuid id PK
        uuid branch_id FK
        string applicant_type
        date start_date
        date end_date
        string status
    }
```

---

## 7. Fee & Financial Management Domain

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ FINANCIAL_ACCOUNT : "operates"
    FINANCIAL_ACCOUNT ||--o{ EXPENSE : "disburses"
    EXPENSE_CATEGORY ||--o{ EXPENSE : "classifies"
    
    SCHOOL_BRANCH ||--o{ FEE_CATEGORY : "defines"
    FEE_CATEGORY ||--o{ FEE_STRUCTURE : "groups"
    FEE_STRUCTURE ||--o{ FEE_STRUCTURE_ITEM : "specifies_line_items"
    
    FEE_STRUCTURE ||--o{ STUDENT_FEE_ASSIGNMENT : "assigned_to_student"
    STUDENT ||--o{ STUDENT_FEE_ASSIGNMENT : "owes"
    FEE_CONCESSION ||--o{ STUDENT_FEE_ASSIGNMENT : "applies_discount"
    
    STUDENT ||--o{ FEE_INVOICE : "billed_to"
    STUDENT_ENROLLMENT ||--o{ FEE_INVOICE : "for_enrollment"
    FEE_INVOICE ||--o{ FEE_INVOICE_ITEM : "contains_items"
    
    STUDENT ||--o{ FEE_PAYMENT : "pays"
    FEE_PAYMENT ||--o{ FEE_PAYMENT_ALLOCATION : "allocates"
    FEE_INVOICE ||--o{ FEE_PAYMENT_ALLOCATION : "received_against"
    FEE_PAYMENT ||--o{ FEE_REFUND : "refunds"
    FEE_INVOICE ||--o{ FEE_FINE : "penalizes"

    FEE_STRUCTURE {
        uuid id PK
        uuid branch_id FK
        uuid academic_session_id FK
        string name
        string fee_frequency
    }

    FEE_INVOICE {
        uuid id PK
        uuid branch_id FK
        uuid student_id FK
        uuid enrollment_id FK
        string invoice_number UK
        decimal subtotal
        decimal discount_amount
        decimal fine_amount
        decimal total_amount
        decimal paid_amount
        decimal balance_amount
        date due_date
        string status
    }

    FEE_PAYMENT {
        uuid id PK
        uuid branch_id FK
        uuid student_id FK
        string receipt_number UK
        decimal amount_paid
        timestamptz payment_date
        string payment_method
        string status
    }

    FEE_PAYMENT_ALLOCATION {
        uuid id PK
        uuid fee_payment_id FK
        uuid fee_invoice_id FK
        decimal allocated_amount
    }
```

---

## 8. Examinations, Grading & Results Domain

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ EXAMINATION_TYPE : "classifies"
    EXAMINATION_TYPE ||--o{ EXAMINATION : "categorizes"
    ACADEMIC_SESSION ||--o{ EXAMINATION : "conducted_in"
    
    EXAMINATION ||--o{ EXAM_SCHEDULE : "timetables"
    SUBJECT ||--o{ EXAM_SCHEDULE : "exam_for"
    SCHOOL_CLASS ||--o{ EXAM_SCHEDULE : "evaluated_class"
    
    EXAM_SCHEDULE ||--o{ ASSESSMENT_COMPONENT : "contains_components"
    ASSESSMENT_COMPONENT ||--o{ STUDENT_ASSESSMENT : "scores"
    
    EXAM_SCHEDULE ||--o{ STUDENT_MARK : "graded_scores"
    STUDENT ||--o{ STUDENT_MARK : "achieved_by"
    
    EXAMINATION ||--o{ EXAM_RESULT : "summarizes"
    STUDENT ||--o{ EXAM_RESULT : "scored_by"
    
    SCHOOL_BRANCH ||--o{ REPORT_CARD_TEMPLATE : "designs"
    REPORT_CARD_TEMPLATE ||--o{ REPORT_CARD : "generates"
    STUDENT ||--o{ REPORT_CARD : "issued_to"

    EXAMINATION {
        uuid id PK
        uuid branch_id FK
        uuid session_id FK
        string name
        date start_date
        date end_date
        string status
    }

    EXAM_SCHEDULE {
        uuid id PK
        uuid examination_id FK
        uuid class_id FK
        uuid subject_id FK
        date exam_date
        decimal max_marks
        decimal pass_marks
    }

    STUDENT_MARK {
        uuid id PK
        uuid exam_schedule_id FK
        uuid student_id FK
        decimal marks_obtained
        string grade
        boolean is_absent
    }

    EXAM_RESULT {
        uuid id PK
        uuid examination_id FK
        uuid student_id FK
        decimal total_marks_obtained
        decimal percentage
        string final_grade
        string result_status
    }
```

---

## 9. Operations: Library, Transport, Inventory, Hostel, Visitors & Health

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ LIBRARY_BOOK : "catalogues"
    LIBRARY_BOOK ||--o{ BOOK_COPY : "stocks"
    BOOK_COPY ||--o{ BOOK_ISSUE : "circulates"
    
    SCHOOL_BRANCH ||--o{ TRANSPORT_VEHICLE : "operates"
    SCHOOL_BRANCH ||--o{ TRANSPORT_ROUTE : "navigates"
    TRANSPORT_ROUTE ||--o{ TRANSPORT_STOP : "halts_at"
    STUDENT ||--o{ STUDENT_TRANSPORT_ALLOCATION : "assigned_bus"
    
    SCHOOL_BRANCH ||--o{ INVENTORY_CATEGORY : "classifies_items"
    INVENTORY_CATEGORY ||--o{ INVENTORY_ITEM : "contains"
    INVENTORY_ITEM ||--o{ INVENTORY_PURCHASE : "procured"
    INVENTORY_ITEM ||--o{ INVENTORY_ISSUE : "consumed"
    
    SCHOOL_BRANCH ||--o{ HOSTEL_BUILDING : "maintains"
    HOSTEL_BUILDING ||--o{ HOSTEL_ROOM : "comprises"
    HOSTEL_ROOM ||--o{ HOSTEL_ALLOCATION : "accommodates"
    
    SCHOOL_BRANCH ||--o{ VISITOR : "logs"
    VISITOR ||--o{ VISITOR_PASS : "issues"
    STUDENT ||--o{ STUDENT_GATE_PASS : "authorizes_exit"
    
    STUDENT ||--o{ CLINIC_VISIT : "medical_log"
    STUDENT ||--o{ MEDICAL_CONDITION : "health_profile"

    LIBRARY_BOOK {
        uuid id PK
        string title
        string isbn
        string author
    }

    BOOK_COPY {
        uuid id PK
        uuid book_id FK
        string accession_number UK
        string status
    }

    TRANSPORT_ROUTE {
        uuid id PK
        string route_name
        string route_code UK
    }

    HOSTEL_ROOM {
        uuid id PK
        uuid building_id FK
        string room_number
        int capacity
    }

    VISITOR_PASS {
        uuid id PK
        uuid visitor_id FK
        string pass_number UK
        string purpose
    }
```

---

## 10. Communication, Support & System Audit

```mermaid
erDiagram
    SCHOOL_BRANCH ||--o{ ANNOUNCEMENT : "broadcasts"
    ANNOUNCEMENT ||--o{ ANNOUNCEMENT_ACKNOWLEDGEMENT : "tracks_read"
    
    USER ||--o{ NOTIFICATION : "receives"
    NOTIFICATION_TEMPLATE ||--o{ NOTIFICATION : "formats"
    NOTIFICATION ||--o{ NOTIFICATION_LOG : "delivers"
    
    USER ||--o{ MESSAGE : "authors"
    MESSAGE_CONVERSATION ||--o{ MESSAGE : "contains"
    
    USER ||--o{ SUPPORT_TICKET : "files"
    SUPPORT_TICKET ||--o{ TICKET_MESSAGE : "threads"
    
    TENANT ||--o{ AUDIT_LOG : "tracks_actions"
    USER ||--o{ AUDIT_LOG : "performed_by"
    TENANT ||--o{ FILE_UPLOAD : "stores_assets"

    ANNOUNCEMENT {
        uuid id PK
        string title
        text content
        string target_audience
        timestamptz published_at
    }

    NOTIFICATION {
        uuid id PK
        uuid user_id FK
        string type
        string title
        text body
        boolean is_read
    }

    SUPPORT_TICKET {
        uuid id PK
        string ticket_number UK
        string subject
        string priority
        string status
    }

    AUDIT_LOG {
        uuid id PK
        uuid tenant_id FK
        uuid user_id FK
        string action
        string entity_type
        uuid entity_id
        jsonb old_values
        jsonb new_values
        string ip_address
        timestamptz created_at
    }
```

---

## 11. Schema Constraints & Relationship Summary

| Domain | Table Count | Foreign Keys | Unique Constraints | Check Constraints | Indexes |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SaaS Core** | 6 | 12 | 6 | 14 | 18 |
| **Subscriptions** | 7 | 10 | 4 | 22 | 16 |
| **Auth & RBAC** | 7 | 16 | 5 | 8 | 19 |
| **Academic Management** | 16 | 42 | 14 | 94 | 45 |
| **Staff & HR** | 10 | 28 | 8 | 58 | 27 |
| **Student Management** | 9 | 24 | 9 | 72 | 31 |
| **Attendance & Leaves** | 5 | 16 | 4 | 36 | 18 |
| **Finance & Invoicing** | 16 | 48 | 10 | 184 | 42 |
| **Examinations & Grading** | 12 | 34 | 7 | 98 | 29 |
| **Communications & Tickets** | 9 | 18 | 3 | 32 | 19 |
| **Operations (All 7 sub-units)**| 22 | 72 | 9 | 260 | 48 |
| **System, Files & Auditing** | 5 | 14 | 2 | 50 | 14 |
| **Core Framework (Cache, Jobs, Sessions, etc.)** | 9 | 20 | 0 | 0 | 0 |
| **TOTAL** | **133** | **354** | **81** | **928** | **326** |

