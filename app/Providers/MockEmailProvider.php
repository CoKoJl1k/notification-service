<?php

namespace App\Providers;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;
use Illuminate\Support\Facades\Log;

class MockEmailProvider implements NotificationProviderInterface
{
    public function send(string $recipient, string $message): bool
    {
        Log::info("MockEmailProvider: sending email to {$recipient}", [
            'message' => $message,
        ]);

        if (str_contains($recipient, 'invalid')) {
            return false;
        }

        return true;
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }
}
