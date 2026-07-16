<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Services;

use Hekal\HookRelay\Events\WebhookDeadLettered;
use Hekal\HookRelay\Events\WebhookDelivered;
use Hekal\HookRelay\Models\WebhookAttempt;
use Hekal\HookRelay\Models\WebhookDelivery;
use Hekal\HookRelay\Support\BackoffCalculator;
use Hekal\HookRelay\Support\HmacSigner;
use Illuminate\Support\Facades\Http;
use Throwable;

final class WebhookDeliverer
{
    public function deliver(WebhookDelivery $delivery): void
    {
        $delivery->loadMissing('endpoint');
        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->is_active) {
            $delivery->markDeadLettered('Endpoint missing or inactive.');
            event(new WebhookDeadLettered($delivery));

            return;
        }

        $delivery->markProcessing();
        $attemptNumber = $delivery->attempts_count + 1;
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = HmacSigner::sign($endpoint->secret, $timestamp, $body);

        $headers = array_merge((array) ($endpoint->headers ?? []), [
            'Content-Type' => 'application/json',
            'User-Agent' => (string) config('hookrelay.delivery.user_agent', 'HookRelay/0.1'),
            (string) config('hookrelay.delivery.signature_header') => HmacSigner::headerValue($timestamp, $signature),
            (string) config('hookrelay.delivery.timestamp_header') => $timestamp,
            (string) config('hookrelay.delivery.event_header') => $delivery->event_type,
            (string) config('hookrelay.delivery.delivery_header') => $delivery->uuid,
        ]);

        $started = hrtime(true);
        $statusCode = null;
        $responseBody = null;
        $error = null;
        $success = false;

        try {
            $response = Http::timeout((int) config('hookrelay.delivery.timeout', 10))
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $statusCode = $response->status();
            $responseBody = mb_substr($response->body(), 0, 2000);
            $success = $response->successful();
            if (! $success) {
                $error = 'HTTP '.$statusCode;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);

        WebhookAttempt::query()->create([
            'delivery_id' => $delivery->id,
            'attempt_number' => $attemptNumber,
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'error' => $error,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);

        $delivery->forceFill(['attempts_count' => $attemptNumber])->save();

        if ($success) {
            $delivery->markDelivered();
            event(new WebhookDelivered($delivery->fresh()));

            return;
        }

        $maxAttempts = $endpoint->max_attempts;
        $message = $error ?? 'Delivery failed';

        if ($attemptNumber >= $maxAttempts) {
            $delivery->markDeadLettered($message);
            event(new WebhookDeadLettered($delivery->fresh()));

            return;
        }

        $backoff = (array) config('hookrelay.delivery.backoff', []);
        $delay = BackoffCalculator::delaySeconds(
            $attemptNumber,
            (int) ($backoff['base_seconds'] ?? 30),
            (float) ($backoff['multiplier'] ?? 2.0),
            (int) ($backoff['max_seconds'] ?? 3600),
        );

        $delivery->markFailed($message, now()->addSeconds($delay));
    }
}
