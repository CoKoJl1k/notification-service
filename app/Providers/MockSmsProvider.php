<?php

namespace App\Providers;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;
use Illuminate\Support\Facades\Log;

class MockSmsProvider implements NotificationProviderInterface
{
    public function send(string $recipient, string $message): bool
    {
        Log::info("MockSmsProvider: sending SMS to {$recipient}", [
            'message' => $message,
        ]);

        if (str_starts_with($recipient, '000')) {
            return false;
        }

        return true;
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::SMS;
    }
}
