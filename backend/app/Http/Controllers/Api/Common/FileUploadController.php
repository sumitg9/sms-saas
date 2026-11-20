<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\FileUpload;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileUploadController extends Controller
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Handle safe, validated multipart file upload with tenant isolation.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:jpeg,jpg,png,webp,svg,pdf,doc,docx,xls,xlsx,csv,zip',
            ],
            'folder' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'is_private' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant context found for upload.',
            ], 400);
        }

        $folder = $request->input('folder', 'attachments');
        $isPrivate = $request->boolean('is_private', false);

        $extension = $file->getClientOriginalExtension();
        $safeFileName = Str::uuid() . '.' . strtolower($extension);
        $disk = 'public'; // Store in public disk with tenant subfolder or local
        $storagePath = "tenants/{$tenantId}/{$folder}";

        $storedPath = $file->storeAs($storagePath, $safeFileName, $disk);

        $fileUpload = FileUpload::create([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'bucket_name' => $disk,
            'file_path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_private' => $isPrivate,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'id' => $fileUpload->id,
                'original_name' => $fileUpload->original_name,
                'file_path' => $fileUpload->file_path,
                'url' => Storage::disk($disk)->url($fileUpload->file_path),
                'file_size' => $fileUpload->file_size,
                'mime_type' => $fileUpload->mime_type,
                'is_private' => $fileUpload->is_private,
            ],
        ], 201);
    }

    /**
     * Download or view an uploaded file with tenant authorization check.
     */
    public function show(string $id): StreamedResponse|JsonResponse
    {
        $fileUpload = FileUpload::findOrFail($id);

        Gate::authorize('download', $fileUpload);

        $disk = $fileUpload->bucket_name ?: 'public';

        if (!Storage::disk($disk)->exists($fileUpload->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Physical file not found on disk.',
            ], 404);
        }

        return Storage::disk($disk)->download(
            $fileUpload->file_path,
            $fileUpload->original_name,
            ['Content-Type' => $fileUpload->mime_type]
        );
    }
}

