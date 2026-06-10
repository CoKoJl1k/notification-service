<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 1;

    public ?string $queue = null;

    public function __construct(
        private readonly string $notificationId,
        ?string $queue = null,
    ) {
        $this->queue = $queue;
    }

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification || $notification->isFinalState()) {
            return;
        }

        $dispatcher->dispatch($notification);
    }

    public function failed(\Throwable $e): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification && !$notification->isFinalState()) {
            $notification->update([
                'status' => \App\Enums\NotificationStatus::Discarded,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
