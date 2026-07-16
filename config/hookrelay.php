<?php

declare(strict_types=1);

return [
    'route_prefix' => env('HOOKRELAY_PREFIX', 'hookrelay'),

    'inbound' => [
        'enabled' => env('HOOKRELAY_INBOUND', true),
        'middleware' => ['api'],
        /*
         * source => secret for HMAC verification (header X-HookRelay-Signature)
         */
        'sources' => [
            // 'stripe' => env('HOOKRELAY_STRIPE_SECRET'),
        ],
        'signature_header' => 'X-HookRelay-Signature',
        'timestamp_header' => 'X-HookRelay-Timestamp',
        'tolerance_seconds' => 300,
    ],

    'delivery' => [
        'signature_header' => 'X-HookRelay-Signature',
        'timestamp_header' => 'X-HookRelay-Timestamp',
        'event_header' => 'X-HookRelay-Event',
        'delivery_header' => 'X-HookRelay-Delivery',
        'user_agent' => 'HookRelay/0.1',
        'timeout' => 10,
        'default_max_attempts' => 5,
        'backoff' => [
            'base_seconds' => 30,
            'multiplier' => 2.0,
            'max_seconds' => 3600,
        ],
        'queue' => env('HOOKRELAY_QUEUE', 'default'),
    ],
];
