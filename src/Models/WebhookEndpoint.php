<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property array<int, string>|null $events
 * @property bool $is_active
 * @property int $max_attempts
 * @property array<string, string>|null $headers
 */
class WebhookEndpoint extends Model
{
    protected $table = 'hookrelay_endpoints';

    protected $fillable = [
        'uuid',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'max_attempts',
        'headers',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'is_active' => 'boolean',
            'max_attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $endpoint): void {
            $endpoint->uuid ??= (string) Str::uuid();
            $endpoint->secret ??= Str::random(40);
            $endpoint->max_attempts ??= (int) config('hookrelay.delivery.default_max_attempts', 5);
        });
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function accepts(string $eventType): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->events === null || $this->events === []) {
            return true;
        }

        return in_array($eventType, $this->events, true) || in_array('*', $this->events, true);
    }
}
