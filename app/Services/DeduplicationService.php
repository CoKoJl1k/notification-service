<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DeduplicationService
{
    private const TTL_SECONDS = 3600;

    public function generateKey(string $channel, string $recipientId, string $message, ?string $customKey = null): string
    {
        if ($customKey) {
            return 'dedup:' . $customKey;
        }

        $hash = md5($channel . ':' . $recipientId . ':' . $message);
        return 'dedup:' . $hash;
    }

    public function acquireLock(string $key): bool
    {
        return (bool) Redis::set($key, '1', 'EX', self::TTL_SECONDS, 'NX');
    }

    public function isProcessed(string $key): bool
    {
        return (bool) Redis::exists($key);
    }

    public function markProcessed(string $key): void
    {
        Redis::setex($key, self::TTL_SECONDS, '1');
    }

    public function remove(string $key): void
    {
        Redis::del($key);
    }
}
