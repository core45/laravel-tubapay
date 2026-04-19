<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Contracts\TubaPayOrderResolver;
use Core45\LaravelTubaPay\Contracts\TubaPayTransactable;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Listeners\DefaultHandlePaymentReceived;
use Core45\LaravelTubaPay\Listeners\DefaultHandleRecurringOrderRequested;
use Core45\LaravelTubaPay\Listeners\DefaultHandleTransactionAccepted;
use Core45\LaravelTubaPay\Listeners\DefaultHandleTransactionRejected;
use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\DTO\Webhook\InvoicePayload;
use Core45\TubaPay\DTO\Webhook\PaymentPayload;
use Core45\TubaPay\DTO\Webhook\StatusChangedPayload;
use PHPUnit\Framework\Attributes\Test;

final class DefaultListenersTest extends TestCase
{
    #[Test]
    public function accepted_listener_marks_transactable_as_accepted(): void
    {
        $order = new FakeTubaPayOrder;
        $this->bindResolver($order);
        $this->app['config']->set('tubapay.status_map.accepted', 'paid');

        $listener = new DefaultHandleTransactionAccepted($this->app, new TubaPayStatusMapper);
        $listener->handle(new TransactionStatusChanged($this->statusPayload('accepted')));

        $this->assertSame('AGR-456', $order->acceptedAgreementNumber);
        $this->assertSame('transaction_accepted', $order->events[0][0]);
        $this->assertStringContainsString('paid', $order->events[0][1]);
    }

    #[Test]
    public function rejected_listener_applies_mapped_status(): void
    {
        $order = new FakeTubaPayOrder;
        $this->bindResolver($order);
        $this->app['config']->set('tubapay.status_map.rejected', 'payment_failed');

        $listener = new DefaultHandleTransactionRejected($this->app, new TubaPayStatusMapper);
        $listener->handle(new TransactionStatusChanged($this->statusPayload('rejected')));

        $this->assertSame('payment_failed', $order->rejectedStatus);
        $this->assertSame('AGR-456', $order->rejectedAgreementNumber);
    }

    #[Test]
    public function listeners_are_noop_without_resolver_binding(): void
    {
        $listener = new DefaultHandleTransactionAccepted($this->app, new TubaPayStatusMapper);
        $listener->handle(new TransactionStatusChanged($this->statusPayload('accepted')));

        $this->assertTrue(true);
    }

    #[Test]
    public function payment_listener_records_payment_event(): void
    {
        $order = new FakeTubaPayOrder;
        $this->bindResolver($order);

        $listener = new DefaultHandlePaymentReceived($this->app);
        $listener->handle(new PaymentReceived($this->paymentPayload()));

        $this->assertSame('payment_received', $order->events[0][0]);
    }

    #[Test]
    public function recurring_listener_records_recurring_event(): void
    {
        $order = new FakeTubaPayOrder;
        $this->bindResolver($order);

        $listener = new DefaultHandleRecurringOrderRequested($this->app);
        $listener->handle(new RecurringOrderRequested($this->invoicePayload()));

        $this->assertSame('recurring_order_requested', $order->events[0][0]);
    }

    private function bindResolver(FakeTubaPayOrder $order): void
    {
        $this->app->instance(TubaPayOrderResolver::class, new FakeTubaPayOrderResolver($order));
    }

    private function statusPayload(string $status): StatusChangedPayload
    {
        return StatusChangedPayload::fromArray($this->basePayload('TRANSACTION_STATUS_CHANGED', [
            'agreementStatus' => $status,
            'agreementNetValue' => 1000.0,
            'originCompany' => 'BACCA_PAY',
            'templateName' => 'template',
            'templateFileVersion' => '1.0',
            'externalRef' => 'ORDER-123',
            'agreementNumber' => 'AGR-456',
        ]));
    }

    private function paymentPayload(): PaymentPayload
    {
        return PaymentPayload::fromArray($this->basePayload('TRANSACTION_MERCHANT_PAYMENT', [
            'agreementStatus' => 'accepted',
            'agreementNetValue' => 1000.0,
            'originCompany' => 'BACCA_PAY',
            'templateName' => 'template',
            'templateFileVersion' => '1.0',
            'externalRef' => 'ORDER-123',
            'agreementNumber' => 'AGR-456',
            'paymentAmount' => 1000.0,
            'paymentTitle' => 'Payment',
            'paymentDate' => '2026-04-19',
        ]));
    }

    private function invoicePayload(): InvoicePayload
    {
        return InvoicePayload::fromArray($this->basePayload('CUSTOMER_RECURRING_ORDER_REQUEST', [
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
                    'paymentScheduleId' => 'schedule-123',
                    'rateNumber' => 1,
                    'totalAmount' => 250.0,
                ],
            ],
        ]));
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @return array<string, mixed>
     */
    private function basePayload(string $commandType, array $transaction): array
    {
        return [
            'metaData' => [
                'commandType' => $commandType,
                'commandRef' => 'command-ref',
                'commandDateTime' => '2026-04-19T10:00:00Z',
                'commandCallbackUrl' => 'https://example.com/webhook',
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

final class FakeTubaPayOrderResolver implements TubaPayOrderResolver
{
    public function __construct(
        private readonly FakeTubaPayOrder $order,
    ) {}

    public function resolve(string $externalRef): ?TubaPayTransactable
    {
        return $externalRef === 'ORDER-123' ? $this->order : null;
    }
}

final class FakeTubaPayOrder implements TubaPayTransactable
{
    public ?string $acceptedAgreementNumber = null;

    public ?string $rejectedStatus = null;

    public ?string $rejectedAgreementNumber = null;

    /**
     * @var list<array{0: string, 1: string}>
     */
    public array $events = [];

    public function markTubaPayAccepted(string $agreementNumber): void
    {
        $this->acceptedAgreementNumber = $agreementNumber;
    }

    public function markTubaPayRejected(string $status, ?string $agreementNumber = null): void
    {
        $this->rejectedStatus = $status;
        $this->rejectedAgreementNumber = $agreementNumber;
    }

    public function recordTubaPayEvent(string $event, string $details): void
    {
        $this->events[] = [$event, $details];
    }

    public function isTubaPayPaid(): bool
    {
        return $this->acceptedAgreementNumber !== null;
    }
}
