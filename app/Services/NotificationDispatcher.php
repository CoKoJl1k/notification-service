<?php

namespace App\Services;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationStatus;
use App\Exceptions\NotificationException;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    private const MAX_RETRIES = 3;

    /** @var array<string, NotificationProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function addProvider(NotificationProviderInterface $provider): void
    {
        $this->providers[$provider->channel()->value] = $provider;
    }

    public function dispatch(Notification $notification): void
    {
        $provider = $this->providers[$notification->channel->value] ?? null;

        if (!$provider) {
            $this->notificationService->markDiscarded(
                $notification,
                "No provider found for channel: {$notification->channel->value}",
            );
            return;
        }

        try {
            $user = User::findOrFail($notification->recipient_id);
            $success = $provider->send($user, $notification->message);

            if ($success) {
                $this->notificationService->markSent($notification);
                $this->notificationService->markDelivered($notification);
            } else {
                $this->handleFailure($notification, 'Provider returned failure');
            }
        } catch (\Throwable $e) {
            Log::error("Provider exception for notification {$notification->id}: {$e->getMessage()}");
            $this->handleFailure($notification, $e->getMessage());
        }
    }

    private function handleFailure(Notification $notification, string $errorMessage): void
    {
        $this->notificationService->incrementRetry($notification);

        if ($notification->retry_count >= self::MAX_RETRIES) {
            $this->notificationService->markDiscarded($notification, $errorMessage);
            return;
        }

        $this->notificationService->markSent($notification);
    }
}
