<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Console\Commands;

use Hekal\HookRelay\Services\WebhookDispatcher;
use Illuminate\Console\Command;

final class ReplayDeliveryCommand extends Command
{
    protected $signature = 'hookrelay:replay {delivery : Delivery id or uuid}';

    protected $description = 'Replay a HookRelay webhook delivery';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $delivery = $dispatcher->replay((string) $this->argument('delivery'));
        $this->info("Requeued delivery [{$delivery->uuid}] (status={$delivery->status}).");

        return self::SUCCESS;
    }
}
