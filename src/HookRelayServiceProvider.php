<?php

declare(strict_types=1);

namespace Hekal\HookRelay;

use Hekal\HookRelay\Console\Commands\ProcessDueDeliveriesCommand;
use Hekal\HookRelay\Console\Commands\ReplayDeliveryCommand;
use Hekal\HookRelay\Services\InboundWebhookProcessor;
use Hekal\HookRelay\Services\WebhookDeliverer;
use Hekal\HookRelay\Services\WebhookDispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class HookRelayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hookrelay.php', 'hookrelay');

        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(WebhookDeliverer::class);
        $this->app->singleton(InboundWebhookProcessor::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/hookrelay.php' => config_path('hookrelay.php'),
        ], 'hookrelay-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'hookrelay-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('hookrelay.inbound.enabled', true)) {
            $this->app->make(Router::class)
                ->middleware((array) config('hookrelay.inbound.middleware', ['api']))
                ->prefix((string) config('hookrelay.route_prefix', 'hookrelay'))
                ->group(__DIR__.'/../routes/hookrelay.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReplayDeliveryCommand::class,
                ProcessDueDeliveriesCommand::class,
            ]);
        }
    }
}
