<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted TubaPay merchant payment notification.
 *
 * @property int $id
 * @property string|null $external_ref
 * @property string|null $agreement_number
 * @property string|null $payment_title
 * @property float $payment_amount
 * @property Carbon|null $payment_date
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TubaPayPayment extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'external_ref',
        'agreement_number',
        'payment_title',
        'payment_amount',
        'payment_date',
        'payload',
    ];

    public function getTable(): string
    {
        return config('tubapay.database.payments_table', 'tubapay_payments');
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
            'payment_amount' => 'decimal:2',
            'payment_date' => 'date',
            'payload' => 'array',
        ];
    }
}
