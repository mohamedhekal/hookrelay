<?php

declare(strict_types=1);

use Hekal\HookRelay\Enums\DeliveryStatus;
use Hekal\HookRelay\Facades\HookRelay;
use Hekal\HookRelay\Jobs\DeliverWebhookJob;
use Hekal\HookRelay\Models\WebhookAttempt;
use Hekal\HookRelay\Models\WebhookDelivery;
use Hekal\HookRelay\Models\WebhookEndpoint;
use Hekal\HookRelay\Services\WebhookDeliverer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('fans out events to matching endpoints', function () {
    Queue::fake();

    WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => 'sec-1',
        'events' => ['order.created'],
        'is_active' => true,
        'max_attempts' => 3,
    ]);

    WebhookEndpoint::query()->create([
        'name' => 'All',
        'url' => 'https://hooks.example.test/all',
        'secret' => 'sec-2',
        'events' => null,
        'is_active' => true,
        'max_attempts' => 3,
    ]);

    WebhookEndpoint::query()->create([
        'name' => 'Other',
        'url' => 'https://hooks.example.test/other',
        'secret' => 'sec-3',
        'events' => ['invoice.paid'],
        'is_active' => true,
        'max_attempts' => 3,
    ]);

    $deliveries = HookRelay::dispatch('order.created', ['id' => 1], 'idem-1');

    expect($deliveries)->toHaveCount(2);
    Queue::assertPushed(DeliverWebhookJob::class, 2);
});

it('delivers successfully and records an attempt', function () {
    Http::fake([
        'hooks.example.test/*' => Http::response(['received' => true], 200),
    ]);

    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => 'sec-1',
        'events' => null,
        'is_active' => true,
        'max_attempts' => 3,
    ]);

    $delivery = WebhookDelivery::query()->create([
        'endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['id' => 9],
        'status' => DeliveryStatus::Pending->value,
    ]);

    app(WebhookDeliverer::class)->deliver($delivery->fresh());

    $delivery->refresh();

    expect($delivery->status)->toBe(DeliveryStatus::Delivered->value)
        ->and(WebhookAttempt::query()->where('delivery_id', $delivery->id)->count())->toBe(1);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-HookRelay-Signature')
            && $request->hasHeader('X-HookRelay-Event', 'order.created');
    });
});

it('dead-letters after max attempts', function () {
    Http::fake([
        'hooks.example.test/*' => Http::response('nope', 500),
    ]);

    $endpoint = WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => 'sec-1',
        'events' => null,
        'is_active' => true,
        'max_attempts' => 2,
    ]);

    $delivery = WebhookDelivery::query()->create([
        'endpoint_id' => $endpoint->id,
        'event_type' => 'order.created',
        'payload' => ['id' => 9],
        'status' => DeliveryStatus::Pending->value,
    ]);

    $deliverer = app(WebhookDeliverer::class);
    $deliverer->deliver($delivery->fresh());
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Failed->value);

    $deliverer->deliver($delivery->fresh());
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::DeadLettered->value)
        ->and(WebhookAttempt::query()->where('delivery_id', $delivery->id)->count())->toBe(2);
});

it('skips duplicate idempotency keys per endpoint', function () {
    Queue::fake();

    WebhookEndpoint::query()->create([
        'name' => 'Orders',
        'url' => 'https://hooks.example.test/orders',
        'secret' => 'sec-1',
        'events' => null,
        'is_active' => true,
        'max_attempts' => 3,
    ]);

    $first = HookRelay::dispatch('order.created', ['id' => 1], 'same-key');
    $second = HookRelay::dispatch('order.created', ['id' => 1], 'same-key');

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0)
        ->and(WebhookDelivery::query()->count())->toBe(1);
});
