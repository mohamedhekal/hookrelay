<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Events;

use Hekal\HookRelay\Models\WebhookDelivery;
use Illuminate\Foundation\Events\Dispatchable;

final class WebhookDeadLettered
{
    use Dispatchable;

    public function __construct(
        public readonly WebhookDelivery $delivery,
    ) {}
}
