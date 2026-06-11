<?php

namespace App\Services;

class DeduplicationService
{
    private const TTL_SECONDS = 3600;

    public function generateKey(string $channel, string $recipientId, string $message): string
    {
        $hash = md5($channel . ':' . $recipientId . ':' . $message);
        return 'dedup:' . $hash;
    }

    public function acquireLock(string $key): bool
    {
        if (!$this->hasRedis()) {
            return true;
        }

        try {
            return (bool) app('redis.connection')->set($key, '1', 'EX', self::TTL_SECONDS, 'NX');
        } catch (\Throwable) {
            return true;
        }
    }

    private function hasRedis(): bool
    {
        return app()->bound('redis') && class_exists(\Predis\Client::class);
    }
}
