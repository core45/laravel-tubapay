<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Events\InvoiceRequested;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Events\WebhookReceived;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\DTO\Webhook\InvoicePayload;
use Core45\TubaPay\DTO\Webhook\PaymentPayload;
use Core45\TubaPay\DTO\Webhook\StatusChangedPayload;
use Core45\TubaPay\Enum\AgreementStatus;
use PHPUnit\Framework\Attributes\Test;

final class EventsTest extends TestCase
{
    #[Test]
    public function webhook_received_event_has_payload_and_raw_data(): void
    {
        $statusPayload = $this->createStatusChangedPayload();
        $rawPayload = '{"test": "data"}';

        $event = new WebhookReceived($statusPayload, $rawPayload);

        $this->assertSame($statusPayload, $event->payload);
        $this->assertSame($rawPayload, $event->rawPayload);
        $this->assertSame('TRANSACTION_STATUS_CHANGED', $event->getType());
        $this->assertSame('ORDER-123', $event->getExternalRef());
    }

    #[Test]
    public function transaction_status_changed_event_provides_helpers(): void
    {
        $payload = $this->createStatusChangedPayload();

        $event = new TransactionStatusChanged($payload);

        $this->assertSame('ORDER-123', $event->getExternalRef());
        $this->assertSame(AgreementStatus::Accepted, $event->getStatus());
        $this->assertSame('AGR-456', $event->getAgreementNumber());
        $this->assertTrue($event->isAccepted());
        $this->assertFalse($event->isRejected());
        $this->assertFalse($event->isPending());
        // Accepted is not final - agreement can still transition to Repaid -> Closed
        $this->assertFalse($event->isFinal());
    }

    #[Test]
    public function payment_received_event_provides_payment_details(): void
    {
        $payload = $this->createPaymentPayload();

        $event = new PaymentReceived($payload);

        $this->assertSame('ORDER-123', $event->getExternalRef());
        $this->assertSame(1000.0, $event->getAmount());
        $this->assertSame('Payment for Order 123', $event->getTitle());
        $this->assertSame('2024-01-15', $event->getPaymentDate());
    }

    #[Test]
    public function invoice_requested_event_provides_invoice_details(): void
    {
        $payload = $this->createInvoicePayload();

        $event = new InvoiceRequested($payload);

        $this->assertSame('ORDER-123', $event->getExternalRef());
        $this->assertSame('AGR-789', $event->getAgreementNumber());
        $this->assertIsArray($event->getPositions());
    }

    #[Test]
    public function recurring_order_requested_event_provides_schedule_details(): void
    {
        $payload = $this->createInvoicePayload();

        $event = new RecurringOrderRequested($payload);

        $this->assertSame('ORDER-123', $event->getExternalRef());
        $this->assertSame('AGR-789', $event->getAgreementNumber());
        $this->assertSame('schedule-123', $event->getPaymentScheduleId());
    }

    private function createStatusChangedPayload(): StatusChangedPayload
    {
        return StatusChangedPayload::fromArray([
            'metaData' => [
                'commandType' => 'TRANSACTION_STATUS_CHANGED',
                'commandRef' => 'ref-123',
                'commandDateTime' => '2024-01-15T10:00:00Z',
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
                'transaction' => [
                    'agreementStatus' => 'accepted',
                    'agreementNetValue' => 1000.0,
                    'originCompany' => 'BACCA_PAY',
                    'templateName' => 'test_template',
                    'templateFileVersion' => '1.0',
                    'externalRef' => 'ORDER-123',
                    'agreementNumber' => 'AGR-456',
                ],
            ],
        ]);
    }

    private function createPaymentPayload(): PaymentPayload
    {
        return PaymentPayload::fromArray([
            'metaData' => [
                'commandType' => 'TRANSACTION_MERCHANT_PAYMENT',
                'commandRef' => 'ref-123',
                'commandDateTime' => '2024-01-15T10:00:00Z',
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
                'transaction' => [
                    'agreementStatus' => 'accepted',
                    'agreementNetValue' => 1000.0,
                    'originCompany' => 'BACCA_PAY',
                    'templateName' => 'test_template',
                    'templateFileVersion' => '1.0',
                    'externalRef' => 'ORDER-123',
                    'agreementNumber' => 'AGR-456',
                    'paymentTitle' => 'Payment for Order 123',
                    'paymentAmount' => 1000.0,
                    'paymentDate' => '2024-01-15',
                ],
            ],
        ]);
    }

    private function createInvoicePayload(): InvoicePayload
    {
        return InvoicePayload::fromArray([
            'metaData' => [
                'commandType' => 'CUSTOMER_RECURRING_ORDER_REQUEST',
                'commandRef' => 'ref-123',
                'commandDateTime' => '2024-01-15T10:00:00Z',
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
                'transaction' => [
                    'agreementStatus' => 'accepted',
                    'agreementNetValue' => 1000.0,
                    'originCompany' => 'BACCA_PAY',
                    'templateName' => 'test_template',
                    'templateFileVersion' => '1.0',
                    'externalRef' => 'ORDER-123',
                    'agreementNumber' => 'AGR-789',
                    'requestTotalAmount' => 500.0,
                    'requestPositions' => [
                        [
                            'paymentScheduleId' => 'schedule-123',
                            'rateNumber' => 1,
                            'totalAmount' => 500.0,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
