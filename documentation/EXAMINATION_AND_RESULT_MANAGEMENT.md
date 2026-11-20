# Examination & Result Management Module Documentation

## 1. Architectural Overview

The **Examination & Result Management System** provides an enterprise-grade, board-agnostic assessment and grading platform for multi-tenant K-12 schools, higher education institutions, and educational networks.

Rather than hardcoding the assessment conventions of a specific education board (e.g. CBSE, ICSE, Cambridge IGCSE, IB, or State Boards), the system implements an extensible **Dynamic Grading Engine** and **Assessment Component Architecture**. Schools can customize evaluation intervals, weightage distributions, grade points, ranking visibility policies, and printable report card templates.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Master Examination Session                      │
│     (e.g., Mid-Term Assessment 2026-27, CBSE Board Pre-Board)         │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ 1 : N
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        Exam Subject Schedules                          │
│         (Class 10A - Mathematics, Date, Hall, Max: 100, Pass: 33)      │
└───────────┬───────────────────────────────────────────────┬────────────┘
            │ 1 : N                                         │ 1 : N
            ▼                                               ▼
┌───────────────────────────────┐               ┌────────────────────────┐
│     Assessment Components     │               │      Student Marks     │
│ (Theory: 80, Practical: 20,   │               │ (Marks Obtained,       │
│  Periodic Test, Notebook)     │               │  Absent Flag, Remarks) │
└───────────┬───────────────────┘               └───────────┬────────────┘
            │                                               │
            └───────────────────────┬───────────────────────┘
                                    │ 1 : N
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        Student Assessment Breakdown                    │
│                 (Theory: 74/80, Practical: 19/20, Total: 93)           │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                       Result Calculation & Ranking                     │
│        (Aggregate %, Dynamic Grade Range Mapping, Tie-Break Ranks)      │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│               Report Card Builder & Multi-Portal Publishing             │
│   (Configurable Ranking Toggle, Attendance Summary, Signatures Block)  │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Core Functional Domains

### 2.1 Examination Sessions & Scheduling
- **Master Examination Model (`examinations`)**: Stores title, assessment type, academic session, date range, description, and workflow status (`draft`, `scheduled`, `ongoing`, `completed`, `published`).
- **Subject Scheduling (`exam_schedules`)**: Maps a class and subject to a specific date, start/end time slot, classroom/hall number, maximum marks, and minimum passing marks.
- **Assessment Sub-Components (`assessment_components`)**: Enables multi-part evaluation structures within a single examination subject (e.g., Theory, Practical, Viva, Periodic Test, Notebook Evaluation, Subject Enrichment Activity).

### 2.2 Teacher Marks Entry & Audit Workflow
- **Granular Subject Teacher Authorization**: Teachers are strictly authorized to enter marks only for their assigned class and subject schedules via `teacher_subjects`. Administrative roles (`platform_admin`, `school_owner`, `school_admin`, `principal`) retain global institution-wide access.
- **Instant Validation**: Enforces non-negative marks and bounds checks ($0 \le \text{marks\_obtained} \le \text{max\_marks}$).
- **Absentee Tracking (`is_absent`)**: Flags absent students explicitly, recording score as `null`/`0.00` without skewing pass/fail statistics.
- **Bulk CSV Export & Import**:
  - `GET /api/v1/examinations/schedules/{id}/template`: Generates pre-populated CSV template with `enrollment_id`, `student_id`, `admission_number`, `student_name`, `roll_number`.
  - `POST /api/v1/examinations/schedules/{id}/import`: Parses CSV in bulk, validating rows inside a database transaction.
- **Audit-Trailed Marks Correction (`result_corrections`)**:
  - Post-submission modifications are logged in an immutable audit trail (`old_marks`, `new_marks`, `reason`, `requested_by`, `status`).
  - Score updates require two-man rule verification: a principal or administrator with `examinations.publish` permission must approve or reject the change before student records are updated.

### 2.3 Dynamic Board-Agnostic Grading Engine
- **Configurable Grading Schemes (`grading_schemes`)**:
  - Pre-seeded with **CBSE 9-Point Scale** ($A1, A2, B1, B2, C1, C2, D, E1, E2$), **Percentage Scale** ($Distinction, First Class, Pass$), and **GPA Scale** ($4.0 / 10.0$).
  - Supports institutional custom bands without hardcoded if/else rules.
- **Grade Ranges (`grading_ranges`)**: Defines `grade_name`, `min_percentage`, `max_percentage`, `grade_point`, and descriptive qualitative remarks.
- **Subject & Overall Evaluation**:
  - Dynamically computes percentage: $\text{percentage} = \frac{\text{obtained}}{\text{max}} \times 100$.
  - Assigns grade letter and grade point by locating the matching percentage interval.
  - Determines pass/fail status per subject based on configurable `pass_marks`.

### 2.4 Class Ranking & Ties
- **Ranking Calculation**: Calculates cumulative scores across all subjects scheduled for the class.
- **Tie-Breaking Algorithm**: Students with identical aggregate marks and percentage receive identical rank, with subsequent ranks advancing cleanly (e.g., Rank 1, Rank 1, Rank 3).
- **Configurable Ranking Visibility (`show_ranking`)**:
  - Schools adopting non-competitive, formative, or progressive pedagogies can disable rank display (`show_ranking = false`).
  - When disabled, student transcripts omit rank badges while preserving performance analytics for internal school use.

