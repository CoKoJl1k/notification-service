<?php

namespace App\Console\Commands;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConsumeNotificationsCommand extends Command
{
    protected $signature = 'notifications:consume
        {--queue= : The queue to consume from (default: all)}';

    protected $description = 'Consume pending notifications from the database and dispatch them';

    public function handle(NotificationService $notificationService, NotificationDispatcher $dispatcher): void
    {
        $this->info('Starting notification consumer...');

        while (true) {
            $query = Notification::where('status', NotificationStatus::Queued)
                ->orderByRaw("CASE WHEN priority = 'transactional' THEN 0 ELSE 1 END")
                ->orderBy('created_at');

            if ($queue = $this->option('queue')) {
                $query->where('priority', $queue);
            }

            $notifications = $query->limit(50)->get();

            if ($notifications->isEmpty()) {
                sleep(1);
                continue;
            }

            foreach ($notifications as $notification) {
                try {
                    $dispatcher->dispatch($notification);
                } catch (\Throwable $e) {
                    Log::error("Failed to process notification {$notification->id}: {$e->getMessage()}");
                }
            }
        }
    }
}
