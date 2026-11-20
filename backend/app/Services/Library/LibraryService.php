<?php

namespace App\Services\Library;

use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\BookReservation;
use App\Models\Employee;
use App\Models\LibraryBook;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LibraryService
{
    public const STUDENT_MAX_BOOKS = 3;
    public const EMPLOYEE_MAX_BOOKS = 5;
    public const STUDENT_LOAN_DAYS = 14;
    public const EMPLOYEE_LOAN_DAYS = 30;
    public const DEFAULT_FINE_PER_DAY = 5.00;

    /**
     * Create a new library book with physical copies.
     */
    public function createBook(array $data, int $initialCopies = 1): LibraryBook
    {
        return DB::transaction(function () use ($data, $initialCopies) {
            $book = LibraryBook::create([
                'branch_id' => $data['branch_id'],
                'title' => $data['title'],
                'isbn' => $data['isbn'] ?? null,
                'author' => $data['author'],
                'publisher' => $data['publisher'] ?? null,
                'edition' => $data['edition'] ?? null,
                'category' => $data['category'],
                'total_copies' => 0,
                'available_copies' => 0,
                'shelf_no' => $data['shelf_no'] ?? null,
            ]);

            $this->addCopiesToBook($book, max(1, $initialCopies));

            return $book->fresh(['copies']);
        });
    }

    /**
     * Add physical copies to a book.
     */
    public function addCopiesToBook(LibraryBook $book, int $count = 1): array
    {
        $existingCount = $book->copies()->count();
        $createdCopies = [];

        for ($i = 1; $i <= $count; $i++) {
            $copyNumber = $existingCount + $i;
            $barcode = 'BC-' . strtoupper(substr($book->branch_id, 0, 4)) . '-' . strtoupper(substr($book->id, 0, 6)) . '-' . str_pad($copyNumber, 3, '0', STR_PAD_LEFT);

            $copy = BookCopy::create([
                'book_id' => $book->id,
                'barcode' => $barcode,
                'copy_number' => $copyNumber,
                'status' => 'available',
            ]);

            $createdCopies[] = $copy;
        }

        $book->syncCounts();

        return $createdCopies;
    }

    /**
     * Issue a book copy to a student or employee.
     */
    public function issueBook(array $data, ?string $issuedByUserId = null): BookIssue
    {
        return DB::transaction(function () use ($data, $issuedByUserId) {
            $copy = BookCopy::with('book')->findOrFail($data['copy_id']);

            if ($copy->status !== 'available') {
                throw new InvalidArgumentException("Book copy '{$copy->barcode}' is not available for issue (current status: {$copy->status}).");
            }

            $memberType = $data['member_type']; // 'student' or 'employee'
            $memberId = $data['member_id'];

            // Validate member exists
            if ($memberType === 'student') {
                $member = Student::withoutGlobalScopes()->findOrFail($memberId);
                $maxAllowed = self::STUDENT_MAX_BOOKS;
                $loanDays = self::STUDENT_LOAN_DAYS;
            } elseif ($memberType === 'employee') {
                $member = Employee::withoutGlobalScopes()->findOrFail($memberId);
                $maxAllowed = self::EMPLOYEE_MAX_BOOKS;
                $loanDays = self::EMPLOYEE_LOAN_DAYS;
            } else {
                throw new InvalidArgumentException("Invalid member type: {$memberType}");
            }

            // Check borrowing limit
            $activeIssuesCount = BookIssue::where('member_type', $memberType)
                ->where('member_id', $memberId)
                ->whereIn('status', ['issued', 'overdue'])
                ->count();

            if ($activeIssuesCount >= $maxAllowed) {
                throw new InvalidArgumentException("Member has reached the maximum borrowing limit of {$maxAllowed} books.");
            }

            $issueDate = isset($data['issue_date']) ? Carbon::parse($data['issue_date']) : Carbon::today();
            $dueDate = isset($data['due_date']) ? Carbon::parse($data['due_date']) : $issueDate->copy()->addDays($loanDays);

            $issue = BookIssue::create([
                'copy_id' => $copy->id,
                'member_type' => $memberType,
                'member_id' => $memberId,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'fine_amount' => 0.00,
                'fine_paid' => false,
                'issued_by' => $issuedByUserId,
                'status' => 'issued',
            ]);

            $copy->update(['status' => 'issued']);
            $copy->book->syncCounts();

            return $issue->fresh(['copy.book']);
        });
    }

    /**
     * Return an issued book.
     */
    public function returnBook(string $issueId, array $data = []): BookIssue
    {
        return DB::transaction(function () use ($issueId, $data) {
            $issue = BookIssue::with(['copy.book'])->findOrFail($issueId);

            if ($issue->status === 'returned') {
                throw new InvalidArgumentException("This book has already been returned.");
            }

            $returnDate = isset($data['return_date']) ? Carbon::parse($data['return_date']) : Carbon::today();
            $fineRate = isset($data['fine_rate_per_day']) ? (float)$data['fine_rate_per_day'] : self::DEFAULT_FINE_PER_DAY;

            // Calculate overdue fine if applicable
            $calculatedFine = 0.00;
            if ($returnDate->greaterThan(Carbon::parse($issue->due_date))) {
                $daysOverdue = abs((int) $returnDate->diffInDays(Carbon::parse($issue->due_date)));
                $calculatedFine = round($daysOverdue * $fineRate, 2);
            }

            $fineAmount = isset($data['fine_amount']) ? (float)$data['fine_amount'] : $calculatedFine;
            $finePaid = $data['fine_paid'] ?? ($fineAmount <= 0.00);

            $copyStatus = $data['copy_status'] ?? 'available'; // 'available', 'damaged', 'lost'

            $issue->update([
                'return_date' => $returnDate->toDateString(),
                'fine_amount' => $fineAmount,
                'fine_paid' => $finePaid,
                'status' => 'returned',
            ]);

            $issue->copy->update(['status' => $copyStatus]);
            $issue->copy->book->syncCounts();

            // Fulfill reservation if someone reserved this book and copy is available
            if ($copyStatus === 'available') {
                $reservation = BookReservation::where('book_id', $issue->copy->book_id)
                    ->where('status', 'reserved')
                    ->orderBy('reservation_date', 'asc')
                    ->first();

                if ($reservation) {
                    $reservation->update(['status' => 'fulfilled']);
                }
            }

            return $issue->fresh(['copy.book']);
        });
    }

    /**
     * Report a book copy as lost or damaged.
     */
    public function reportLostOrDamaged(string $copyId, string $status, float $fineAmount = 0.00): BookCopy
    {
        if (!in_array($status, ['lost', 'damaged'])) {
            throw new InvalidArgumentException("Status must be 'lost' or 'damaged'.");
        }

        return DB::transaction(function () use ($copyId, $status, $fineAmount) {
            $copy = BookCopy::with('book')->findOrFail($copyId);
            $copy->update(['status' => $status]);

            // If there's an active issue, close it with the damage/loss fee
            $activeIssue = $copy->activeIssue;
            if ($activeIssue) {
                $activeIssue->update([
                    'return_date' => Carbon::today()->toDateString(),
                    'fine_amount' => $fineAmount,
                    'status' => 'returned',
                ]);
            }

            $copy->book->syncCounts();

            return $copy->fresh(['book']);
        });
    }

    /**
     * Barcode/QR lookup for scanning at library desk.
     */
    public function lookupBarcode(string $barcode): array
    {
        $copy = BookCopy::with(['book', 'activeIssue.issuer'])->where('barcode', $barcode)->first();

        if (!$copy) {
            throw new InvalidArgumentException("No book copy found with barcode '{$barcode}'.");
        }

        $activeIssue = $copy->activeIssue;
        $borrower = null;

        if ($activeIssue) {
            $borrower = [
                'issue_id' => $activeIssue->id,
                'member_type' => $activeIssue->member_type,
                'member_id' => $activeIssue->member_id,
                'member_name' => $activeIssue->member ? ($activeIssue->member->first_name . ' ' . $activeIssue->member->last_name) : 'Unknown',
                'issue_date' => $activeIssue->issue_date->toDateString(),
                'due_date' => $activeIssue->due_date->toDateString(),
                'is_overdue' => $activeIssue->isOverdue(),
                'calculated_fine' => $activeIssue->calculateOverdueFine(),
            ];
        }

        return [
            'copy_id' => $copy->id,
            'barcode' => $copy->barcode,
            'copy_number' => $copy->copy_number,
            'status' => $copy->status,
            'book' => [
                'id' => $copy->book->id,
                'title' => $copy->book->title,
                'author' => $copy->book->author,
                'isbn' => $copy->book->isbn,
                'category' => $copy->book->category,
                'shelf_no' => $copy->book->shelf_no,
                'available_copies' => $copy->book->available_copies,
            ],
            'current_borrower' => $borrower,
        ];
    }

    /**
     * Get member library account details.
     */
    public function getMemberAccount(string $memberType, string $memberId): array
    {
        $member = null;
        if ($memberType === 'student') {
            $member = Student::withoutGlobalScopes()->findOrFail($memberId);
            $maxAllowed = self::STUDENT_MAX_BOOKS;
        } else {
            $member = Employee::withoutGlobalScopes()->findOrFail($memberId);
            $maxAllowed = self::EMPLOYEE_MAX_BOOKS;
        }

        $activeIssues = BookIssue::with(['copy.book'])
            ->where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->whereIn('status', ['issued', 'overdue'])
            ->get();

        $historyIssues = BookIssue::with(['copy.book'])
            ->where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->where('status', 'returned')
            ->orderBy('return_date', 'desc')
            ->take(20)
            ->get();

        $unpaidFines = BookIssue::where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->where('fine_paid', false)
            ->sum('fine_amount');

        $activeReservations = BookReservation::with('book')
            ->where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->where('status', 'reserved')
            ->get();

        return [
            'member' => [
                'id' => $member->id,
                'type' => $memberType,
                'name' => $member->first_name . ' ' . $member->last_name,
                'max_allowed_books' => $maxAllowed,
                'currently_borrowed' => $activeIssues->count(),
                'can_borrow' => ($activeIssues->count() < $maxAllowed) && ($unpaidFines <= 0),
            ],
            'active_issues' => $activeIssues->map(function ($issue) {
                return [
                    'id' => $issue->id,
                    'book_title' => $issue->copy->book->title,
                    'author' => $issue->copy->book->author,
                    'barcode' => $issue->copy->barcode,
                    'issue_date' => $issue->issue_date->toDateString(),
                    'due_date' => $issue->due_date->toDateString(),
                    'is_overdue' => $issue->isOverdue(),
                    'estimated_fine' => $issue->calculateOverdueFine(),
                ];
            }),
            'unpaid_fines_total' => (float)$unpaidFines,
            'reservations' => $activeReservations,
            'history' => $historyIssues,
        ];
    }

    /**
     * Reserve a book.
     */
    public function reserveBook(string $bookId, string $memberType, string $memberId): BookReservation
    {
        $book = LibraryBook::findOrFail($bookId);

        // Check if member already has an active reservation for this book
        $existing = BookReservation::where('book_id', $book->id)
            ->where('member_type', $memberType)
            ->where('member_id', $memberId)
            ->where('status', 'reserved')
            ->first();

        if ($existing) {
            throw new InvalidArgumentException("Member already has a pending reservation for this book.");
        }

        return BookReservation::create([
            'book_id' => $book->id,
            'member_type' => $memberType,
            'member_id' => $memberId,
            'reservation_date' => Carbon::today()->toDateString(),
            'status' => 'reserved',
        ]);
    }

    /**
     * Get library reports and summary statistics.
     */
    public function getLibraryReports(string $branchId): array
    {
        $totalTitles = LibraryBook::where('branch_id', $branchId)->count();
        $totalCopies = BookCopy::whereHas('book', fn($q) => $q->where('branch_id', $branchId))->count();
        $availableCopies = BookCopy::whereHas('book', fn($q) => $q->where('branch_id', $branchId))->where('status', 'available')->count();
        $issuedCopies = BookCopy::whereHas('book', fn($q) => $q->where('branch_id', $branchId))->where('status', 'issued')->count();
        $lostCopies = BookCopy::whereHas('book', fn($q) => $q->where('branch_id', $branchId))->where('status', 'lost')->count();
        $damagedCopies = BookCopy::whereHas('book', fn($q) => $q->where('branch_id', $branchId))->where('status', 'damaged')->count();

        // Overdue count
        $overdueCount = BookIssue::whereHas('copy.book', fn($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['issued', 'overdue'])
            ->where('due_date', '<', Carbon::today()->toDateString())
            ->count();

        // Total collected fines
        $collectedFines = BookIssue::whereHas('copy.book', fn($q) => $q->where('branch_id', $branchId))
            ->where('fine_paid', true)
            ->sum('fine_amount');

        // Pending fines
        $pendingFines = BookIssue::whereHas('copy.book', fn($q) => $q->where('branch_id', $branchId))
            ->where('fine_paid', false)
            ->sum('fine_amount');

        // Categories breakdown
        $categoryBreakdown = LibraryBook::where('branch_id', $branchId)
            ->select('category', DB::raw('count(*) as count'), DB::raw('sum(total_copies) as copies'))
            ->groupBy('category')
            ->get();

        return [
            'total_titles' => $totalTitles,
            'total_copies' => $totalCopies,
            'available_copies' => $availableCopies,
            'issued_copies' => $issuedCopies,
            'lost_copies' => $lostCopies,
            'damaged_copies' => $damagedCopies,
            'overdue_count' => $overdueCount,
            'collected_fines' => (float)$collectedFines,
            'pending_fines' => (float)$pendingFines,
            'category_breakdown' => $categoryBreakdown,
        ];
    }
}
