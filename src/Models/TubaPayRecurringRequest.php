<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted TubaPay recurring order request.
 *
 * @property int $id
 * @property string|null $external_ref
 * @property string|null $agreement_number
 * @property string|null $payment_schedule_id
 * @property int|null $rate_number
 * @property float|null $total_amount
 * @property float $request_total_amount
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TubaPayRecurringRequest extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'external_ref',
        'agreement_number',
        'payment_schedule_id',
        'rate_number',
        'total_amount',
        'request_total_amount',
        'payload',
        'processed_at',
    ];

    public function getTable(): string
    {
        return config('tubapay.database.recurring_requests_table', 'tubapay_recurring_requests');
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
            'rate_number' => 'integer',
            'total_amount' => 'decimal:2',
            'request_total_amount' => 'decimal:2',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
