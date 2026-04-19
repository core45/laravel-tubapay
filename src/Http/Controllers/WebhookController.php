<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Http\Controllers;

use Core45\LaravelTubaPay\Events\InvoiceRequested;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Events\WebhookReceived;
use Core45\LaravelTubaPay\Models\TubaPayRecurringRequest;
use Core45\LaravelTubaPay\Services\TubaPayWebhookEventStore;
use Core45\LaravelTubaPay\Services\TubaPayWebhookPersistence;
use Core45\TubaPay\DTO\Webhook\InvoicePayload;
use Core45\TubaPay\DTO\Webhook\PaymentPayload;
use Core45\TubaPay\DTO\Webhook\StatusChangedPayload;
use Core45\TubaPay\DTO\Webhook\WebhookPayload;
use Core45\TubaPay\Exception\WebhookVerificationException;
use Core45\TubaPay\TubaPay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Controller for handling TubaPay webhooks.
 *
 * This controller optionally verifies the webhook signature, parses the payload,
 * tracks transactions in the database, and dispatches appropriate Laravel events.
 */
final class WebhookController extends Controller
{
    public function __construct(
        private readonly TubaPay $tubaPay,
        private readonly TubaPayWebhookEventStore $webhookEvents,
        private readonly TubaPayWebhookPersistence $webhookPersistence,
    ) {}

    /**
     * Handle incoming TubaPay webhook.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-TUBAPAY-CHECKSUM');

        try {
            $webhookPayload = $this->parseAndVerifyPayload(
                $payload,
                is_string($signature) ? $signature : null
            );
        } catch (WebhookVerificationException $e) {
            $this->logWebhookError($e, $request);

            return new Response('Invalid webhook', 400);
        }

        $this->logWebhookReceived($webhookPayload->commandType, $payload);

        $webhookEvent = $this->webhookEvents->begin($webhookPayload, $payload);

        if ($this->webhookEvents->isEnabled() && $webhookEvent === null) {
            return new Response('OK', 200);
        }

        try {
            $recurringRequest = $this->webhookPersistence->persist($webhookPayload);

            WebhookReceived::dispatch($webhookPayload, $payload);

            $this->dispatchSpecificEvent($webhookPayload, $recurringRequest);

            $this->webhookEvents->markProcessed($webhookEvent);

            return new Response('OK', 200);
        } catch (Throwable $e) {
            $this->webhookEvents->markFailed($webhookEvent, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Parse and optionally verify the webhook payload.
     *
     * @throws WebhookVerificationException
     */
    private function parseAndVerifyPayload(string $payload, ?string $signature): WebhookPayload
    {
        /** @var bool $verifySignatures */
        $verifySignatures = config('tubapay.verify_webhook_signatures', true);

        if ($verifySignatures) {
            return $this->tubaPay->verifyAndParseWebhook($payload, $signature);
        }

        // Skip verification - just parse the payload
        return $this->tubaPay->parseWebhook($payload);
    }

    /**
     * Dispatch the appropriate event based on webhook payload type.
     */
    private function dispatchSpecificEvent(
        WebhookPayload $payload,
        ?TubaPayRecurringRequest $recurringRequest,
    ): void {
        if ($payload instanceof StatusChangedPayload) {
            TransactionStatusChanged::dispatch($payload);

            return;
        }

        if ($payload instanceof PaymentPayload) {
            PaymentReceived::dispatch($payload);

            return;
        }

        if ($payload instanceof InvoicePayload) {
            InvoiceRequested::dispatch($payload);
            RecurringOrderRequested::dispatch($payload, $recurringRequest);
        }
    }

    /**
     * Log webhook error.
     */
    private function logWebhookError(WebhookVerificationException $e, Request $request): void
    {
        /** @var bool $logWebhooks */
        $logWebhooks = config('tubapay.logging.log_webhooks', false);
        if (! $logWebhooks) {
            return;
        }

        /** @var string|null $channel */
        $channel = config('tubapay.logging.channel');

        Log::channel($channel)
            ->error('TubaPay webhook verification failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
    }

    /**
     * Log successful webhook receipt.
     */
    private function logWebhookReceived(string $type, string $payload): void
    {
        /** @var bool $logWebhooks */
        $logWebhooks = config('tubapay.logging.log_webhooks', false);
        if (! $logWebhooks) {
            return;
        }

        /** @var string|null $channel */
        $channel = config('tubapay.logging.channel');

        Log::channel($channel)
            ->info('TubaPay webhook received', [
                'type' => $type,
                'payload' => json_decode($payload, true),
            ]);
    }
}
