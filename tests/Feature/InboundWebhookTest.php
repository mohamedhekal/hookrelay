<?php

declare(strict_types=1);

use Hekal\HookRelay\Events\InboundWebhookReceived;
use Hekal\HookRelay\Models\InboundWebhook;
use Hekal\HookRelay\Support\HmacSigner;
use Illuminate\Support\Facades\Event;

it('accepts a valid inbound webhook', function () {
    Event::fake([InboundWebhookReceived::class]);

    $body = json_encode(['type' => 'payment.ok', 'amount' => 10], JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = HmacSigner::sign('inbound-secret', $timestamp, $body);

    $this->call(
        'POST',
        '/hookrelay/ingest/partner',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HOOKRELAY_SIGNATURE' => HmacSigner::headerValue($timestamp, $signature),
            'HTTP_IDEMPOTENCY_KEY' => 'in-1',
        ],
        $body,
    )->assertAccepted()
        ->assertJsonPath('accepted', true);

    expect(InboundWebhook::query()->count())->toBe(1);
    Event::assertDispatched(InboundWebhookReceived::class);
});

it('rejects invalid inbound signatures', function () {
    $body = '{"type":"x"}';

    $this->call(
        'POST',
        '/hookrelay/ingest/partner',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HOOKRELAY_SIGNATURE' => 't=1,v1=deadbeef',
        ],
        $body,
    )->assertUnauthorized();
});
