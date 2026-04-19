<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Events;

use Core45\LaravelTubaPay\Models\TubaPayRecurringRequest;
use Core45\TubaPay\DTO\Webhook\InvoicePayload;
use Core45\TubaPay\DTO\Webhook\InvoicePosition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when TubaPay requests creation of a recurring/monthly order.
 */
final class RecurringOrderRequested
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly InvoicePayload $payload,
        public readonly ?TubaPayRecurringRequest $record = null,
    ) {}

    public function getExternalRef(): ?string
    {
        return $this->payload->externalRef;
    }

    public function getAgreementNumber(): ?string
    {
        return $this->payload->agreementNumber;
    }

    public function getFirstPosition(): ?InvoicePosition
    {
        return $this->payload->getFirstPosition();
    }

    public function getPaymentScheduleId(): ?string
    {
        return $this->getFirstPosition()?->paymentScheduleId;
    }
}
