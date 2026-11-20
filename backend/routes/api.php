<?php

use App\Http\Controllers\Api\Academic\AcademicCalendarController;
use App\Http\Controllers\Api\Academic\AcademicSessionController;
use App\Http\Controllers\Api\Academic\BoardController;
use App\Http\Controllers\Api\Academic\ClassController;
use App\Http\Controllers\Api\Academic\SubjectController;
use App\Http\Controllers\Api\Academic\TimetableController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RolePermissionController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\Staff\DepartmentController;
use App\Http\Controllers\Api\Staff\EmployeeController;
use App\Http\Controllers\Api\Staff\LeaveController;
use App\Http\Controllers\Api\Staff\PayrollController;
use App\Http\Controllers\Api\Staff\TeacherController;
use App\Http\Controllers\Api\Student\AdmissionController;
use App\Http\Controllers\Api\Student\GuardianController;
use App\Http\Controllers\Api\Student\StudentController;
use App\Http\Controllers\Api\Student\StudentImportExportController;
use App\Http\Controllers\Api\Finance\FeeStructureController;
use App\Http\Controllers\Api\Finance\StudentFeeController;
use App\Http\Controllers\Api\Finance\PaymentController;
use App\Http\Controllers\Api\Finance\RazorpayWebhookController;
use App\Http\Controllers\Api\Finance\ExpenseController;
use App\Http\Controllers\Api\Finance\FinancialReportController;
use App\Http\Controllers\Api\Examination\GradingSchemeController;
use App\Http\Controllers\Api\Examination\ExaminationController;
use App\Http\Controllers\Api\Examination\MarksEntryController;
use App\Http\Controllers\Api\Examination\ResultController;
use App\Http\Controllers\Api\Examination\ReportCardController;
use App\Http\Controllers\Api\Lms\HomeworkController;
use App\Http\Controllers\Api\Lms\AssignmentSubmissionController;
use App\Http\Controllers\Api\Lms\LearningResourceController;
use App\Http\Controllers\Api\Lms\QuizController;
use App\Http\Controllers\Api\Portal\StudentPortalController;
use App\Http\Controllers\Api\Portal\ParentPortalController;
use App\Http\Controllers\Api\Portal\TeacherPortalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

// Health Endpoint
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'School ERP SaaS API is operational',
        'version' => 'v1',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::post('/2fa/challenge', [TwoFactorController::class, 'challenge']);
});

// Payment Webhook (Public, signature-verified & idempotent)
Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle']);

// Authenticated User Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    // Auth Management
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // 2FA Management
        Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable']);

        // Session Management & Device Tracking
        Route::get('/sessions', [SessionController::class, 'index']);
        Route::delete('/sessions/others', [SessionController::class, 'destroyOthers']);
        Route::delete('/sessions/{id}', [SessionController::class, 'destroy']);
    });

    // Roles and Permissions Directory
    Route::get('/roles', [RolePermissionController::class, 'indexRoles']);
    Route::get('/permissions', [RolePermissionController::class, 'indexPermissions']);
    Route::post('/users/{user}/roles', [RolePermissionController::class, 'assignRole'])
        ->middleware('role:school_admin,school_owner,platform_admin');
    Route::delete('/users/{user}/roles/{roleName}', [RolePermissionController::class, 'removeRole'])
        ->middleware('role:school_admin,school_owner,platform_admin');
});

