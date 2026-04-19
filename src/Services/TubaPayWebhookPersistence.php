<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Services;

use Core45\LaravelTubaPay\Models\TubaPayPayment;
use Core45\LaravelTubaPay\Models\TubaPayRecurringRequest;
use Core45\LaravelTubaPay\Models\TubaPayTransaction;
use Core45\TubaPay\DTO\Webhook\InvoicePayload;
use Core45\TubaPay\DTO\Webhook\InvoicePosition;
use Core45\TubaPay\DTO\Webhook\PaymentPayload;
use Core45\TubaPay\DTO\Webhook\StatusChangedPayload;
use Core45\TubaPay\DTO\Webhook\WebhookPayload;
use Illuminate\Support\Carbon;

final class TubaPayWebhookPersistence
{
    public function persist(WebhookPayload $payload): ?TubaPayRecurringRequest
    {
        if (! (bool) config('tubapay.database.track_transactions', true)) {
            return null;
        }

        if ($payload instanceof StatusChangedPayload) {
            $this->persistStatusChanged($payload);

            return null;
        }

        if ($payload instanceof PaymentPayload) {
            $this->persistPayment($payload);

            return null;
        }

        if ($payload instanceof InvoicePayload) {
            return $this->persistRecurringRequest($payload);
        }

        return null;
    }

    private function persistStatusChanged(StatusChangedPayload $payload): void
    {
        $externalRef = $payload->externalRef;

        if ($externalRef === null) {
            return;
        }

        $transaction = TubaPayTransaction::findByExternalRef($externalRef);

        if ($transaction === null) {
            $transaction = new TubaPayTransaction;
            $transaction->external_ref = $externalRef;
            $transaction->agreement_number = $payload->agreementNumber;
            $transaction->amount = $payload->agreementNetValue;
            $transaction->currency = 'PLN';
            $transaction->customer_name = trim(
                $payload->customer->firstName.' '.$payload->customer->lastName
            );
            $transaction->customer_email = $payload->customer->email;
            $transaction->customer_phone = $payload->customer->phone;
            $transaction->status = $payload->agreementStatus->value;
            $transaction->origin_company = $payload->originCompany;
            $transaction->template_name = $payload->templateName;
            $transaction->last_webhook_payload = $payload->rawPayload;
            $transaction->status_changed_at = Carbon::now();
            $transaction->save();

            return;
        }

        $transaction->updateFromWebhook($payload);
    }

    private function persistPayment(PaymentPayload $payload): void
    {
        TubaPayPayment::query()->create([
            'external_ref' => $payload->externalRef,
            'agreement_number' => $payload->agreementNumber,
            'payment_title' => $payload->paymentTitle,
            'payment_amount' => $payload->paymentAmount,
            'payment_date' => $payload->paymentDate?->format('Y-m-d'),
            'payload' => $payload->rawPayload,
        ]);
    }

    private function persistRecurringRequest(InvoicePayload $payload): TubaPayRecurringRequest
    {
        $firstRecord = null;

        foreach ($payload->requestPositions as $position) {
            $record = $this->persistRecurringPosition($payload, $position);

            if ($firstRecord === null) {
                $firstRecord = $record;
            }
        }

        if ($firstRecord !== null) {
            return $firstRecord;
        }

        return TubaPayRecurringRequest::query()->create([
            'external_ref' => $payload->externalRef,
            'agreement_number' => $payload->agreementNumber,
            'request_total_amount' => $payload->requestTotalAmount,
            'payload' => $payload->rawPayload,
        ]);
    }

    private function persistRecurringPosition(
        InvoicePayload $payload,
        InvoicePosition $position,
    ): TubaPayRecurringRequest {
        $values = [
            'external_ref' => $payload->externalRef,
            'agreement_number' => $payload->agreementNumber,
            'rate_number' => $position->rateNumber,
            'total_amount' => $position->totalAmount,
            'request_total_amount' => $payload->requestTotalAmount,
            'payload' => $payload->rawPayload,
        ];

        if ($position->paymentScheduleId === null) {
            return TubaPayRecurringRequest::query()->create($values);
        }

        return TubaPayRecurringRequest::query()->updateOrCreate(
            ['payment_schedule_id' => $position->paymentScheduleId],
            $values,
        );
    }
}
