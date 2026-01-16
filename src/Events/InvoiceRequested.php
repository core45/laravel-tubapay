<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Events;

use Core45\TubaPay\DTO\Webhook\InvoicePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when TubaPay requests a recurring invoice.
 *
 * This event is used for subscription/recurring payment scenarios
 * where TubaPay needs invoice information.
 */
final class InvoiceRequested
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly InvoicePayload $payload,
    ) {}

    /**
     * Get the external reference (your order ID).
     */
    public function getExternalRef(): ?string
    {
        return $this->payload->externalRef;
    }

    /**
     * Get the agreement number.
     */
    public function getAgreementNumber(): ?string
    {
        return $this->payload->agreementNumber;
    }

    /**
     * Get the first position from the invoice.
     */
    public function getFirstPosition(): ?\Core45\TubaPay\DTO\Webhook\InvoicePosition
    {
        return $this->payload->getFirstPosition();
    }

    /**
     * Get all positions from the invoice.
     *
     * @return list<\Core45\TubaPay\DTO\Webhook\InvoicePosition>
     */
    public function getPositions(): array
    {
        return $this->payload->requestPositions;
    }
}
