<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Services;

use Hekal\HookRelay\Enums\DeliveryStatus;
use Hekal\HookRelay\Jobs\DeliverWebhookJob;
use Hekal\HookRelay\Models\WebhookDelivery;
use Hekal\HookRelay\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class WebhookDispatcher
{
    /**
     * Fan-out an event to all matching active endpoints.
     *
     * @param  array<string, mixed>  $payload
     * @return Collection<int, WebhookDelivery>
     */
    public function dispatch(string $eventType, array $payload, ?string $idempotencyKey = null): Collection
    {
        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->accepts($eventType));

        $deliveries = collect();

        foreach ($endpoints as $endpoint) {
            $delivery = $this->createDelivery($endpoint, $eventType, $payload, $idempotencyKey);
            if ($delivery !== null) {
                $deliveries->push($delivery);
                DeliverWebhookJob::dispatch($delivery->id)
                    ->onQueue((string) config('hookrelay.delivery.queue', 'default'));
            }
        }

        return $deliveries;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatchTo(WebhookEndpoint $endpoint, string $eventType, array $payload, ?string $idempotencyKey = null): ?WebhookDelivery
    {
        if (! $endpoint->accepts($eventType)) {
            return null;
        }

        $delivery = $this->createDelivery($endpoint, $eventType, $payload, $idempotencyKey);

        if ($delivery !== null) {
            DeliverWebhookJob::dispatch($delivery->id)
                ->onQueue((string) config('hookrelay.delivery.queue', 'default'));
        }

        return $delivery;
    }

    public function replay(WebhookDelivery|string|int $delivery): WebhookDelivery
    {
        $model = $this->resolveDelivery($delivery);

        $model->forceFill([
            'status' => DeliveryStatus::Pending->value,
            'next_attempt_at' => now(),
            'dead_lettered_at' => null,
            'last_error' => null,
        ])->save();

        DeliverWebhookJob::dispatch($model->id)
            ->onQueue((string) config('hookrelay.delivery.queue', 'default'));

        return $model->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createDelivery(
        WebhookEndpoint $endpoint,
        string $eventType,
        array $payload,
        ?string $idempotencyKey,
    ): ?WebhookDelivery {
        try {
            return DB::transaction(function () use ($endpoint, $eventType, $payload, $idempotencyKey) {
                if ($idempotencyKey !== null) {
                    $existing = WebhookDelivery::query()
                        ->where('endpoint_id', $endpoint->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        return null;
                    }
                }

                return WebhookDelivery::query()->create([
                    'endpoint_id' => $endpoint->id,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'idempotency_key' => $idempotencyKey,
                    'status' => DeliveryStatus::Pending->value,
                    'next_attempt_at' => now(),
                ]);
            });
        } catch (Throwable) {
            // Unique constraint race on idempotency key.
            return null;
        }
    }

    private function resolveDelivery(WebhookDelivery|string|int $delivery): WebhookDelivery
    {
        if ($delivery instanceof WebhookDelivery) {
            return $delivery;
        }

        $query = WebhookDelivery::query();

        return is_numeric($delivery)
            ? $query->findOrFail((int) $delivery)
            : $query->where('uuid', (string) $delivery)->firstOrFail();
    }
}
