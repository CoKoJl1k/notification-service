<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Http\Requests\SendNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $channel = NotificationChannel::from($request->input('channel'));
        $priority = NotificationPriority::from($request->input('priority'));
        $recipientIds = $request->input('recipient_ids');
        $message = $request->input('message');
        $idempotencyKey = $request->input('idempotency_key');

        $notifications = $this->notificationService->dispatch(
            $channel,
            $priority,
            $message,
            $recipientIds,
            $idempotencyKey,
        );

        foreach ($notifications as $notification) {
            $queue = $priority === NotificationPriority::Transactional
                ? 'notifications_high'
                : 'notifications_low';

            ProcessNotification::dispatch($notification->id, $queue);
        }

        return response()->json([
            'notification_ids' => $notifications->pluck('id'),
            'count' => $notifications->count(),
            'channel' => $channel->value,
            'priority' => $priority->value,
        ], 201);
    }

    public function subscriberHistory(string $recipientId): JsonResponse
    {
        $notifications = $this->notificationService->getSubscriberHistory($recipientId);

        return response()->json([
            'recipient_id' => $recipientId,
            'notifications' => NotificationResource::collection($notifications),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }
}
