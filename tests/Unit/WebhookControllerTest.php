<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Events\InvoiceRequested;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\WebhookReceived;
use Core45\LaravelTubaPay\Http\Controllers\WebhookController;
use Core45\LaravelTubaPay\Models\TubaPayPayment;
use Core45\LaravelTubaPay\Models\TubaPayRecurringRequest;
use Core45\LaravelTubaPay\Models\TubaPayWebhookEvent;
use Core45\LaravelTubaPay\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

final class WebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('tubapay.verify_webhook_signatures', false);
    }

    #[Test]
    public function payment_webhook_creates_payment_record(): void
    {
        Event::fake();

        $response = $this->handlePayload($this->paymentPayload('payment-ref-1'));

        $payment = TubaPayPayment::query()->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($payment);
        $this->assertSame('ORDER-123', $payment->external_ref);
        $this->assertSame('AGR-456', $payment->agreement_number);
        $this->assertSame(1000.0, (float) $payment->payment_amount);
        Event::assertDispatched(WebhookReceived::class, 1);
        Event::assertDispatched(PaymentReceived::class, 1);
    }

    #[Test]
    public function recurring_request_webhook_creates_record_and_dispatches_alias_event(): void
    {
        Event::fake();

        $response = $this->handlePayload($this->recurringPayload('recurring-ref-1', 'schedule-123'));

        $record = TubaPayRecurringRequest::query()->first();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($record);
        $this->assertSame('ORDER-123', $record->external_ref);
        $this->assertSame('schedule-123', $record->payment_schedule_id);
        $this->assertSame(1, $record->rate_number);
        Event::assertDispatched(InvoiceRequested::class, 1);
        Event::assertDispatched(RecurringOrderRequested::class, 1);
    }

    #[Test]
    public function duplicate_payment_schedule_id_is_idempotent(): void
    {
        $this->handlePayload($this->recurringPayload('recurring-ref-1', 'schedule-123', 1));
        $this->handlePayload($this->recurringPayload('recurring-ref-2', 'schedule-123', 2));

        $record = TubaPayRecurringRequest::query()->first();

        $this->assertSame(1, TubaPayRecurringRequest::query()->count());
        $this->assertSame(2, $record?->rate_number);
    }

    #[Test]
    public function duplicate_processed_webhook_event_is_not_dispatched_twice(): void
    {
        Event::fake();
        $payload = $this->paymentPayload('payment-ref-1');

        $this->handlePayload($payload);
        $this->handlePayload($payload);

        $event = TubaPayWebhookEvent::query()->first();

        $this->assertSame(1, TubaPayPayment::query()->count());
        $this->assertSame(1, TubaPayWebhookEvent::query()->count());
        $this->assertSame(TubaPayWebhookEvent::STATUS_PROCESSED, $event?->status);
        Event::assertDispatched(WebhookReceived::class, 1);
        Event::assertDispatched(PaymentReceived::class, 1);
    }

    #[Test]
    public function tracking_can_be_disabled(): void
    {
        Event::fake();
        $this->app['config']->set('tubapay.database.track_transactions', false);

        $response = $this->handlePayload($this->paymentPayload('payment-ref-1'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, TubaPayPayment::query()->count());
        $this->assertSame(0, TubaPayWebhookEvent::query()->count());
        Event::assertDispatched(PaymentReceived::class, 1);
    }

    private function handlePayload(array $payload): Response
    {
        $request = Request::create(
            '/webhooks/tubapay',
            'POST',
            [],
            [],
            [],
            [],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        return $this->app->make(WebhookController::class)->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(string $commandRef): array
    {
        return $this->basePayload('TRANSACTION_MERCHANT_PAYMENT', $commandRef, [
            'agreementStatus' => 'accepted',
            'agreementNetValue' => 1000.0,
            'originCompany' => 'BACCA_PAY',
            'templateName' => 'template',
            'templateFileVersion' => '1.0',
            'externalRef' => 'ORDER-123',
            'agreementNumber' => 'AGR-456',
            'paymentTitle' => 'Payment for Order 123',
            'paymentAmount' => 1000.0,
            'paymentDate' => '2026-04-19',
            'beneficiaryAccountNumber' => '0011223344',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function recurringPayload(string $commandRef, string $paymentScheduleId, int $rateNumber = 1): array
    {
        return $this->basePayload('CUSTOMER_RECURRING_ORDER_REQUEST', $commandRef, [
            'agreementStatus' => 'accepted',
            'agreementNetValue' => 1000.0,
            'originCompany' => 'BACCA_PAY',
            'templateName' => 'template',
            'templateFileVersion' => '1.0',
            'externalRef' => 'ORDER-123',
            'agreementNumber' => 'AGR-456',
            'requestTotalAmount' => 250.0,
            'requestPositions' => [
                [
                    'paymentScheduleId' => $paymentScheduleId,
                    'rateNumber' => $rateNumber,
                    'totalAmount' => 250.0,
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @return array<string, mixed>
     */
    private function basePayload(string $commandType, string $commandRef, array $transaction): array
    {
        return [
            'metaData' => [
                'commandType' => $commandType,
                'commandRef' => $commandRef,
                'commandDateTime' => '2026-04-19T10:00:00Z',
                'commandCallbackUrl' => 'https://example.com/webhooks/tubapay',
                'commandCallbackType' => 'custom',
            ],
            'payload' => [
                'partner' => [
                    'tubapayPartnerId' => '703419',
                    'partnerName' => 'Test Partner',
                ],
                'customer' => [
                    'firstName' => 'Jan',
                    'lastName' => 'Kowalski',
                    'email' => 'jan@example.com',
                    'phone' => '519088975',
                    'street' => 'Testowa',
                    'zipCode' => '00-001',
                    'town' => 'Warszawa',
                ],
                'transaction' => $transaction,
            ],
        ];
    }
}
