<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Services;

use Hekal\HookRelay\Events\InboundWebhookReceived;
use Hekal\HookRelay\Exceptions\HookRelayException;
use Hekal\HookRelay\Models\InboundWebhook;
use Hekal\HookRelay\Support\HmacSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class InboundWebhookProcessor
{
    public function handle(string $source, Request $request): InboundWebhook
    {
        /** @var array<string, string|null> $sources */
        $sources = (array) config('hookrelay.inbound.sources', []);
        $secret = $sources[$source] ?? null;

        $raw = $request->getContent();
        $signatureHeader = $request->header((string) config('hookrelay.inbound.signature_header'));

        $valid = false;
        if (is_string($secret) && $secret !== '') {
            $valid = HmacSigner::verify(
                secret: $secret,
                body: $raw,
                header: $signatureHeader,
                toleranceSeconds: (int) config('hookrelay.inbound.tolerance_seconds', 300),
            );

            if (! $valid) {
                throw HookRelayException::invalidSignature();
            }
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = ['raw' => $raw];
        }

        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->header('X-HookRelay-Idempotency-Key');

        try {
            $inbound = DB::transaction(function () use ($source, $payload, $request, $valid, $idempotencyKey) {
                if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                    $existing = InboundWebhook::query()
                        ->where('source', $source)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        return $existing;
                    }
                }

                return InboundWebhook::query()->create([
                    'source' => $source,
                    'event_type' => $request->header((string) config('hookrelay.delivery.event_header'))
                        ?? ($payload['type'] ?? $payload['event'] ?? null),
                    'payload' => $payload,
                    'headers' => $request->headers->all(),
                    'signature_valid' => $valid,
                    'idempotency_key' => is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
                ]);
            });
        } catch (Throwable $e) {
            // Unique race — re-fetch
            if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                $existing = InboundWebhook::query()
                    ->where('source', $source)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }

        if ($inbound->processed_at === null) {
            event(new InboundWebhookReceived($inbound));
            $inbound->forceFill(['processed_at' => now()])->save();
        }

        return $inbound;
    }
}
