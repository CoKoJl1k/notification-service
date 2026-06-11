<?php

namespace App\Providers;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class MockSmsProvider implements NotificationProviderInterface
{
    public function send(User $recipient, string $message): bool
    {
        try {
            Log::info("MockSmsProvider: sending SMS to {$recipient->phone}", ['message' => $message,]);
        } catch (Exception $e) {
            Log::info($e->getMessage() . ' in ' . $e->getFile() . ' ' . $e->getLine());
            return false;
        }
        return true;
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::SMS;
    }
}
