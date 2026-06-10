<?php

namespace App\Exceptions;

use Exception;

class NotificationException extends Exception
{
    public static function providerFailed(string $channel, string $recipient, string $reason): self
    {
        return new self("Provider failed for {$channel}:{$recipient} - {$reason}");
    }

    public static function duplicate(string $idempotencyKey): self
    {
        return new self("Duplicate notification detected: {$idempotencyKey}");
    }
}
