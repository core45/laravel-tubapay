<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Idempotency state for a TubaPay webhook delivery.
 *
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property string $status
 * @property int $attempts
 * @property string $payload_hash
 * @property string|null $last_error
 * @property Carbon $received_at
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TubaPayWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'status',
        'attempts',
        'payload_hash',
        'last_error',
        'received_at',
        'processing_started_at',
        'processed_at',
        'failed_at',
    ];

    public function getTable(): string
    {
        return config('tubapay.database.webhook_events_table', 'tubapay_webhook_events');
    }

    public function getConnectionName(): ?string
    {
        return config('tubapay.database.connection');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $this->attempts + 1,
            'processing_started_at' => Carbon::now(),
            'last_error' => null,
            'failed_at' => null,
        ])->save();
    }

    public function markProcessed(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => Carbon::now(),
            'last_error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'failed_at' => Carbon::now(),
            'last_error' => $error,
        ])->save();
    }
}
