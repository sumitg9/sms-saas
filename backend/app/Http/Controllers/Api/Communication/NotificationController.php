<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\BulkNotificationRequest;
use App\Http\Requests\Communication\SendNotificationRequest;
use App\Models\Notification;
use App\Services\Communication\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $result = $this->notificationService->send($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification dispatched successfully.',
            'data' => $result,
        ], 202);
    }

    public function sendBulk(BulkNotificationRequest $request): JsonResponse
    {
        $result = $this->notificationService->sendBulk($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Bulk notifications queued successfully.',
            'data' => $result,
        ], 202);
    }

    public function logs(Request $request): JsonResponse
    {
        $this->authorize('communication.view');

        $logs = $this->notificationService->getLogs($request->all());

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function retry(string $id): JsonResponse
    {
        $this->authorize('communication.manage');

        $retried = $this->notificationService->retry($id);

        return response()->json([
            'success' => $retried,
            'message' => $retried ? 'Notification retry queued successfully.' : 'Notification could not be retried.',
        ]);
    }

    public function userNotifications(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function markNotificationRead(string $id, Request $request): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }
}

