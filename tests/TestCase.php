<?php

namespace Tests;

use App\Services\DeduplicationService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->partialMock(DeduplicationService::class, function ($mock) {
            $mock->shouldReceive('acquireLock')->andReturnTrue();
        });
    }
}
