<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Exceptions;

use RuntimeException;

class HookRelayException extends RuntimeException
{
    public static function endpointNotFound(string $identifier): self
    {
        return new self("Webhook endpoint [{$identifier}] was not found.");
    }

    public static function deliveryNotFound(string $identifier): self
    {
        return new self("Webhook delivery [{$identifier}] was not found.");
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid webhook signature.');
    }
}
