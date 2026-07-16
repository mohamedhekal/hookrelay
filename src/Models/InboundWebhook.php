<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $source
 * @property string|null $event_type
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $headers
 * @property bool $signature_valid
 * @property string|null $idempotency_key
 * @property Carbon|null $processed_at
 */
class InboundWebhook extends Model
{
    protected $table = 'hookrelay_inbounds';

    protected $fillable = [
        'uuid',
        'source',
        'event_type',
        'payload',
        'headers',
        'signature_valid',
        'idempotency_key',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $inbound): void {
            $inbound->uuid ??= (string) Str::uuid();
        });
    }
}