// Tenant-Scoped Protected Routes
Route::middleware(['auth:sanctum', 'tenant.require', 'tenant.membership', 'branch.access'])->group(function () {
    Route::get('/tenancy/current', function (\App\Services\Tenancy\TenantContext $context) {
        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $context->getTenantId(),
                'tenant_name' => $context->getTenant()?->name,
                'branch_id' => $context->getBranchId(),
                'school_id' => $context->getSchoolId(),
                'is_super_admin' => $context->isSuperAdmin(),
            ],
        ]);
    });

    // ==========================================
    // Student Admissions CRM Routes
    // ==========================================
    Route::prefix('admissions')->group(function () {
        Route::get('/', [AdmissionController::class, 'index'])->middleware('permission:admissions.view');
        Route::post('/', [AdmissionController::class, 'store'])->middleware('permission:admissions.create');
        Route::get('/waitlist', [AdmissionController::class, 'waitlist'])->middleware('permission:admissions.view');
        Route::get('/{admission}', [AdmissionController::class, 'show'])->middleware('permission:admissions.view');
        Route::patch('/{admission}/status', [AdmissionController::class, 'updateStatus'])->middleware('permission:admissions.edit');
        Route::post('/{admission}/confirm', [AdmissionController::class, 'confirm'])->middleware('permission:admissions.approve');
    });

    // ==========================================
    // Student 360 & Directory Routes
    // ==========================================
    Route::prefix('students')->group(function () {
        // Bulk Import & Export
        Route::get('/import/template', [StudentImportExportController::class, 'template'])->middleware('permission:students.view');
        Route::post('/import/preview', [StudentImportExportController::class, 'preview'])->middleware('permission:students.create');
        Route::post('/import', [StudentImportExportController::class, 'import'])->middleware('permission:students.create');
        Route::get('/import/history', [StudentImportExportController::class, 'history'])->middleware('permission:students.view');
        Route::get('/export', [StudentImportExportController::class, 'export'])->middleware('permission:students.export');

        // CRUD & Batch Progression
        Route::get('/', [StudentController::class, 'index'])->middleware('permission:students.view');
        Route::post('/', [StudentController::class, 'store'])->middleware('permission:students.create');
        Route::post('/bulk-promote', [StudentController::class, 'bulkPromote'])->middleware('permission:students.edit');

        // Specific Student Workflows
        Route::get('/{student}/id-card', [StudentController::class, 'idCard'])->middleware('permission:students.print');
        Route::get('/{student}/timeline', [StudentController::class, 'timeline'])->middleware('permission:students.view');
        Route::post('/{student}/promote', [StudentController::class, 'promote'])->middleware('permission:students.edit');
        Route::post('/{student}/withdraw', [StudentController::class, 'withdraw'])->middleware('permission:students.edit');
        Route::post('/{student}/re-admit', [StudentController::class, 'reAdmit'])->middleware('permission:students.edit');
        Route::post('/{student}/transfer', [StudentController::class, 'transfer'])->middleware('permission:students.edit');

        // Multi-Guardian Association
        Route::post('/{student}/guardians', [GuardianController::class, 'store'])->middleware('permission:students.edit');

        // Core Student Record
        Route::get('/{student}', [StudentController::class, 'show']);
        Route::put('/{student}', [StudentController::class, 'update'])->middleware('permission:students.edit');
        Route::delete('/{student}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');
    });

    // Guardians & Parent Portal
    Route::prefix('guardians')->group(function () {
        Route::get('/', [GuardianController::class, 'index'])->middleware('permission:students.view');
        Route::get('/my-children', [GuardianController::class, 'myChildren']);
    });

    // Invoices
    Route::get('/invoices', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\FeeInvoice::all(),
        ]);
    });

    Route::get('/invoices/{invoice}', function (\App\Models\FeeInvoice $invoice) {
        Gate::authorize('view', $invoice);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    });

    // Documents
    Route::get('/documents/{document}', function (\App\Models\FileUpload $document) {
        Gate::authorize('download', $document);

        return response()->json([
            'success' => true,
            'data' => $document,
        ]);
    });

    // ==========================================
    // Academic Management Module Routes
    // ==========================================

    // 1. Academic Sessions
    Route::prefix('academic-sessions')->group(function () {
        Route::get('/', [AcademicSessionController::class, 'index'])->middleware('permission:academics.view');
        Route::post('/', [AcademicSessionController::class, 'store'])->middleware('permission:academics.create');
        Route::get('/historical', [AcademicSessionController::class, 'historical'])->middleware('permission:academics.view');
        Route::get('/{academicSession}', [AcademicSessionController::class, 'show'])->middleware('permission:academics.view');
        Route::put('/{academicSession}', [AcademicSessionController::class, 'update'])->middleware('permission:academics.edit');
        Route::post('/{academicSession}/activate', [AcademicSessionController::class, 'activate'])->middleware('permission:academics.manage');
        Route::post('/{academicSession}/close', [AcademicSessionController::class, 'close'])->middleware('permission:academics.manage');
    });

    // 2. Education Boards
    Route::prefix('boards')->group(function () {
        Route::get('/', [BoardController::class, 'index'])->middleware('permission:academics.view');
        Route::post('/', [BoardController::class, 'store'])->middleware('permission:academics.manage');
    });

    // 3. Classes and Sections
    Route::prefix('classes')->group(function () {
        Route::get('/', [ClassController::class, 'index'])->middleware('permission:academics.view');
        Route::post('/', [ClassController::class, 'store'])->middleware('permission:academics.create');
        Route::post('/reorder', [ClassController::class, 'reorder'])->middleware('permission:academics.manage');
        Route::get('/{class}', [ClassController::class, 'show'])->middleware('permission:academics.view');
        Route::put('/{class}', [ClassController::class, 'update'])->middleware('permission:academics.edit');
        Route::post('/{class}/sections', [ClassController::class, 'storeSection'])->middleware('permission:academics.create');
        Route::put('/{class}/sections/{section}', [ClassController::class, 'updateSection'])->middleware('permission:academics.edit');
        Route::post('/{class}/sections/{section}/teacher', [ClassController::class, 'assignClassTeacher'])->middleware('permission:academics.manage');
        Route::post('/{class}/subjects', [SubjectController::class, 'assignToClass'])->middleware('permission:academics.manage');
    });

    // 4. Subjects
    Route::prefix('subjects')->group(function () {
        Route::get('/', [SubjectController::class, 'index'])->middleware('permission:academics.view');
        Route::post('/', [SubjectController::class, 'store'])->middleware('permission:academics.create');
        Route::put('/{subject}', [SubjectController::class, 'update'])->middleware('permission:academics.edit');
    });

    Route::post('/class-subjects/{classSubject}/teachers', [SubjectController::class, 'assignTeacher'])
        ->middleware('permission:academics.manage');

    Route::get('/subject-groups', [SubjectController::class, 'indexGroups'])->middleware('permission:academics.view');
    Route::post('/subject-groups', [SubjectController::class, 'storeGroup'])->middleware('permission:academics.manage');

    // 5. Academic Calendars and Events
    Route::prefix('academic-calendars')->group(function () {
        Route::get('/', [AcademicCalendarController::class, 'index'])->middleware('permission:academics.view');
        Route::post('/', [AcademicCalendarController::class, 'store'])->middleware('permission:academics.create');
        Route::get('/{academicCalendar}', [AcademicCalendarController::class, 'show'])->middleware('permission:academics.view');
        Route::post('/{academicCalendar}/events', [AcademicCalendarController::class, 'storeEvent'])->middleware('permission:academics.manage');
    });

    Route::put('/academic-events/{academicEvent}', [AcademicCalendarController::class, 'updateEvent'])
        ->middleware('permission:academics.manage');
    Route::delete('/academic-events/{academicEvent}', [AcademicCalendarController::class, 'destroyEvent'])
        ->middleware('permission:academics.manage');

    // 6. Timetables, Periods, Slots and Substitutions
    Route::prefix('timetables')->group(function () {
        Route::get('/', [TimetableController::class, 'index'])->middleware('permission:academics.view');
        Route::post('/', [TimetableController::class, 'store'])->middleware('permission:academics.create');
        Route::get('/{timetable}', [TimetableController::class, 'show'])->middleware('permission:academics.view');
        Route::post('/{timetable}/periods', [TimetableController::class, 'configurePeriods'])->middleware('permission:academics.manage');
        Route::post('/{timetable}/slots', [TimetableController::class, 'assignSlot'])->middleware('permission:academics.manage');
        Route::post('/{timetable}/check-conflicts', [TimetableController::class, 'checkConflicts'])->middleware('permission:academics.view');
    });

    Route::post('/timetable-entries/{entry}/substitute', [TimetableController::class, 'assignSubstitute'])
        ->middleware('permission:academics.manage');

    // ==========================================
    // Employee & Staff Management Routes
    // ==========================================
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:staff.view');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:staff.create');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->middleware('permission:staff.view');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->middleware('permission:staff.edit');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:staff.delete');
        Route::post('/{employee}/contracts', [EmployeeController::class, 'addContract'])->middleware('permission:staff.edit');
        Route::post('/{employee}/documents', [EmployeeController::class, 'addDocument'])->middleware('permission:staff.edit');
        Route::post('/{employee}/exit', [EmployeeController::class, 'recordExit'])->middleware('permission:staff.manage');
    });

    // ==========================================
    // Teacher Management Routes
    // ==========================================
    Route::prefix('teachers')->group(function () {
        Route::get('/', [TeacherController::class, 'index'])->middleware('permission:staff.view');
        Route::post('/substitutions', [TeacherController::class, 'assignSubstitute'])->middleware('permission:staff.manage');
        Route::get('/{teacher}', [TeacherController::class, 'show'])->middleware('permission:staff.view');
        Route::get('/{teacher}/workload', [TeacherController::class, 'workload'])->middleware('permission:staff.view');
        Route::get('/{teacher}/timetable', [TeacherController::class, 'timetable'])->middleware('permission:staff.view');
        Route::get('/{teacher}/attendance', [TeacherController::class, 'attendance'])->middleware('permission:staff.view');
    });

    // ==========================================
    // Departments & Designations Routes
    // ==========================================
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:staff.view');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:staff.manage');
    });

    Route::prefix('designations')->group(function () {
        Route::get('/', [DepartmentController::class, 'designations'])->middleware('permission:staff.view');
        Route::post('/', [DepartmentController::class, 'storeDesignation'])->middleware('permission:staff.manage');
    });

    // ==========================================
    // Leave Management Routes
    // ==========================================
    Route::prefix('leaves')->group(function () {
        Route::get('/types', [LeaveController::class, 'types'])->middleware('permission:staff.view');
        Route::post('/types', [LeaveController::class, 'storeType'])->middleware('permission:staff.manage');
        Route::get('/balances/{employee}', [LeaveController::class, 'balances'])->middleware('permission:staff.view');
        Route::get('/applications', [LeaveController::class, 'applications'])->middleware('permission:staff.view');
        Route::post('/applications', [LeaveController::class, 'apply'])->middleware('permission:staff.view');
        Route::put('/applications/{application}/review', [LeaveController::class, 'review'])->middleware('permission:staff.manage');
    });

    // ==========================================
    // Payroll & Compensation Routes
    // ==========================================
    Route::prefix('payroll')->group(function () {
        Route::get('/components', [PayrollController::class, 'components'])->middleware('permission:payroll.view');
        Route::post('/components', [PayrollController::class, 'storeComponent'])->middleware('permission:payroll.manage');
        Route::get('/structures', [PayrollController::class, 'structures'])->middleware('permission:payroll.view');
        Route::post('/structures', [PayrollController::class, 'storeStructure'])->middleware('permission:payroll.manage');
        Route::post('/employees/{employee}/profile', [PayrollController::class, 'assignProfile'])->middleware('permission:payroll.manage');
        Route::get('/periods', [PayrollController::class, 'periods'])->middleware('permission:payroll.view');
        Route::post('/periods', [PayrollController::class, 'storePeriod'])->middleware('permission:payroll.manage');
        Route::post('/periods/{period}/calculate', [PayrollController::class, 'calculate'])->middleware('permission:payroll.manage');
        Route::post('/periods/{period}/approve', [PayrollController::class, 'approve'])->middleware('permission:payroll.manage');
        Route::post('/periods/{period}/pay', [PayrollController::class, 'pay'])->middleware('permission:payroll.manage');
        Route::get('/payslips', [PayrollController::class, 'payslips'])->middleware('permission:payroll.view');
        Route::get('/payslips/{payslip}', [PayrollController::class, 'showPayslip'])->middleware('permission:payroll.view');
    });

    // ==========================================
    // Fee Management Routes
    // ==========================================
    Route::prefix('fees')->group(function () {
        // Categories & Structures
        Route::get('/categories', [FeeStructureController::class, 'listCategories'])->middleware('permission:fees.view');
        Route::post('/categories', [FeeStructureController::class, 'storeCategory'])->middleware('permission:fees.create');
        Route::get('/structures', [FeeStructureController::class, 'index'])->middleware('permission:fees.view');
        Route::post('/structures', [FeeStructureController::class, 'store'])->middleware('permission:fees.create');
        Route::get('/structures/{id}', [FeeStructureController::class, 'show'])->middleware('permission:fees.view');
        Route::put('/structures/{id}', [FeeStructureController::class, 'update'])->middleware('permission:fees.edit');

        // Concessions
        Route::get('/concessions', [FeeStructureController::class, 'listConcessions'])->middleware('permission:fees.view');
        Route::post('/concessions', [FeeStructureController::class, 'storeConcession'])->middleware('permission:fees.create');

        // Assignments & Invoices
        Route::post('/assignments', [StudentFeeController::class, 'assignFee'])->middleware('permission:fees.create');
        Route::get('/invoices', [StudentFeeController::class, 'listInvoices'])->middleware('permission:fees.view');
        Route::get('/invoices/{id}', [StudentFeeController::class, 'showInvoice'])->middleware('permission:fees.view');
        Route::post('/invoices/{id}/fine', [StudentFeeController::class, 'applyFine'])->middleware('permission:fees.edit');
        Route::post('/fines/{id}/waive', [StudentFeeController::class, 'waiveFine'])->middleware('permission:fees.edit');
        Route::get('/ledger/{studentId}', [StudentFeeController::class, 'studentLedger'])->middleware('permission:fees.view');

        // Payments & Razorpay
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:fees.view');
        Route::get('/payments/{id}', [PaymentController::class, 'show'])->middleware('permission:fees.view');
        Route::post('/payments/collect', [PaymentController::class, 'collect'])->middleware('permission:fees.collect');
        Route::post('/payments/razorpay/order', [PaymentController::class, 'initiateRazorpay'])->middleware('permission:fees.collect');
        Route::post('/payments/razorpay/verify', [PaymentController::class, 'verifyRazorpay'])->middleware('permission:fees.collect');
        Route::post('/payments/{id}/refund', [PaymentController::class, 'refund'])->middleware('permission:fees.refund');
    });

    // ==========================================
    // Financial & Expense Management Routes
    // ==========================================
    Route::prefix('finance')->group(function () {
        // Accounts & Expenses
        Route::get('/accounts', [ExpenseController::class, 'listAccounts'])->middleware('permission:fees.view');
        Route::post('/accounts', [ExpenseController::class, 'storeAccount'])->middleware('permission:fees.create');
        Route::get('/expense-categories', [ExpenseController::class, 'listCategories'])->middleware('permission:fees.view');
        Route::post('/expense-categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:fees.create');
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:fees.view');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:fees.create');

        // Financial Reports
        Route::prefix('reports')->group(function () {
            Route::get('/daily', [FinancialReportController::class, 'daily'])->middleware('permission:fees.view');
            Route::get('/monthly', [FinancialReportController::class, 'monthly'])->middleware('permission:fees.view');
            Route::get('/class-wise', [FinancialReportController::class, 'classWise'])->middleware('permission:fees.view');
            Route::get('/outstanding', [FinancialReportController::class, 'outstanding'])->middleware('permission:fees.view');
            Route::get('/defaulters', [FinancialReportController::class, 'defaulters'])->middleware('permission:fees.view');
            Route::get('/payment-methods', [FinancialReportController::class, 'paymentMethods'])->middleware('permission:fees.view');
            Route::get('/refunds', [FinancialReportController::class, 'refunds'])->middleware('permission:fees.view');
            Route::get('/summary', [FinancialReportController::class, 'summary'])->middleware('permission:fees.view');
        });
    });

    // ==========================================
    // Examination & Result Management Routes
    // ==========================================
    Route::prefix('examinations')->group(function () {
        // Types & Grading Schemes
        Route::get('/types', [ExaminationController::class, 'listTypes'])->middleware('permission:examinations.view');
        Route::post('/types', [ExaminationController::class, 'storeType'])->middleware('permission:examinations.create');
        Route::get('/grading-schemes', [GradingSchemeController::class, 'index'])->middleware('permission:examinations.view');
        Route::post('/grading-schemes', [GradingSchemeController::class, 'store'])->middleware('permission:examinations.create');
        Route::get('/grading-schemes/{id}', [GradingSchemeController::class, 'show'])->middleware('permission:examinations.view');

        // Master Examinations
        Route::get('/', [ExaminationController::class, 'index'])->middleware('permission:examinations.view');
        Route::post('/', [ExaminationController::class, 'store'])->middleware('permission:examinations.create');
        Route::get('/{id}', [ExaminationController::class, 'show'])->middleware('permission:examinations.view');
        Route::put('/{id}', [ExaminationController::class, 'update'])->middleware('permission:examinations.edit');

        // Schedules
        Route::post('/schedules', [ExaminationController::class, 'schedule'])->middleware('permission:examinations.create');
        Route::get('/{id}/schedules', [ExaminationController::class, 'listSchedules'])->middleware('permission:examinations.view');
        Route::get('/schedules/{scheduleId}', [ExaminationController::class, 'getSchedule'])->middleware('permission:examinations.view');

        // Marks Entry & CSV
        Route::get('/schedules/{scheduleId}/marks', [MarksEntryController::class, 'getScheduleMarks'])->middleware('permission:examinations.view');
        Route::post('/schedules/{scheduleId}/marks', [MarksEntryController::class, 'enterMarks'])->middleware('permission:examinations.create');
        Route::get('/schedules/{scheduleId}/template', [MarksEntryController::class, 'downloadCsvTemplate'])->middleware('permission:examinations.view');
        Route::post('/schedules/{scheduleId}/import', [MarksEntryController::class, 'importCsv'])->middleware('permission:examinations.create');
        Route::get('/corrections', [MarksEntryController::class, 'listCorrections'])->middleware('permission:examinations.view');
        Route::post('/marks/{markId}/correction', [MarksEntryController::class, 'requestCorrection'])->middleware('permission:examinations.create');
        Route::post('/corrections/{correctionId}/review', [MarksEntryController::class, 'reviewCorrection'])->middleware('permission:examinations.publish');

        // Results, Class Ranking & Publication
        Route::post('/{id}/classes/{classId}/calculate', [ResultController::class, 'calculateClassResults'])->middleware('permission:examinations.create');
        Route::post('/{id}/publish', [ResultController::class, 'publishResults'])->middleware('permission:examinations.publish');
        Route::get('/{id}/classes/{classId}/performance', [ResultController::class, 'classPerformance'])->middleware('permission:examinations.view');
        Route::get('/{id}/classes/{classId}/toppers', [ResultController::class, 'toppers'])->middleware('permission:examinations.view');
        Route::get('/{id}/classes/{classId}/subject-wise', [ResultController::class, 'subjectWise'])->middleware('permission:examinations.view');
        Route::get('/students/{studentId}/history', [ResultController::class, 'studentHistory'])->middleware('permission:examinations.view');

        // Report Cards
        Route::get('/report-cards/templates', [ReportCardController::class, 'listTemplates'])->middleware('permission:examinations.view');
        Route::post('/report-cards/templates', [ReportCardController::class, 'storeTemplate'])->middleware('permission:examinations.create');
        Route::get('/{id}/students/{studentId}/report-card', [ReportCardController::class, 'generateStudentReportCard'])->middleware('permission:examinations.view');
    });

    // ==========================================
    // LMS (Homework, Assignments, Resources & Quizzes)
    // ==========================================
    Route::prefix('lms')->group(function () {
        // Homework
        Route::get('/homework', [HomeworkController::class, 'index'])->middleware('permission:homework.view');
        Route::post('/homework', [HomeworkController::class, 'store'])->middleware('permission:homework.create');
        Route::get('/homework/{id}', [HomeworkController::class, 'show'])->middleware('permission:homework.view');
        Route::put('/homework/{id}', [HomeworkController::class, 'update'])->middleware('permission:homework.edit');
        Route::delete('/homework/{id}', [HomeworkController::class, 'destroy'])->middleware('permission:homework.delete');

        // Submissions & Grading
        Route::post('/homework/{id}/submit', [AssignmentSubmissionController::class, 'submit'])->middleware('permission:homework.submit');
        Route::get('/homework/{id}/submissions', [AssignmentSubmissionController::class, 'index'])->middleware('permission:homework.view');
        Route::get('/submissions/{id}', [AssignmentSubmissionController::class, 'show'])->middleware('permission:homework.view');
        Route::post('/submissions/{id}/grade', [AssignmentSubmissionController::class, 'grade'])->middleware('permission:homework.grade');
        Route::post('/submissions/{id}/resubmit-request', [AssignmentSubmissionController::class, 'requestResubmission'])->middleware('permission:homework.grade');

        // Learning Resources
        Route::get('/resources', [LearningResourceController::class, 'index'])->middleware('permission:lms.view');
        Route::post('/resources', [LearningResourceController::class, 'store'])->middleware('permission:lms.create');
        Route::put('/resources/{id}', [LearningResourceController::class, 'update'])->middleware('permission:lms.edit');
        Route::delete('/resources/{id}', [LearningResourceController::class, 'destroy'])->middleware('permission:lms.delete');

        // Online Quizzes
        Route::get('/quizzes', [QuizController::class, 'index'])->middleware('permission:lms.view');
        Route::post('/quizzes', [QuizController::class, 'store'])->middleware('permission:lms.create');
        Route::post('/quizzes/{id}/publish', [QuizController::class, 'publish'])->middleware('permission:lms.manage');
        Route::post('/quizzes/{id}/attempts', [QuizController::class, 'startAttempt'])->middleware('permission:lms.view');
        Route::post('/quiz-attempts/{id}/submit', [QuizController::class, 'submitAttempt'])->middleware('permission:lms.view');
        Route::get('/quizzes/{id}/report', [QuizController::class, 'report'])->middleware('permission:lms.view');
    });

    // ==========================================
    // Unified Portals (Student, Parent, Teacher)
    // ==========================================
    Route::prefix('portal')->group(function () {
        // Student Portal
        Route::prefix('student')->group(function () {
            Route::get('/dashboard', [StudentPortalController::class, 'dashboard']);
            Route::get('/timetable', [StudentPortalController::class, 'timetable']);
            Route::get('/homework', [StudentPortalController::class, 'homework']);
            Route::get('/resources', [StudentPortalController::class, 'learningResources']);
            Route::get('/report-cards', [StudentPortalController::class, 'reportCards']);
        });

        // Parent Portal
        Route::prefix('parent')->group(function () {
            Route::get('/children', [ParentPortalController::class, 'children']);
            Route::get('/children/{studentId}/dashboard', [ParentPortalController::class, 'childDashboard']);
            Route::get('/children/{studentId}/fees', [ParentPortalController::class, 'childFees']);
            Route::get('/children/{studentId}/timetable', [ParentPortalController::class, 'childTimetable']);
            Route::post('/children/{studentId}/leave', [ParentPortalController::class, 'submitChildLeave']);
            Route::post('/children/{studentId}/communicate', [ParentPortalController::class, 'communicate']);
        });

        // Teacher Portal
        Route::prefix('teacher')->group(function () {
            Route::get('/dashboard', [TeacherPortalController::class, 'dashboard']);
            Route::get('/classes', [TeacherPortalController::class, 'classes']);
            Route::get('/timetable', [TeacherPortalController::class, 'timetable']);
            Route::get('/performance/{classId}/{subjectId}', [TeacherPortalController::class, 'studentPerformance']);
        });
    });
});
