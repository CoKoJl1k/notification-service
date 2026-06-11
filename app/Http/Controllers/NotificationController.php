<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Http\Requests\SendNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Jobs\ProcessNotification;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $channel  = NotificationChannel::from($request->input('channel'));
        $priority = NotificationPriority::from($request->input('priority'));
        $userIds  = $request->input('recipient_ids');
        $message  = $request->input('message');
        $idempotencyKey = $request->input('idempotency_key');

        $notifications = $this->notificationService->saveAndSetKey(
            $channel,
            $priority,
            $message,
            $userIds,
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

    public function subscriberHistory(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $notifications = $this->notificationService->getSubscriberHistory($user->id);

        return response()->json([
            'recipient_id'  => (int) $userId,
            'phone'         => $user->phone,
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
