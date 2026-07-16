<?php

declare(strict_types=1);

namespace Hekal\HookRelay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delivery_id
 * @property int $attempt_number
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string|null $error
 * @property int|null $duration_ms
 */
class WebhookAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'hookrelay_attempts';

    protected $fillable = [
        'delivery_id',
        'attempt_number',
        'status_code',
        'response_body',
        'error',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WebhookDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WebhookDelivery::class, 'delivery_id');
    }
}
