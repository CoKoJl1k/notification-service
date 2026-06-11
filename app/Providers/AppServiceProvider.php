<?php

namespace App\Providers;

use App\Enums\NotificationChannel;
use App\Services\NotificationDispatcher;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(NotificationDispatcher::class, function ($app) {
            $dispatcher = new NotificationDispatcher($app->make(NotificationService::class));

            foreach ($this->getProviders() as $provider) {
                $dispatcher->addProvider($provider);
            }

            return $dispatcher;
        });
    }

    public function boot(): void
    {
        $this->app->afterResolving(NotificationDispatcher::class, function (NotificationDispatcher $dispatcher) {
            //
        });
    }

    private function getProviders(): array
    {
        return [
            NotificationChannel::SMS->value => new MockSmsProvider(),
            NotificationChannel::Email->value => new MockEmailProvider(),
        ];
    }
}
