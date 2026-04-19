<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted TubaPay checkout selection.
 *
 * @property int $id
 * @property string $external_ref
 * @property int $installments
 * @property array<int, string> $consents_accepted
 * @property string|null $return_url
 * @property array<string, mixed>|null $metadata
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TubaPayCheckoutSelection extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'external_ref',
        'installments',
        'consents_accepted',
        'return_url',
        'metadata',
        'expires_at',
    ];

    public function getTable(): string
    {
        return config('tubapay.database.checkout_selections_table', 'tubapay_checkout_selections');
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
            'installments' => 'integer',
            'consents_accepted' => 'array',
            'metadata' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
