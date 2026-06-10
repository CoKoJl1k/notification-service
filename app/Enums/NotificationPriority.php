<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';

    public function queuePriority(): int
    {
        return match ($this) {
            self::Transactional => 10,
            self::Marketing => 0,
        };
    }
}
