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
use App\Http\Controllers\Api\Student\AdmissionController;
use App\Http\Controllers\Api\Student\GuardianController;
use App\Http\Controllers\Api\Student\StudentController;
use App\Http\Controllers\Api\Student\StudentImportExportController;
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
});
