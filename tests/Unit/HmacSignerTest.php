<?php

declare(strict_types=1);

use Hekal\HookRelay\Support\HmacSigner;

it('signs and verifies payloads', function () {
    $timestamp = (string) time();
    $body = '{"ok":true}';
    $signature = HmacSigner::sign('secret', $timestamp, $body);
    $header = HmacSigner::headerValue($timestamp, $signature);

    expect(HmacSigner::verify('secret', $body, $header))->toBeTrue()
        ->and(HmacSigner::verify('wrong', $body, $header))->toBeFalse();
});

it('rejects expired timestamps', function () {
    $timestamp = (string) (time() - 10_000);
    $body = '{}';
    $signature = HmacSigner::sign('secret', $timestamp, $body);
    $header = HmacSigner::headerValue($timestamp, $signature);

    expect(HmacSigner::verify('secret', $body, $header, 300))->toBeFalse();
});
