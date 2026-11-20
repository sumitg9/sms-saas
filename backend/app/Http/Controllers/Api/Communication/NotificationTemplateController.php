<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('communication.view');

        $query = NotificationTemplate::query();

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->has('event_name')) {
            $query->where('event_name', $request->event_name);
        }

        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('communication.create');

        $validated = $request->validate([
            'event_name' => 'required|string|max:100',
            'channel' => 'required|string|in:in_app,email,sms,whatsapp,push',
            'language' => 'nullable|string|max:10',
            'subject' => 'nullable|string|max:200',
            'body_template' => 'required|string',
            'placeholders' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = NotificationTemplate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Notification template created successfully.',
            'data' => $template,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('communication.view');

        $template = NotificationTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('communication.edit');

        $template = NotificationTemplate::findOrFail($id);

        $validated = $request->validate([
            'subject' => 'nullable|string|max:200',
            'body_template' => 'required|string',
            'language' => 'nullable|string|max:10',
            'placeholders' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Notification template updated successfully.',
            'data' => $template,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('communication.delete');

        $template = NotificationTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification template deleted successfully.',
        ]);
    }
}

