<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Events;

use Core45\TubaPay\DTO\Webhook\PaymentPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment notification is received from TubaPay.
 *
 * This event indicates that TubaPay has made a payment to the merchant.
 */
final class PaymentReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly PaymentPayload $payload,
    ) {}

    /**
     * Get the external reference (your order ID).
     */
    public function getExternalRef(): ?string
    {
        return $this->payload->externalRef;
    }

    /**
     * Get the payment amount.
     */
    public function getAmount(): float
    {
        return $this->payload->paymentAmount;
    }

    /**
     * Get the payment title/description.
     */
    public function getTitle(): string
    {
        return $this->payload->paymentTitle;
    }

    /**
     * Get the payment date.
     */
    public function getPaymentDate(): ?string
    {
        return $this->payload->paymentDate?->format('Y-m-d');
    }
}
