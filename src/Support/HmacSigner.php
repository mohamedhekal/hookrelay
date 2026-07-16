<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Support;

final class HmacSigner
{
    public static function sign(string $secret, string $timestamp, string $body): string
    {
        $payload = $timestamp.'.'.$body;

        return hash_hmac('sha256', $payload, $secret);
    }

    public static function headerValue(string $timestamp, string $signature): string
    {
        return 't='.$timestamp.',v1='.$signature;
    }

    /**
     * @return array{timestamp: string|null, signature: string|null}
     */
    public static function parseHeader(?string $header): array
    {
        $timestamp = null;
        $signature = null;

        if ($header === null || $header === '') {
            return compact('timestamp', 'signature');
        }

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signature = $value;
            }
        }

        return compact('timestamp', 'signature');
    }

    public static function verify(
        string $secret,
        string $body,
        ?string $header,
        int $toleranceSeconds = 300,
        ?int $now = null,
    ): bool {
        $parsed = self::parseHeader($header);
        if ($parsed['timestamp'] === null || $parsed['signature'] === null) {
            return false;
        }

        $now ??= time();
        $ts = (int) $parsed['timestamp'];

        if (abs($now - $ts) > $toleranceSeconds) {
            return false;
        }

        $expected = self::sign($secret, (string) $ts, $body);

        return hash_equals($expected, $parsed['signature']);
    }
}
