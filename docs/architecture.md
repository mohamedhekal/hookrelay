# Architecture

## Outbound flow

```
HookRelay::dispatch(event, payload, idempotency?)
  → match active endpoints (event filter)
  → create hookrelay_deliveries (skip on idempotency hit)
  → queue DeliverWebhookJob
       → WebhookDeliverer
            → POST signed payload
            → write hookrelay_attempts
            → delivered | failed(+requeue) | dead_lettered
```

## Signature

`v1 = HMAC_SHA256(secret, "{timestamp}.{raw_body}")`  
Header: `t={timestamp},v1={signature}`

## Inbound flow

```
POST /hookrelay/ingest/{source}
  → verify HMAC with configured source secret
  → persist hookrelay_inbounds (idempotent)
  → fire InboundWebhookReceived
```

## Design notes

- Jobs use `$tries = 1`; rescheduling is explicit via delayed re-dispatch so attempt history stays accurate.
- Filament ops UI is intentionally deferred to v0.2.
- Pair with OnceGate on your public APIs and Integrator for calling partner APIs that are not webhooks.
