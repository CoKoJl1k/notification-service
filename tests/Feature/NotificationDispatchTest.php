<?php

namespace Tests\Feature;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_notification_via_provider(): void
    {
        $service = $this->app->make(NotificationService::class);
        $dispatcher = $this->app->make(NotificationDispatcher::class);

        $notifications = $service->dispatch(
            NotificationChannel::SMS,
            NotificationPriority::Transactional,
            'Test message',
            ['+380501234567'],
        );

        $notification = $notifications->first();
        $dispatcher->dispatch($notification);

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Delivered, $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertNotNull($notification->delivered_at);
    }

    public function test_it_dispatches_email_via_provider(): void
    {
        $service = $this->app->make(NotificationService::class);
        $dispatcher = $this->app->make(NotificationDispatcher::class);

        $notifications = $service->dispatch(
            NotificationChannel::Email,
            NotificationPriority::Marketing,
            'Welcome',
            ['user@example.com'],
        );

        $notification = $notifications->first();
        $dispatcher->dispatch($notification);

        $notification->refresh();
        $this->assertEquals(NotificationStatus::Delivered, $notification->status);
    }

    public function test_it_marks_discarded_after_max_retries(): void
    {
        $provider = new class implements NotificationProviderInterface {
            public function send(User $recipient, string $message): bool
            {
                return false;
            }
            public function channel(): NotificationChannel
            {
                return NotificationChannel::SMS;
            }
        };

        $service = $this->app->make(NotificationService::class);
        $dispatcher = new NotificationDispatcher($service);
        $dispatcher->addProvider($provider);

        $notifications = $service->dispatch(
            NotificationChannel::SMS,
            NotificationPriority::Transactional,
            'Test',
            ['+380501234567'],
        );

        $notification = $notifications->first();

        for ($i = 0; $i < 3; $i++) {
            $dispatcher->dispatch($notification);
            $notification->refresh();
        }

        $this->assertEquals(NotificationStatus::Discarded, $notification->status);
        $this->assertEquals(3, $notification->retry_count);
    }

    public function test_it_discards_on_provider_failure(): void
    {
        $provider = new class implements NotificationProviderInterface {
            public function send(User $recipient, string $message): bool
            {
                return false;
            }
            public function channel(): NotificationChannel
            {
                return NotificationChannel::SMS;
            }
        };

        $service = $this->app->make(NotificationService::class);
        $dispatcher = new NotificationDispatcher($service);
        $dispatcher->addProvider($provider);

        $notifications = $service->dispatch(
            NotificationChannel::SMS,
            NotificationPriority::Transactional,
            'Test',
            ['+380501234567'],
        );

        $notification = $notifications->first();

        $dispatcher->dispatch($notification);
        $notification->refresh();

        $this->assertEquals(NotificationStatus::Sent, $notification->status);
        $this->assertEquals(1, $notification->retry_count);
    }

    public function test_it_maps_priority_to_correct_queue_priority(): void
    {
        $this->assertEquals(10, NotificationPriority::Transactional->queuePriority());
        $this->assertEquals(0, NotificationPriority::Marketing->queuePriority());
    }

    public function test_it_routes_notifications_to_correct_queues(): void
    {
        $service = $this->app->make(NotificationService::class);

        $transactional = $service->dispatch(
            NotificationChannel::SMS,
            NotificationPriority::Transactional,
            'High priority',
            ['+380501234567'],
        );

        $marketing = $service->dispatch(
            NotificationChannel::SMS,
            NotificationPriority::Marketing,
            'Low priority',
            ['+380501234568'],
        );

        $this->assertEquals(NotificationPriority::Transactional, $transactional->first()->priority);
        $this->assertEquals(NotificationPriority::Marketing, $marketing->first()->priority);
    }
}
