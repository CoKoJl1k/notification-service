<?php

namespace App\Contracts;

use App\Enums\NotificationChannel;

interface NotificationProviderInterface
{
    public function send(string $recipient, string $message): bool;
    public function channel(): NotificationChannel;
}