### 2.5 Report Card Template Builder & Publishing
- **Official Student Transcript Payload**:
  - School Details: Institution name, affiliation code, campus address, contact details, official crest/logo.
  - Student Profile: Name, admission number, roll number, class & section.
  - Subject Breakdown: Subject name, code, max marks, pass marks, marks obtained, assigned grade, grade point, and pass/fail indicator.
  - Overall Performance: Total marks, aggregate percentage, final grade, outcome status (`PASSED`, `PROMOTED`, `FAILED`).
  - Attendance Box: Total academic working days, days present, attendance percentage.
  - Qualitative Observations: Class teacher remarks and principal remarks.
  - Authentication Block: Signature lines for Class Teacher, Parent/Guardian, and Principal Seal.
- **Multi-Portal Publishing (`result_publications`)**:
  - Results are held in draft state until formally released.
  - Controlled visibility: `allow_parent_view` and `allow_student_view` toggles prevent premature disclosure before moderation.

### 2.6 Class Analytics & Performance Insights
- **Aggregated Performance Metrics**: Total enrolled, appeared, passed, failed, class average percentage, highest mark, lowest mark.
- **Class Toppers Podium**: Top 3 / Top 5 rank holders with Gold, Silver, and Bronze badges.
- **Grade Distribution Frequency**: Recharts bar chart showing the frequency distribution of students across grades ($A1, A2, B1$, etc.).

---

## 3. Database Schema Reference

| Table Name | Primary Key | Description |
|:---|:---|:---|
| `exam_types` | `UUID` | Examination categories (e.g., Unit Test, Periodic Assessment, Half-Yearly, Board Exam). |
| `examinations` | `UUID` | Master examination milestones scoped to tenant and branch. |
| `exam_schedules` | `UUID` | Timetable linking examination, class, subject, date, room, and max/pass marks. |
| `assessment_components` | `UUID` | Sub-score components (Theory, Practical, Viva, Project Work) per schedule. |
| `student_marks` | `UUID` | Core mark records linked to student enrollment and exam schedule. |
| `student_assessments` | `UUID` | Itemized marks for each sub-assessment component. |
| `result_corrections` | `UUID` | Audit trail for post-submission score adjustments with review status. |
| `grading_schemes` | `UUID` | Board-level grading definitions (CBSE, ICSE, Percentage, GPA). |
| `grading_ranges` | `UUID` | Range intervals mapping percentages to letter grades and grade points. |
| `result_publications` | `UUID` | Publication release status with portal visibility flags. |
| `report_card_templates`| `UUID` | Configurable report card layout schemas and styling options. |
| `report_cards` | `UUID` | Historical generated report card records with signature tracking. |

---

## 4. API Endpoints Catalog

### Master Examinations & Types
- `GET /api/v1/examinations/types` - List examination types.
- `POST /api/v1/examinations/types` - Create examination type.
- `GET /api/v1/examinations` - List examinations with session/type filters.
- `POST /api/v1/examinations` - Create master examination session.
- `GET /api/v1/examinations/{id}` - Get examination details.
- `PUT /api/v1/examinations/{id}` - Update examination details.

### Exam Scheduling
- `POST /api/v1/examinations/schedules` - Schedule subject examination with duration and room.
- `GET /api/v1/examinations/{id}/schedules` - List scheduled subjects for an examination.
- `GET /api/v1/examinations/schedules/{scheduleId}` - Get schedule details and components.

### Marks Entry & Import
- `GET /api/v1/examinations/schedules/{scheduleId}/marks` - Fetch enrolled students and existing marks.
- `POST /api/v1/examinations/schedules/{scheduleId}/marks` - Enter or update marks (teacher authorization verified).
- `GET /api/v1/examinations/schedules/{scheduleId}/template` - Download pre-filled CSV marks template.
- `POST /api/v1/examinations/schedules/{scheduleId}/import` - Bulk import marks from CSV content.

### Marks Correction Workflow
- `GET /api/v1/examinations/corrections` - List correction requests with status filter.
- `POST /api/v1/examinations/marks/{markId}/correction` - Request a score correction with reason.
- `POST /api/v1/examinations/corrections/{correctionId}/review` - Approve or reject correction request.

### Result Calculation & Publication
- `POST /api/v1/examinations/{id}/classes/{classId}/calculate` - Compute class results, grades, and rankings.
- `POST /api/v1/examinations/{id}/publish` - Publish results to student and parent portals.
- `GET /api/v1/examinations/{id}/classes/{classId}/performance` - Class analytics KPI summary.
- `GET /api/v1/examinations/{id}/classes/{classId}/toppers` - Class toppers leaderboard.
- `GET /api/v1/examinations/{id}/classes/{classId}/subject-wise` - Subject performance metrics.
- `GET /api/v1/examinations/students/{studentId}/history` - Historical examination results for a student.

### Report Cards & Grading Schemes
- `GET /api/v1/examinations/grading-schemes` - List grading schemes with range intervals.
- `POST /api/v1/examinations/grading-schemes` - Create configurable grading scheme.
- `GET /api/v1/examinations/grading-schemes/{id}` - Get grading scheme details.
- `GET /api/v1/examinations/report-cards/templates` - List report card templates.
- `POST /api/v1/examinations/report-cards/templates` - Create report card template.
- `GET /api/v1/examinations/{id}/students/{studentId}/report-card` - Generate official student report card.

---

## 5. Security & Multi-Tenancy Guarantees

1. **Tenant Isolation**: Every database query is scoped by `tenant_id` via the `TenantScoped` Eloquent trait. Cross-tenant reads and writes are rejected with `404 Not Found` or `403 Forbidden`.
2. **Teacher Authorization Boundary**: Non-administrative teachers can only input marks for classes and subjects officially mapped to them in `teacher_subjects`.
3. **Audit Trail Integrity**: Any marks change after initial entry must pass through `result_corrections` with requester identification, timestamp, justification text, and administrative sign-off.
4. **Publication Guardrails**: Only users with the `examinations.publish` permission can release results to students and parents.

