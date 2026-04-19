<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Services;

use Core45\LaravelTubaPay\Models\TubaPayWebhookEvent;
use Core45\TubaPay\DTO\Webhook\WebhookPayload;
use Illuminate\Support\Carbon;

final class TubaPayWebhookEventStore
{
    public function isEnabled(): bool
    {
        return (bool) config('tubapay.database.track_transactions', true)
            && (bool) config('tubapay.webhook.idempotency.enabled', true);
    }

    public function begin(WebhookPayload $payload, string $rawPayload): ?TubaPayWebhookEvent
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $eventId = $this->eventId($payload, $rawPayload);
        $payloadHash = hash('sha256', $rawPayload);

        /** @var TubaPayWebhookEvent|null $event */
        $event = TubaPayWebhookEvent::query()
            ->where('event_id', $eventId)
            ->first();

        if ($event === null) {
            $event = TubaPayWebhookEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => $payload->commandType,
                'status' => TubaPayWebhookEvent::STATUS_RECEIVED,
                'attempts' => 0,
                'payload_hash' => $payloadHash,
                'received_at' => Carbon::now(),
            ]);
        }

        if (! $this->canProcess($event)) {
            return null;
        }

        $event->forceFill([
            'event_type' => $payload->commandType,
            'payload_hash' => $payloadHash,
        ]);

        $event->markProcessing();

        return $event;
    }

    public function markProcessed(?TubaPayWebhookEvent $event): void
    {
        $event?->markProcessed();
    }

    public function markFailed(?TubaPayWebhookEvent $event, string $error): void
    {
        $event?->markFailed($error);
    }

    private function canProcess(TubaPayWebhookEvent $event): bool
    {
        if ($event->status === TubaPayWebhookEvent::STATUS_PROCESSED) {
            return false;
        }

        if ($event->status === TubaPayWebhookEvent::STATUS_FAILED) {
            return $event->attempts < $this->maxAttempts();
        }

        if ($event->status === TubaPayWebhookEvent::STATUS_PROCESSING) {
            return $this->leaseExpired($event->processing_started_at);
        }

        if ($event->status === TubaPayWebhookEvent::STATUS_RECEIVED && $event->attempts > 0) {
            return $this->leaseExpired($event->received_at);
        }

        return true;
    }

    private function leaseExpired(?Carbon $timestamp): bool
    {
        if ($timestamp === null) {
            return true;
        }

        return $timestamp->copy()->addMinutes($this->leaseMinutes())->isPast();
    }

    private function leaseMinutes(): int
    {
        return max(1, (int) config('tubapay.webhook.idempotency.lease_minutes', 5));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('tubapay.webhook.idempotency.max_attempts', 5));
    }

    private function eventId(WebhookPayload $payload, string $rawPayload): string
    {
        if ($payload->commandType !== '' && $payload->commandRef !== '') {
            return $payload->commandType.':'.$payload->commandRef;
        }

        return hash('sha256', $rawPayload);
    }
}
