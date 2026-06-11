<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        private readonly DeduplicationService $deduplicationService,
    ) {}

    public function saveNotificationAndSetKey(
        NotificationChannel $channel,
        NotificationPriority $priority,
        string $message,
        array $recipientIds,
    ): Collection {
        $notifications = collect();

        DB::transaction(function () use ($channel, $priority, $message, $recipientIds, &$notifications) {
            foreach ($recipientIds as $recipientId) {
                $key = $this->deduplicationService->generateKey(
                    $channel->value,
                    (string) $recipientId,
                    $message,
                );
                if (!$this->deduplicationService->acquireLock($key)) {
                    continue;
                }

                try {
                    $data = [
                        'recipient_id' => $recipientId,
                        'channel' => $channel,
                        'priority' => $priority,
                        'message' => $message,
                        'status' => NotificationStatus::Queued,
                        'retry_count' => 0,
                    ];

                    $notification = Notification::create($data);
                } catch (QueryException $e) {
                    throw $e;
                }

                $notifications->push($notification);
            }
        });

        return $notifications;
    }

    public function markSent(Notification $notification): void
    {
        $notification->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function markDelivered(Notification $notification): void
    {
        $notification->update([
            'status' => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }

    public function markDiscarded(Notification $notification, string $errorMessage): void
    {
        $notification->update([
            'status' => NotificationStatus::Discarded,
            'error_message' => $errorMessage,
        ]);
    }

    public function incrementRetry(Notification $notification): void
    {
        $notification->increment('retry_count');
    }

    public function getSubscriberHistory(int $recipientId): Collection
    {
        return Notification::where('recipient_id', $recipientId)
            ->orderByDesc('created_at')
            ->get();
    }
}
