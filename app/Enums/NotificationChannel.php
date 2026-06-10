<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case SMS = 'sms';
    case Email = 'email';
}
