<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Exceptions\NotificationException;
use App\Models\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        private readonly DeduplicationService $deduplicationService,
    ) {}

    public function dispatch(
        NotificationChannel $channel,
        NotificationPriority $priority,
        string $message,
        array $recipientIds,
        ?string $idempotencyKey = null,
    ): Collection {
        $notifications = collect();

        DB::transaction(function () use ($channel, $priority, $message, $recipientIds, $idempotencyKey, &$notifications) {
            foreach ($recipientIds as $recipientId) {
                if ($idempotencyKey) {
                    $key = $this->deduplicationService->generateKey(
                        $channel->value,
                        $recipientId,
                        $message,
                        $idempotencyKey,
                    );

                    if (!$this->deduplicationService->acquireLock($key)) {
                        continue;
                    }
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

                    if ($idempotencyKey) {
                        $data['idempotency_key'] = $key;
                    }

                    $notification = Notification::create($data);
                } catch (QueryException $e) {
                    if ($idempotencyKey && $e->getCode() === '23505') {
                        continue;
                    }
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

    public function getSubscriberHistory(string $recipientId): Collection
    {
        return Notification::where('recipient_id', $recipientId)
            ->orderByDesc('created_at')
            ->get();
    }
}
