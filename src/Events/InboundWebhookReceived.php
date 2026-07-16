<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Events;

use Hekal\HookRelay\Models\InboundWebhook;
use Illuminate\Foundation\Events\Dispatchable;

final class InboundWebhookReceived
{
    use Dispatchable;

    public function __construct(
        public readonly InboundWebhook $inbound,
    ) {}
}
