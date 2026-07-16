<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Support;

final class BackoffCalculator
{
    public static function delaySeconds(int $attempt, int $base, float $multiplier, int $max): int
    {
        $attempt = max(1, $attempt);
        $delay = (int) round($base * ($multiplier ** ($attempt - 1)));

        return min($delay, $max);
    }
}
