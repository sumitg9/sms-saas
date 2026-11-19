# REST API Specification & Contract
## Project: Multi-Tenant School Management System (School ERP SaaS)
**API Version:** v1  
**Base URL:** `https://api.yourdomain.com/api/v1`  
**Protocol:** HTTPS only | Data Format: JSON  

---

## 1. Global API Standards & Conventions

### 1.1 Standard Request Headers
| Header | Type | Description | Mandatory |
| :--- | :--- | :--- | :--- |
| `Authorization` | String | Bearer token generated during authentication: `Bearer <token>` | Yes (Except public auth/webhooks) |
| `X-Tenant-ID` | UUID | UUID of the active organization (validated against user credentials) | Yes (For tenant-scoped routes) |
| `X-School-ID` | UUID | UUID of the specific school branch | Yes (For branch-scoped routes) |
| `Accept` | String | Must always be `application/json` | Yes |
| `Content-Type` | String | `application/json` (or `multipart/form-data` for file uploads) | Yes |

### 1.2 Standard Success Response Envelope
```json
{
  "success": true,
  "message": "Resource retrieved successfully",
  "data": { ... },
  "meta": {
    "pagination": {
      "total": 1250,
      "count": 25,
      "per_page": 25,
      "current_page": 1,
      "total_pages": 50
    },
    "timestamp": "2026-11-19T03:40:00Z"
  }
}
```

### 1.3 Standard Error Response Envelope (RFC 7807 Aligned)
```json
{
  "success": false,
  "code": "VALIDATION_FAILED",
  "message": "The given data failed validation.",
  "errors": {
    "admission_number": [
      "The admission number has already been taken in this school branch."
    ],
    "date_of_birth": [
      "The date of birth must be a date before today."
    ]
  },
  "timestamp": "2026-11-19T03:40:00Z"
}
```

### 1.4 HTTP Status Code Map
- `200 OK`: Request succeeded, returns data.
- `201 Created`: Resource successfully created.
- `204 No Content`: Resource deleted or action completed without return body.
- `400 Bad Request`: Malformed syntax or invalid state transition.
- `401 Unauthorized`: Missing or invalid Bearer token.
- `403 Forbidden`: Authenticated user lacks permission or does not belong to specified tenant.
- `404 Not Found`: Resource does not exist or belongs to another tenant (obscured for security).
- `422 Unprocessable Entity`: Form payload validation failed.
- `429 Too Many Requests`: Rate limit exceeded.
- `500 Internal Server Error`: Unhandled server exception (logged with correlation ID).

---

## 2. Authentication & Session Endpoints (`/api/v1/auth`)

