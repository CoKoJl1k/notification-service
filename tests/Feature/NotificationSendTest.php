<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class NotificationSendTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_it_sends_sms_notifications(): void
    {
        $user1 = User::create(['name' => 'Alice', 'phone' => '+380501234567']);
        $user2 = User::create(['name' => 'Bob', 'phone' => '+380501234568']);

        $payload = [
            'channel' => 'sms',
            'message' => 'Your verification code is 123456',
            'recipient_ids' => [$user1->id, $user2->id],
            'priority' => 'transactional',
        ];

        $response = $this->postJson('/api/notifications/send', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'notification_ids',
                'count',
                'channel',
                'priority',
            ]);

        $this->assertEquals(2, $response->json('count'));
        $this->assertCount(2, Notification::all());
    }

    public function test_it_sends_email_notifications(): void
    {
        $user = User::create(['name' => 'User', 'phone' => 'user@example.com']);
        $admin = User::create(['name' => 'Admin', 'phone' => 'admin@example.com']);

        $payload = [
            'channel' => 'email',
            'message' => 'Welcome to our service!',
            'recipient_ids' => [$user->id, $admin->id],
            'priority' => 'marketing',
        ];

        $response = $this->postJson('/api/notifications/send', $payload);

        $response->assertStatus(201);
        $this->assertEquals(2, $response->json('count'));
        $this->assertCount(2, Notification::all());
    }

    public function test_it_processes_notification_end_to_end(): void
    {
        $user = User::create(['name' => 'Alice', 'phone' => '+380501234567']);

        $payload = [
            'channel' => 'sms',
            'message' => 'Test message',
            'recipient_ids' => [$user->id],
            'priority' => 'marketing',
        ];

        $response = $this->postJson('/api/notifications/send', $payload);

        $response->assertStatus(201);

        $notification = Notification::first();
        $this->assertEquals((string) $user->id, $notification->recipient_id);
        $this->assertEquals(NotificationStatus::Delivered, $notification->status);
        $this->assertNotNull($notification->sent_at);
        $this->assertNotNull($notification->delivered_at);
    }

    public function test_it_returns_subscriber_history(): void
    {
        $user = User::create(['name' => 'Alice', 'phone' => '+380501234567']);

        Notification::create([
            'recipient_id' => $user->id,
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Test',
            'status' => NotificationStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        Notification::create([
            'recipient_id' => $user->id,
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Promo',
            'status' => NotificationStatus::Queued,
        ]);

        $response = $this->getJson("/api/notifications/subscriber/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recipient_id',
                'phone',
                'notifications' => [
                    '*' => [
                        'id', 'recipient_id', 'channel', 'priority',
                        'status', 'created_at',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('notifications'));
        $this->assertEquals($user->id, $response->json('recipient_id'));
    }

    public function test_it_returns_single_notification(): void
    {
        $notification = Notification::create([
            'recipient_id' => 'user123',
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Test',
            'status' => NotificationStatus::Queued,
        ]);

        $response = $this->getJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $notification->id);
    }

    public function test_it_validates_request(): void
    {
        $response = $this->postJson('/api/notifications/send', []);

        $response->assertStatus(422);
    }

    public function test_it_validates_channel_field(): void
    {
        $user = User::create(['name' => 'Alice', 'phone' => '+380501234567']);

        $payload = [
            'channel' => 'invalid',
            'message' => 'Test',
            'recipient_ids' => [$user->id],
            'priority' => 'marketing',
        ];

        $response = $this->postJson('/api/notifications/send', $payload);
        $response->assertStatus(422);
    }

    public function test_it_validates_priority_field(): void
    {
        $user = User::create(['name' => 'Alice', 'phone' => '+380501234567']);

        $payload = [
            'channel' => 'sms',
            'message' => 'Test',
            'recipient_ids' => [$user->id],
            'priority' => 'invalid',
        ];

        $response = $this->postJson('/api/notifications/send', $payload);
        $response->assertStatus(422);
    }

    public function test_it_validates_recipient_ids_exist(): void
    {
        $payload = [
            'channel' => 'sms',
            'message' => 'Test',
            'recipient_ids' => [999],
            'priority' => 'marketing',
        ];

        $response = $this->postJson('/api/notifications/send', $payload);
        $response->assertStatus(422);
    }

    public function test_it_returns_404_for_nonexistent_notification(): void
    {
        $response = $this->getJson('/api/notifications/nonexistent-id');
        $response->assertStatus(404);
    }
}
