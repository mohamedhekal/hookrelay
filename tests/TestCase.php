<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Tests;

use Hekal\HookRelay\HookRelayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [HookRelayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('hookrelay.delivery.backoff.base_seconds', 0);
        $app['config']->set('hookrelay.delivery.backoff.max_seconds', 0);
        $app['config']->set('hookrelay.delivery.default_max_attempts', 3);
        $app['config']->set('hookrelay.inbound.enabled', true);
        $app['config']->set('hookrelay.inbound.sources', [
            'partner' => 'inbound-secret',
        ]);
    }
}
