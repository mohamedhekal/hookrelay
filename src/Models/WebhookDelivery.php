<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Models;

use Hekal\HookRelay\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $endpoint_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string|null $idempotency_key
 * @property string $status
 * @property int $attempts_count
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $dead_lettered_at
 * @property string|null $last_error
 * @property-read WebhookEndpoint|null $endpoint
 */
class WebhookDelivery extends Model
{
    protected $table = 'hookrelay_deliveries';

    protected $fillable = [
        'uuid',
        'endpoint_id',
        'event_type',
        'payload',
        'idempotency_key',
        'status',
        'attempts_count',
        'next_attempt_at',
        'delivered_at',
        'dead_lettered_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts_count' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'dead_lettered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
            $delivery->status ??= DeliveryStatus::Pending->value;
        });
    }

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    /**
     * @return HasMany<WebhookAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookAttempt::class, 'delivery_id');
    }

    public function statusEnum(): DeliveryStatus
    {
        return DeliveryStatus::from($this->status);
    }

    public function markProcessing(): void
    {
        $this->forceFill(['status' => DeliveryStatus::Processing->value])->save();
    }

    public function markDelivered(): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Delivered->value,
            'delivered_at' => now(),
            'next_attempt_at' => null,
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error, ?\DateTimeInterface $nextAttemptAt): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Failed->value,
            'last_error' => $error,
            'next_attempt_at' => $nextAttemptAt,
        ])->save();
    }

    public function markDeadLettered(string $error): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::DeadLettered->value,
            'last_error' => $error,
            'dead_lettered_at' => now(),
            'next_attempt_at' => null,
        ])->save();
    }
}
