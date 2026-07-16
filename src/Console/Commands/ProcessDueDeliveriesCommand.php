<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Console\Commands;

use Hekal\HookRelay\Enums\DeliveryStatus;
use Hekal\HookRelay\Jobs\DeliverWebhookJob;
use Hekal\HookRelay\Models\WebhookDelivery;
use Illuminate\Console\Command;

final class ProcessDueDeliveriesCommand extends Command
{
    protected $signature = 'hookrelay:process-due {--limit=100}';

    protected $description = 'Queue due/failed HookRelay deliveries that are ready for another attempt';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $ids = WebhookDelivery::query()
            ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Failed->value])
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverWebhookJob::dispatch((int) $id)
                ->onQueue((string) config('hookrelay.delivery.queue', 'default'));
        }

        $this->info('Queued '.$ids->count().' delivery job(s).');

        return self::SUCCESS;
    }
}
