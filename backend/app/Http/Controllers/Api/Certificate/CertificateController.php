<?php

namespace App\Http\Controllers\Api\Certificate;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Services\Certificate\CertificateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class CertificateController extends Controller
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * List all certificate templates.
     */
    public function indexTemplates(Request $request): JsonResponse
    {
        $query = CertificateTemplate::query();

        if ($request->filled('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->input('branch_id'))
                    ->orWhereNull('branch_id');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Create a new certificate template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'body_template' => 'required|string',
            'branch_id' => 'nullable|uuid',
            'header_content' => 'nullable|string',
            'footer_content' => 'nullable|string',
            'signature_title_1' => 'nullable|string|max:100',
            'signature_title_2' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $template = $this->certificateService->createTemplate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Certificate template created successfully.',
            'data' => $template,
        ], 201);
    }

    /**
     * List issued certificates with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Certificate::with(['student', 'template', 'branch']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'ilike', "%{$search}%")
                    ->orWhere('recipient_name', 'ilike', "%{$search}%")
                    ->orWhere('verification_code', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 20);
        $certificates = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $certificates->items(),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'total' => $certificates->total(),
                'per_page' => $certificates->perPage(),
            ],
        ]);
    }

    /**
     * Retrieve single certificate.
     */
    public function show(string $id): JsonResponse
    {
        $certificate = Certificate::with(['student', 'template', 'branch', 'issuer', 'revoker'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $certificate,
        ]);
    }

    /**
     * Issue a certificate to a student.
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid',
            'branch_id' => 'required|uuid',
            'template_id' => 'nullable|uuid',
            'type' => 'nullable|string|in:transfer,bonafide,character,study,fee_clearance,custom',
            'academic_session_id' => 'nullable|uuid',
            'conduct' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
        ]);

        try {
            $certificate = $this->certificateService->issueCertificate($validated, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Certificate issued successfully.',
                'data' => $certificate,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Printable HTML certificate output.
     */
    public function print(string $id): Response
    {
        $certificate = Certificate::findOrFail($id);
        $html = $this->certificateService->renderCertificateHtml($certificate);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Revoke an issued certificate.
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        try {
            $certificate = $this->certificateService->revokeCertificate(
                $id,
                $validated['reason'],
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Certificate has been revoked.',
                'data' => $certificate,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Public QR Code Verification endpoint.
     * Accessible without authentication.
     */
    public function verifyPublic(string $verificationCode): JsonResponse
    {
        $result = $this->certificateService->verifyCertificate($verificationCode);

        $status = $result['is_valid'] ? 200 : 404;
        if (!empty($result['is_revoked'])) {
            $status = 410; // Gone / revoked
        }

        return response()->json($result, $status);
    }
}

