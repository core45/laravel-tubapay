<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Models;

use Core45\TubaPay\DTO\Webhook\StatusChangedPayload;
use Core45\TubaPay\Enum\AgreementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TubaPay Transaction Model.
 *
 * Tracks the lifecycle of TubaPay payment transactions.
 *
 * @property int $id
 * @property string $external_ref
 * @property string|null $agreement_number
 * @property string|null $transaction_link
 * @property string $status
 * @property string|null $previous_status
 * @property \Illuminate\Support\Carbon|null $status_changed_at
 * @property float $amount
 * @property string $currency
 * @property int|null $installments
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property string|null $customer_name
 * @property string|null $origin_company
 * @property string|null $template_name
 * @property array<string, mixed>|null $last_webhook_payload
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class TubaPayTransaction extends Model
{
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'external_ref',
        'agreement_number',
        'transaction_link',
        'status',
        'previous_status',
        'status_changed_at',
        'amount',
        'currency',
        'installments',
        'customer_email',
        'customer_phone',
        'customer_name',
        'origin_company',
        'template_name',
        'last_webhook_payload',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('tubapay.database.table', 'tubapay_transactions');
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
            'amount' => 'decimal:2',
            'installments' => 'integer',
            'status_changed_at' => 'datetime',
            'last_webhook_payload' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the agreement status as an enum.
     */
    public function getAgreementStatus(): AgreementStatus
    {
        return AgreementStatus::from($this->status);
    }

    /**
     * Check if the transaction is in a pending state.
     */
    public function isPending(): bool
    {
        return $this->getAgreementStatus()->isPending();
    }

    /**
     * Check if the transaction was successful (will be paid).
     */
    public function isSuccessful(): bool
    {
        return $this->getAgreementStatus()->isSuccessful();
    }

    /**
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->getAgreementStatus()->isFailed();
    }

    /**
     * Check if the transaction is in a final state.
     */
    public function isFinal(): bool
    {
        return $this->getAgreementStatus()->isFinal();
    }

    /**
     * Update the transaction status from a webhook payload.
     */
    public function updateFromWebhook(StatusChangedPayload $payload): void
    {
        $this->previous_status = $this->status;
        $this->status = $payload->agreementStatus->value;
        $this->status_changed_at = now();
        $this->agreement_number = $payload->agreementNumber ?? $this->agreement_number;
        $this->origin_company = $payload->originCompany ?? $this->origin_company;
        $this->template_name = $payload->templateName ?? $this->template_name;
        $this->last_webhook_payload = $payload->rawPayload;

        $this->save();
    }

    /**
     * Find a transaction by external reference.
     */
    public static function findByExternalRef(string $externalRef): ?self
    {
        /** @var self|null */
        return static::query()->where('external_ref', $externalRef)->first();
    }

    /**
     * Scope a query to only include pending transactions.
     *
     * @param Builder<TubaPayTransaction> $query
     * @return Builder<TubaPayTransaction>
     */
    public function scopePending(Builder $query): Builder
    {
        /** @var Builder<TubaPayTransaction> */
        return $query->whereIn('status', [
            AgreementStatus::Draft->value,
            AgreementStatus::Registered->value,
            AgreementStatus::Signed->value,
        ]);
    }

    /**
     * Scope a query to only include successful transactions.
     *
     * @param Builder<TubaPayTransaction> $query
     * @return Builder<TubaPayTransaction>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        /** @var Builder<TubaPayTransaction> */
        return $query->whereIn('status', [
            AgreementStatus::Accepted->value,
            AgreementStatus::Repaid->value,
            AgreementStatus::Closed->value,
        ]);
    }

    /**
     * Scope a query to only include failed transactions.
     *
     * @param Builder<TubaPayTransaction> $query
     * @return Builder<TubaPayTransaction>
     */
    public function scopeFailed(Builder $query): Builder
    {
        /** @var Builder<TubaPayTransaction> */
        return $query->whereIn('status', [
            AgreementStatus::Rejected->value,
            AgreementStatus::Canceled->value,
            AgreementStatus::Terminated->value,
            AgreementStatus::Withdrew->value,
        ]);
    }

    /**
     * Scope a query to filter by customer email.
     *
     * @param Builder<TubaPayTransaction> $query
     * @return Builder<TubaPayTransaction>
     */
    public function scopeForCustomer(Builder $query, string $email): Builder
    {
        /** @var Builder<TubaPayTransaction> */
        return $query->where('customer_email', $email);
    }
}
