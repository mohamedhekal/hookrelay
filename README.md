# HookRelay


[![CI](https://github.com/mohamedhekal/hookrelay/actions/workflows/tests.yml/badge.svg)](https://github.com/mohamedhekal/hookrelay/actions)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%2F12-FF2D20.svg)](https://laravel.com/)

**Search terms:** laravel, webhooks, retries, hmac, dead-letter, saas, php, laravel-package, webhook, outbound-webhooks, signature.


Reliable webhook delivery for Laravel SaaS apps: fan-out events to subscriber endpoints with HMAC signatures, retries, dead-lettering, and replay—plus optional inbound ingest.

## Problem

Webhook handlers fail silently, retries are ad hoc, and there is no audit trail or safe replay when a partner endpoint was down.

## Installation

```bash
composer require hekal/hookrelay
php artisan vendor:publish --tag=hookrelay-config
php artisan migrate
```

## Outbound quick start

```php
use Hekal\HookRelay\Models\WebhookEndpoint;
use Hekal\HookRelay\Facades\HookRelay;

WebhookEndpoint::create([
    'name' => 'Merchant A',
    'url' => 'https://merchant.example/webhooks',
    'secret' => 'whsec_...',
    'events' => ['order.created', 'order.updated'],
]);

HookRelay::dispatch('order.created', [
    'id' => 1001,
    'total' => 2500,
], idempotencyKey: 'order:1001:created');
```

Receivers verify:

```
X-HookRelay-Signature: t=<unix>,v1=<hmac-sha256(secret, "timestamp.body")>
X-HookRelay-Event: order.created
X-HookRelay-Delivery: <uuid>
```

## Inbound ingest

```http
POST /hookrelay/ingest/{source}
X-HookRelay-Signature: t=...,v1=...
Idempotency-Key: optional
```

Configure secrets in `hookrelay.inbound.sources`. Listen for `InboundWebhookReceived`.

## Commands

```bash
php artisan hookrelay:replay {id|uuid}
php artisan hookrelay:process-due
```

## Features (v0.1)

- Endpoint subscriptions with event filters
- Signed outbound deliveries
- Attempt history
- Exponential backoff retries
- Dead-letter status after max attempts
- Idempotency keys per endpoint
- Inbound HMAC verification + event
- Replay / process-due Artisan commands

## Testing

```bash
composer install && composer test
```

## Architecture

See [docs/architecture.md](docs/architecture.md).

## License

MIT
