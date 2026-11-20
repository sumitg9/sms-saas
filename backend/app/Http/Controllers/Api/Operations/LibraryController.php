<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Services\Library\LibraryService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LibraryController extends Controller
{
    public function __construct(
        protected LibraryService $libraryService
    ) {}

    /**
     * List library books with search and filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LibraryBook::with('copies');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', $search)
                  ->orWhere('author', 'ilike', $search)
                  ->orWhere('isbn', 'ilike', $search)
                  ->orWhere('publisher', 'ilike', $search);
            });
        }

        $books = $query->orderBy('title')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $books->items(),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    /**
     * Create a new library book catalog entry with physical copies.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'title' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:50',
            'author' => 'required|string|max:200',
            'publisher' => 'nullable|string|max:200',
            'edition' => 'nullable|string|max:50',
            'category' => 'required|string|max:100',
            'shelf_no' => 'nullable|string|max:50',
            'initial_copies' => 'nullable|integer|min:1|max:500',
        ]);

        $initialCopies = $request->integer('initial_copies', 1);
        $book = $this->libraryService->createBook($validated, $initialCopies);

        return response()->json([
            'success' => true,
            'message' => 'Book cataloged successfully with copies.',
            'data' => $book,
        ], 201);
    }

    /**
     * View book details.
     */
    public function show(string $id): JsonResponse
    {
        $book = LibraryBook::with(['copies.activeIssue', 'reservations'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $book,
        ]);
    }

    /**
     * Update book details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $book = LibraryBook::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'isbn' => 'nullable|string|max:50',
            'author' => 'sometimes|required|string|max:200',
            'publisher' => 'nullable|string|max:200',
            'edition' => 'nullable|string|max:50',
            'category' => 'sometimes|required|string|max:100',
            'shelf_no' => 'nullable|string|max:50',
        ]);

        $book->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Book updated successfully.',
            'data' => $book->fresh('copies'),
        ]);
    }

    /**
     * Add physical copies to a book.
     */
    public function addCopies(Request $request, string $id): JsonResponse
    {
        $book = LibraryBook::findOrFail($id);

        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:200',
        ]);

        $copies = $this->libraryService->addCopiesToBook($book, $validated['count']);

        return response()->json([
            'success' => true,
            'message' => "Added {$validated['count']} copies successfully.",
            'data' => $copies,
        ], 201);
    }

    /**
     * Issue book copy to a member.
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'copy_id' => 'required|uuid|exists:book_copies,id',
            'member_type' => 'required|string|in:student,employee',
            'member_id' => 'required|uuid',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
        ]);

        try {
            $issue = $this->libraryService->issueBook($validated, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Book issued successfully.',
                'data' => $issue,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Return an issued book.
     */
    public function returnBook(Request $request, string $issueId): JsonResponse
    {
        $validated = $request->validate([
            'return_date' => 'nullable|date',
            'fine_amount' => 'nullable|numeric|min:0',
            'fine_paid' => 'nullable|boolean',
            'fine_rate_per_day' => 'nullable|numeric|min:0',
            'copy_status' => 'nullable|string|in:available,damaged,lost',
        ]);

        try {
            $issue = $this->libraryService->returnBook($issueId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Book returned successfully.',
                'data' => $issue,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Report a book copy as lost or damaged.
     */
    public function reportLostOrDamaged(Request $request, string $copyId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:lost,damaged',
            'fine_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $copy = $this->libraryService->reportLostOrDamaged(
                $copyId,
                $validated['status'],
                $validated['fine_amount'] ?? 0.00
            );

            return response()->json([
                'success' => true,
                'message' => "Book copy marked as {$validated['status']}.",
                'data' => $copy,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Barcode/QR scan lookup.
     */
    public function barcodeLookup(string $barcode): JsonResponse
    {
        try {
            $data = $this->libraryService->lookupBarcode($barcode);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Member library account details.
     */
    public function memberAccount(string $memberType, string $memberId): JsonResponse
    {
        try {
            $data = $this->libraryService->getMemberAccount($memberType, $memberId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Reserve a book.
     */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => 'required|uuid|exists:library_books,id',
            'member_type' => 'required|string|in:student,employee',
            'member_id' => 'required|uuid',
        ]);

        try {
            $reservation = $this->libraryService->reserveBook(
                $validated['book_id'],
                $validated['member_type'],
                $validated['member_id']
            );

            return response()->json([
                'success' => true,
                'message' => 'Book reserved successfully.',
                'data' => $reservation,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reports and analytics.
     */
    public function reports(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $reports = $this->libraryService->getLibraryReports($branchId);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}

