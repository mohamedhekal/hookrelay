<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case DeadLettered = 'dead_lettered';
}
