<?php

namespace App\Providers;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;


class MockEmailProvider implements NotificationProviderInterface
{
    public function send(User $recipient, string $message): bool
    {
        try {
            Log::info("MockEmailProvider: sending email to {$recipient->email}", ['message' => $message]);
        } catch (Exception $e) {
            Log::info($e->getMessage() . ' in ' . $e->getFile() . ' ' . $e->getLine());
            return false;
        }

        return true;
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }
}
