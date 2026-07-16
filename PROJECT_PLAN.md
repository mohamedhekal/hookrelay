# HookRelay — Project Plan

## Name

**HookRelay** (`hekal/hookrelay`)

## Vision

Reliable webhook delivery for Laravel SaaS apps: fan-out events to subscriber endpoints with HMAC signing, retries, dead-lettering, and replay—plus optional inbound ingest with signature verification.

## v0.1 scope

**Outbound**
- Endpoints (URL, secret, event filters, active flag)
- `HookRelay::dispatch($event, $payload, $idempotencyKey?)`
- Signed HTTP delivery job with exponential backoff
- Attempt log + dead-letter status
- Artisan: `hookrelay:replay {delivery}` / `hookrelay:work` (optional)
- Idempotency key per endpoint+event

**Inbound (minimal)**
- `POST /hookrelay/ingest/{source}` with HMAC verification
- Persist payload + fire `InboundWebhookReceived`

**Out**
- Filament dashboard (v0.2)
- Transform pipelines
- Multi-tenant UI

## Stack

Laravel 11/12, queues, cache optional, HTTP client.
