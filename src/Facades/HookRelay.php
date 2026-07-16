<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Facades;

use Hekal\HookRelay\Models\WebhookDelivery;
use Hekal\HookRelay\Models\WebhookEndpoint;
use Hekal\HookRelay\Services\WebhookDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, WebhookDelivery> dispatch(string $eventType, array<string, mixed> $payload, string|null $idempotencyKey = null)
 * @method static WebhookDelivery|null dispatchTo(WebhookEndpoint $endpoint, string $eventType, array<string, mixed> $payload, string|null $idempotencyKey = null)
 * @method static WebhookDelivery replay(WebhookDelivery|string|int $delivery)
 *
 * @see WebhookDispatcher
 */
final class HookRelay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebhookDispatcher::class;
    }
}