### 2.1 User Login
- **Endpoint:** `POST /api/v1/auth/login`
- **Rate Limit:** 5 requests / minute
- **Request Body:**
```json
{
  "email": "principal@apexschool.edu",
  "password": "SecurePassword123!",
  "device_name": "Chrome on Windows 11"
}
```
- **Response (200 OK - Standard):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "c1f7b682-628d-4a11-8e3b-9a4891bca100",
      "first_name": "Sunil",
      "last_name": "Sharma",
      "email": "principal@apexschool.edu",
      "avatar_url": "https://storage.yourdomain.com/avatars/user.jpg"
    },
    "tenants": [
      {
        "id": "e8d38f21-7290-4e78-9e12-32b781bc9201",
        "name": "Apex Educational Trust",
        "slug": "apex-trust",
        "schools": [
          {
            "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
            "name": "Apex International School - North Campus",
            "code": "APEX-NORTH",
            "board": "CBSE"
          }
        ]
      }
    ],
    "token": "1|qX8zO9jK1...plainTextToken"
  }
}
```
- **Response (200 OK - 2FA Required):**
```json
{
  "success": true,
  "requires_two_factor": true,
  "two_factor_challenge_token": "challenge_token_jwt..."
}
```

### 2.2 Verify Two-Factor Authentication
- **Endpoint:** `POST /api/v1/auth/two-factor/verify`
- **Request Body:**
```json
{
  "challenge_token": "challenge_token_jwt...",
  "code": "489201"
}
```

### 2.3 Switch Active School Branch
- **Endpoint:** `POST /api/v1/auth/switch-school`
- **Request Body:**
```json
{
  "school_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d"
}
```

### 2.4 Logout
- **Endpoint:** `POST /api/v1/auth/logout`
- **Header:** `Authorization: Bearer <token>`
- **Response (204 No Content)**

---

## 3. Platform Administration Endpoints (`/api/v1/platform`)
*Requires `is_platform_admin = true`*

| Method | URI | Description |
| :--- | :--- | :--- |
| `GET` | `/platform/dashboard` | Global platform KPIs (MRR, Churn, Active Tenants, Storage) |
| `GET` | `/platform/tenants` | Paginated list of all tenants with subscription states |
| `POST` | `/platform/tenants` | Provision a new tenant organization manually |
| `GET` | `/platform/tenants/{id}` | Detailed tenant profile, schools, billing history |
| `PUT` | `/platform/tenants/{id}/suspend` | Suspend tenant with administrative justification |
| `PUT` | `/platform/tenants/{id}/reactivate` | Reactivate suspended tenant |
| `POST` | `/platform/tenants/{id}/impersonate`| Issue temporary impersonation token for support |
| `GET` | `/platform/plans` | List SaaS subscription plans & feature sets |
| `PUT` | `/platform/plans/{id}` | Update pricing, quotas, and module feature flags |

---

## 4. Academic Core & People Management Endpoints

### 4.1 Academic Years & Classes
| Method | URI | Description |
| :--- | :--- | :--- |
| `GET` | `/academics/years` | List academic years for the current school branch |
| `POST` | `/academics/years` | Create a new academic year and define terms |
| `PUT` | `/academics/years/{id}/set-current` | Toggle current active academic year |
| `GET` | `/academics/classes` | List classes, sections, and student headcount |
| `POST` | `/academics/classes` | Create a class (e.g., Grade 10) |
| `POST` | `/academics/classes/{id}/sections` | Add section to class with capacity & room allocation |
| `GET` | `/academics/subjects` | List subjects with theory/practical and credit tags |

### 4.2 Student Management (`/api/v1/students`)
- **List Students:** `GET /api/v1/students?class_id={uuid}&section_id={uuid}&search=rahul&status=active`
- **Create Student:** `POST /api/v1/students`
- **Payload:**
```json
{
  "first_name": "Aarav",
  "last_name": "Patel",
  "admission_number": "ADM-2026-0891",
  "admission_date": "2026-04-01",
  "gender": "male",
  "date_of_birth": "2012-08-15",
  "blood_group": "O+",
  "caste_category": "General",
  "class_section_id": "7a3e29f8-1123-4411-9a41-39a018bc1921",
  "roll_number": "14",
  "guardians": [
    {
      "relation_type": "father",
      "first_name": "Vikram",
      "last_name": "Patel",
      "phone": "+919876543210",
      "email": "vikram.patel@gmail.com",
      "is_primary_contact": true,
      "receives_financial_invoices": true
    }
  ]
}
```
- **Student Profile (360-degree):** `GET /api/v1/students/{id}`
- **Student Document Upload:** `POST /api/v1/students/{id}/documents` (Multipart)
- **Student Promotion / Transfer:** `POST /api/v1/students/promote-batch`

---

## 5. Attendance Operations (`/api/v1/attendance`)

### 5.1 Bulk Mark Daily Attendance
- **Endpoint:** `POST /api/v1/attendance/students/bulk`
- **Request Body:**
```json
{
  "class_section_id": "7a3e29f8-1123-4411-9a41-39a018bc1921",
  "date": "2026-11-19",
  "records": [
    { "student_id": "89b782da-1...", "status": "present" },
    { "student_id": "90c883eb-2...", "status": "absent", "remarks": "High fever reported" },
    { "student_id": "91d994fc-3...", "status": "late", "remarks": "Arrived 08:35 AM" }
  ]
}
```

### 5.2 Biometric Terminal Ingestion Webhook
- **Endpoint:** `POST /api/v1/attendance/biometric/webhook`
- **Signature Header:** `X-Biometric-Signature: sha256=...`
- **Payload:**
```json
{
  "device_serial": "BIO-GATE-01",
  "timestamp": "2026-11-19T07:45:12Z",
  "logs": [
    { "user_code": "EMP-104", "punch_time": "2026-11-19 07:44:02", "in_out": "in" }
  ]
}
```

---

## 6. Fee & Financial Management Endpoints (`/api/v1/fees`)

### 6.1 Create Fee Structure
- **Endpoint:** `POST /api/v1/fees/structures`
- **Payload:**
```json
{
  "fee_group_id": "f12019ab-8912-4011-8012-998811aabbcc",
  "fee_type_id": "t99001ba-1122-4433-8899-aabbccddeeff",
  "class_id": "c1122334-5566-7788-9900-aabbccddeeff",
  "amount": 25000.00,
  "due_date": "2026-07-15",
  "late_fine_type": "daily",
  "late_fine_amount": 50.00
}
```

### 6.2 Record Fee Payment (Offline Collection)
- **Endpoint:** `POST /api/v1/fees/payments/offline`
- **Payload:**
```json
{
  "student_id": "89b782da-1111-2222-3333-444455556666",
  "allocation_ids": [
    "fa111111-2222-3333-4444-555566667777"
  ],
  "amount_paid": 25000.00,
  "payment_method": "upi",
  "gateway_transaction_id": "UPI/20261119/9812984124",
  "notes": "Paid by parent via PhonePe"
}
```
- **Response:** Returns payment confirmation along with signed PDF receipt download URL.

### 6.3 Online Fee Checkout Initiation (Razorpay / Stripe)
- **Endpoint:** `POST /api/v1/fees/payments/initiate-online`
- **Payload:**
```json
{
  "allocation_ids": ["fa111111-2222-3333-4444-555566667777"]
}
```
- **Response:**
```json
{
  "success": true,
  "data": {
    "order_id": "order_Nz8912gX7190",
    "amount": 2500000,
    "currency": "INR",
    "key": "rzp_live_..."
  }
}
```

---

## 7. Examination & Results Endpoints (`/api/v1/exams`)

| Method | URI | Description |
| :--- | :--- | :--- |
| `GET` | `/exams` | List exams for the academic year |
| `POST` | `/exams` | Create exam event (e.g., Final Term Exam) |
| `POST` | `/exams/{id}/schedules` | Set paper dates, max marks, and pass marks |
| `GET` | `/exams/schedules/{id}/marks` | Fetch tabular mark entry sheet for a class |
| `POST` | `/exams/schedules/{id}/marks` | Save marks obtained by students |
| `POST` | `/exams/{id}/publish` | Publish results to Parent and Student portals |
| `GET` | `/exams/{id}/report-card/{student_id}` | Generate downloadable branded PDF report card |

---

## 8. Stakeholder Portal Endpoints

### 8.1 Parent Portal (`/api/v1/portal/parent`)
- `GET /portal/parent/children`: List all wards linked to the logged-in parent account across branches.
- `GET /portal/parent/child/{student_id}/dashboard`: Quick dashboard (today's attendance, due fees, homework).
- `GET /portal/parent/child/{student_id}/attendance`: Monthly attendance calendar.
- `GET /portal/parent/child/{student_id}/report-cards`: List and download verified PDF report cards.

### 8.2 Student Portal (`/api/v1/portal/student`)
- `GET /portal/student/timetable`: Weekly timetable schedule.
- `GET /portal/student/homework`: Pending and completed homework assignments.
- `POST /portal/student/homework/{id}/submit`: Submit homework proof (files/text).

### 8.3 Teacher Portal (`/api/v1/portal/teacher`)
- `GET /portal/teacher/schedule`: Today's class routine and substitution duties.
- `GET /portal/teacher/classes`: Classes and sections assigned to this teacher.
- `POST /portal/teacher/attendance`: Mark attendance directly from classroom mobile device.

---

## 9. Storage & Asset Uploads (`/api/v1/storage`)

To eliminate backend bandwidth bottlenecks, document and photo uploads execute directly against Supabase Storage via signed pre-authorized URLs.

1. **Request Upload Signature:** `POST /api/v1/storage/signed-upload-url`
   - Request: `{ "file_name": "transfer_cert.pdf", "mime_type": "application/pdf", "bucket": "student-documents-private" }`
   - Response: `{ "upload_url": "https://<supabase-id>.supabase.co/storage/v1/object/upload/sign/...", "file_path": "tenants/{tenant_id}/students/{student_id}/..." }`
2. **Client Upload:** Client performs direct `PUT` to `upload_url`.
3. **Commit Asset Record:** Client sends the committed `file_path` to the respective entity endpoint.

