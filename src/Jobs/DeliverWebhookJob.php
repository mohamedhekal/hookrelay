<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Jobs;

use Hekal\HookRelay\Enums\DeliveryStatus;
use Hekal\HookRelay\Models\WebhookDelivery;
use Hekal\HookRelay\Services\WebhookDeliverer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $deliveryId,
    ) {}

    public function handle(WebhookDeliverer $deliverer): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if ($delivery->status === DeliveryStatus::Delivered->value) {
            return;
        }

        if ($delivery->status === DeliveryStatus::DeadLettered->value) {
            return;
        }

        $deliverer->deliver($delivery);

        $delivery->refresh();

        if ($delivery->status === DeliveryStatus::Failed->value && $delivery->next_attempt_at !== null) {
            $delay = max(0, $delivery->next_attempt_at->getTimestamp() - time());
            self::dispatch($delivery->id)
                ->delay(now()->addSeconds($delay))
                ->onQueue((string) config('hookrelay.delivery.queue', 'default'));
        }
    }
}
