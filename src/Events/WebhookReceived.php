<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Events;

use Core45\TubaPay\DTO\Webhook\WebhookPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when any TubaPay webhook is received.
 *
 * This is the base event fired for all webhook types.
 * Listen to this event to handle all webhooks in one place.
 */
final class WebhookReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly WebhookPayload $payload,
        public readonly string $rawPayload,
    ) {}

    /**
     * Get the webhook command type from the payload.
     */
    public function getType(): string
    {
        return $this->payload->commandType;
    }

    /**
     * Get the external reference (order ID) from the payload.
     */
    public function getExternalRef(): ?string
    {
        return $this->payload->externalRef;
    }
}
