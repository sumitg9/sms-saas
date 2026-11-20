<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\BookReservation;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Guardian;
use App\Models\LibraryBook;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibraryManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;
    protected User $studentUser;
    protected Student $student;
    protected User $teacherUser;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tenant & Hierarchy
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oxford High School',
            'slug' => 'oxford-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@oxford.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oxford High Main',
            'code' => 'OXF-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Main Library Wing',
            'branch_code' => 'LW-' . rand(100, 999),
            'is_main_branch' => true,
        ]);

        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($this->tenant);
        $tenantContext->setSchool($this->school);
        $tenantContext->setBranch($this->branch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2026-2027',
            'code' => 'AY-LIB-' . rand(10, 99),
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Class 9',
            'code' => 'CLS-9-' . rand(100, 999),
            'order_index' => 9,
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
            'code' => '9-A-' . rand(100, 999),
            'capacity' => 40,
        ]);

        // 2. Roles & Admin User
        Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);
        Role::firstOrCreate(['name' => 'librarian'], ['guard_name' => 'web', 'display_name' => 'Librarian']);

        $this->adminUser = User::factory()->create([
            'first_name' => 'Librarian',
            'last_name' => 'Head',
            'email' => 'admin.lib.' . Str::random(8) . '@oxford.edu',
            'phone' => '+9199' . rand(10000000, 99999999),
        ]);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // 3. Student User & Profile
        $this->studentUser = User::factory()->create([
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'student.lib.' . Str::random(8) . '@oxford.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
        ]);
        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->studentUser->id,
            'admission_number' => 'ADM-LIB-' . rand(1000, 9999),
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'date_of_birth' => '2011-05-15',
            'gender' => 'male',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '15',
            'status' => 'enrolled',
        ]);

        // 4. Employee & Teacher
        $dept = EmployeeDepartment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Science',
            'code' => 'SCI-' . rand(100, 999),
        ]);

        $desig = EmployeeDesignation::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Science Teacher',
            'code' => 'TCH-' . rand(100, 999),
        ]);

        $this->teacherUser = User::factory()->create([
            'first_name' => 'Vikram',
            'last_name' => 'Mehta',
            'email' => 'teacher.lib.' . Str::random(8) . '@oxford.edu',
            'phone' => '+9197' . rand(10000000, 99999999),
        ]);
        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->teacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-LIB-' . rand(1000, 9999),
            'first_name' => 'Vikram',
            'last_name' => 'Mehta',
            'gender' => 'male',
            'date_of_birth' => '1985-06-15',
            'date_of_joining' => '2023-06-01',
            'email' => 'vikram.' . Str::random(8) . '@oxford.edu',
            'phone' => '+9196' . rand(10000000, 99999999),
            'status' => 'active',
        ]);
    }

    public function test_can_catalog_book_with_auto_generated_physical_copies(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Concepts of Physics Vol 1',
            'isbn' => '978-8177091878',
            'author' => 'H.C. Verma',
            'publisher' => 'Bharati Bhawan',
            'edition' => '2024 Revised',
            'category' => 'Physics',
            'shelf_no' => 'Shelf-B-4',
            'initial_copies' => 3,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Concepts of Physics Vol 1')
            ->assertJsonPath('data.total_copies', 3)
            ->assertJsonPath('data.available_copies', 3);

        $this->assertDatabaseHas('library_books', [
            'title' => 'Concepts of Physics Vol 1',
            'total_copies' => 3,
            'available_copies' => 3,
        ]);

        $bookId = $response->json('data.id');
        $copies = BookCopy::where('book_id', $bookId)->get();
        $this->assertCount(3, $copies);
        $this->assertStringStartsWith('BC-', $copies->first()->barcode);
    }

    public function test_can_add_more_copies_to_existing_book_and_sync_counts(): void
    {
        Sanctum::actingAs($this->adminUser);

        $createRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Introduction to Algorithms',
            'author' => 'CLRS',
            'category' => 'Computer Science',
            'initial_copies' => 2,
        ]);

        $bookId = $createRes->json('data.id');

        $addCopiesRes = $this->postJson("/api/v1/operations/library/books/{$bookId}/copies", [
            'count' => 3,
        ]);

        $addCopiesRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('library_books', [
            'id' => $bookId,
            'total_copies' => 5,
            'available_copies' => 5,
        ]);
    }

    public function test_can_issue_book_to_student_successfully(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Wings of Fire',
            'author' => 'A.P.J. Abdul Kalam',
            'category' => 'Autobiography',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        $issueRes = $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
            'issue_date' => Carbon::today()->toDateString(),
        ]);

        $issueRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'issued');

        $this->assertEquals('issued', $copy->fresh()->status);
        $this->assertEquals(0, $copy->book->fresh()->available_copies);
    }

    public function test_cannot_issue_already_issued_copy(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Discovery of India',
            'author' => 'Jawaharlal Nehru',
            'category' => 'History',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        // Issue first time
        $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
        ])->assertStatus(201);

        // Try issuing same copy again to employee
        $issueAgainRes = $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'employee',
            'member_id' => $this->employee->id,
        ]);

        $issueAgainRes->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_student_borrowing_limit_enforced(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Harry Potter Series',
            'author' => 'J.K. Rowling',
            'category' => 'Fiction',
            'initial_copies' => 5,
        ]);

        $copies = BookCopy::where('book_id', $bookRes->json('data.id'))->get();

        // Issue 3 books (max allowed for student)
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/operations/library/issue', [
                'copy_id' => $copies[$i]->id,
                'member_type' => 'student',
                'member_id' => $this->student->id,
            ])->assertStatus(201);
        }

        // Try 4th book - should fail with limit error
        $fourthRes = $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copies[3]->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
        ]);

        $fourthRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('maximum borrowing limit', $fourthRes->json('message'));
    }

    public function test_can_return_book_on_time_with_zero_fine(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Malgudi Days',
            'author' => 'R.K. Narayan',
            'category' => 'Fiction',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        $issueRes = $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
            'issue_date' => Carbon::today()->subDays(5)->toDateString(),
            'due_date' => Carbon::today()->addDays(9)->toDateString(),
        ]);

        $issueId = $issueRes->json('data.id');

        // Return on time
        $returnRes = $this->postJson("/api/v1/operations/library/return/{$issueId}", [
            'return_date' => Carbon::today()->toDateString(),
        ]);

        $returnRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.fine_amount', '0.00');

        $this->assertEquals('available', $copy->fresh()->status);
        $this->assertEquals(1, $copy->book->fresh()->available_copies);
    }

    public function test_calculates_overdue_fine_when_returning_late(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Advanced Organic Chemistry',
            'author' => 'Solomons',
            'category' => 'Chemistry',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        // 10 days overdue: due 10 days ago
        $issueRes = $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
            'issue_date' => Carbon::today()->subDays(24)->toDateString(),
            'due_date' => Carbon::today()->subDays(10)->toDateString(),
        ]);

        $issueId = $issueRes->json('data.id');

        // Return today with 5.00/day fine -> 10 days late = 50.00
        $returnRes = $this->postJson("/api/v1/operations/library/return/{$issueId}", [
            'return_date' => Carbon::today()->toDateString(),
            'fine_rate_per_day' => 5.00,
        ]);

        $returnRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.fine_amount', '50.00');

        $this->assertDatabaseHas('book_issues', [
            'id' => $issueId,
            'fine_amount' => 50.00,
        ]);
    }

    public function test_can_report_copy_as_lost_or_damaged(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Encyclopedia Britannica',
            'author' => 'Various',
            'category' => 'Reference',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        $damagedRes = $this->postJson("/api/v1/operations/library/copies/{$copy->id}/lost-damaged", [
            'status' => 'damaged',
            'fine_amount' => 250.00,
        ]);

        $damagedRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'damaged');

        $this->assertEquals('damaged', $copy->fresh()->status);
        $this->assertEquals(0, $copy->book->fresh()->available_copies);
    }

    public function test_barcode_qr_scan_lookup_returns_full_status_and_borrower(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'To Kill a Mockingbird',
            'author' => 'Harper Lee',
            'category' => 'Literature',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        // Issue it
        $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
        ]);

        // Scan barcode
        $scanRes = $this->getJson("/api/v1/operations/library/barcode/{$copy->barcode}");

        $scanRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.barcode', $copy->barcode)
            ->assertJsonPath('data.book.title', 'To Kill a Mockingbird')
            ->assertJsonPath('data.current_borrower.member_type', 'student')
            ->assertJsonPath('data.current_borrower.member_name', 'Aarav Sharma');
    }

    public function test_member_library_account_details_and_borrowing_eligibility(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Gitanjali',
            'author' => 'Rabindranath Tagore',
            'category' => 'Poetry',
            'initial_copies' => 1,
        ]);

        $copy = BookCopy::where('book_id', $bookRes->json('data.id'))->first();

        $this->postJson('/api/v1/operations/library/issue', [
            'copy_id' => $copy->id,
            'member_type' => 'student',
            'member_id' => $this->student->id,
        ]);

        $accountRes = $this->getJson("/api/v1/operations/library/member/student/{$this->student->id}");

        $accountRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.member.name', 'Aarav Sharma')
            ->assertJsonPath('data.member.currently_borrowed', 1)
            ->assertJsonPath('data.member.can_borrow', true);
    }

    public function test_can_reserve_book_when_copies_are_checked_out(): void
    {
        Sanctum::actingAs($this->adminUser);

        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'category' => 'Software Engineering',
            'initial_copies' => 1,
        ]);

        $bookId = $bookRes->json('data.id');

        $reserveRes = $this->postJson('/api/v1/operations/library/reserve', [
            'book_id' => $bookId,
            'member_type' => 'employee',
            'member_id' => $this->employee->id,
        ]);

        $reserveRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'reserved');

        $this->assertDatabaseHas('book_reservations', [
            'book_id' => $bookId,
            'member_id' => $this->employee->id,
            'status' => 'reserved',
        ]);
    }

    public function test_tenant_isolation_prevents_cross_tenant_book_access(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create book in Oxford Academy
        $bookRes = $this->postJson('/api/v1/operations/library/books', [
            'branch_id' => $this->branch->id,
            'title' => 'Secret Academy Book',
            'author' => 'Secret Author',
            'category' => 'Secret',
            'initial_copies' => 1,
        ]);
        $bookId = $bookRes->json('data.id');

        // Create second tenant
        $tenant2 = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cambridge High School',
            'slug' => 'cambridge-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@cambridge.edu',
            'contact_phone' => '+919876549999',
            'status' => 'active',
        ]);

        $user2 = User::factory()->create(['first_name' => 'Other', 'last_name' => 'Librarian']);
        $user2->assignRole('school_admin', $tenant2->id);
        TenantMembership::create([
            'tenant_id' => $tenant2->id,
            'user_id' => $user2->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // Switch to tenant 2
        app(TenantContext::class)->setTenant($tenant2);
        Sanctum::actingAs($user2);

        // Attempt to view or list book from tenant 1
        $listRes = $this->getJson('/api/v1/operations/library/books');
        $titles = collect($listRes->json('data'))->pluck('title');
        $this->assertFalse($titles->contains('Secret Academy Book'));

        $showRes = $this->getJson("/api/v1/operations/library/books/{$bookId}");
        $showRes->assertStatus(404);
    }
}
