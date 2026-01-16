<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Events;

use Core45\TubaPay\DTO\Webhook\StatusChangedPayload;
use Core45\TubaPay\Enum\AgreementStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a transaction status changes.
 *
 * This is the most important webhook event - use it to:
 * - Mark orders as paid when status is 'accepted'
 * - Cancel orders when status is 'rejected', 'canceled', etc.
 * - Track the full lifecycle of a TubaPay agreement
 */
final class TransactionStatusChanged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly StatusChangedPayload $payload,
    ) {}

    /**
     * Get the external reference (your order ID).
     */
    public function getExternalRef(): string
    {
        return $this->payload->externalRef ?? '';
    }

    /**
     * Get the agreement status.
     */
    public function getStatus(): AgreementStatus
    {
        return $this->payload->agreementStatus;
    }

    /**
     * Get the TubaPay agreement number.
     */
    public function getAgreementNumber(): ?string
    {
        return $this->payload->agreementNumber;
    }

    /**
     * Check if the payment was accepted (merchant will be paid).
     */
    public function isAccepted(): bool
    {
        return $this->payload->isAccepted();
    }

    /**
     * Check if the payment was rejected.
     */
    public function isRejected(): bool
    {
        return $this->payload->isRejected();
    }

    /**
     * Check if the status is still pending (waiting for customer action).
     */
    public function isPending(): bool
    {
        return $this->payload->agreementStatus->isPending();
    }

    /**
     * Check if this is a final status (no more changes expected).
     */
    public function isFinal(): bool
    {
        return $this->payload->agreementStatus->isFinal();
    }
}
