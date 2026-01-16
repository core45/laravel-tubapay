<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Http\Controllers;

use Core45\LaravelTubaPay\Events\InvoiceRequested;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Events\WebhookReceived;
use Core45\LaravelTubaPay\Models\TubaPayTransaction;
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

        // Track transaction in database if enabled
        $this->trackTransaction($webhookPayload);

        // Dispatch the generic webhook event
        WebhookReceived::dispatch($webhookPayload, $payload);

        // Dispatch specific events based on webhook type
        $this->dispatchSpecificEvent($webhookPayload);

        return new Response('OK', 200);
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
     * Track the transaction in the database.
     */
    private function trackTransaction(WebhookPayload $payload): void
    {
        /** @var bool $trackTransactions */
        $trackTransactions = config('tubapay.database.track_transactions', true);

        if (!$trackTransactions) {
            return;
        }

        // Only track status changes for now
        if (!$payload instanceof StatusChangedPayload) {
            return;
        }

        $externalRef = $payload->externalRef;
        if ($externalRef === null) {
            return;
        }

        $transaction = TubaPayTransaction::findByExternalRef($externalRef);

        if ($transaction === null) {
            // Create new transaction record
            $transaction = new TubaPayTransaction();
            $transaction->external_ref = $externalRef;
            $transaction->agreement_number = $payload->agreementNumber;
            $transaction->amount = $payload->agreementNetValue;
            $transaction->currency = 'PLN'; // TubaPay is PLN-based
            $transaction->customer_name = trim(
                $payload->customer->firstName.' '.$payload->customer->lastName
            );
            $transaction->customer_email = $payload->customer->email;
            $transaction->customer_phone = $payload->customer->phone;
            $transaction->status = $payload->agreementStatus->value;
            $transaction->origin_company = $payload->originCompany;
            $transaction->template_name = $payload->templateName;
            $transaction->last_webhook_payload = $payload->rawPayload;
            $transaction->status_changed_at = now();
            $transaction->save();
        } else {
            // Update existing transaction
            $transaction->updateFromWebhook($payload);
        }
    }

    /**
     * Dispatch the appropriate event based on webhook payload type.
     */
    private function dispatchSpecificEvent(WebhookPayload $payload): void
    {
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
        }
    }

    /**
     * Log webhook error.
     */
    private function logWebhookError(WebhookVerificationException $e, Request $request): void
    {
        /** @var bool $logWebhooks */
        $logWebhooks = config('tubapay.logging.log_webhooks', false);
        if (!$logWebhooks) {
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
        if (!$logWebhooks) {
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
