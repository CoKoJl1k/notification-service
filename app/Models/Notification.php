<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    protected $fillable = [
        'recipient_id',
        'channel',
        'priority',
        'message',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'idempotency_key',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'priority' => NotificationPriority::class,
            'status' => NotificationStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function isFinalState(): bool
    {
        return in_array($this->status, [
            NotificationStatus::Delivered,
            NotificationStatus::Discarded,
        ], true);
    }
}
