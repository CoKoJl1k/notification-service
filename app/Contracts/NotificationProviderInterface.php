<?php

namespace App\Contracts;

use App\Enums\NotificationChannel;
use App\Models\User;

interface NotificationProviderInterface
{
    public function send(User $recipient, string $message): bool;
    public function channel(): NotificationChannel;
}
