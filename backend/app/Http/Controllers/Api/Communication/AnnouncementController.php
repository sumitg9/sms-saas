<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\AnnouncementRequest;
use App\Models\Announcement;
use App\Services\Communication\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $announcements = $this->announcementService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->announcementService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Announcement published successfully.',
            'data' => $announcement,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $announcement = Announcement::with(['author', 'schoolClass', 'section', 'acknowledgements.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ]);
    }

    public function update(AnnouncementRequest $request, string $id): JsonResponse
    {
        $this->authorize('communication.edit');

        $announcement = Announcement::findOrFail($id);
        $updated = $this->announcementService->update($announcement, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Announcement updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('communication.delete');

        $announcement = Announcement::findOrFail($id);
        $this->announcementService->delete($announcement);

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function acknowledge(string $id, Request $request): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $ack = $this->announcementService->acknowledge($announcement, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Announcement acknowledged successfully.',
            'data' => $ack,
        ]);
    }
}

