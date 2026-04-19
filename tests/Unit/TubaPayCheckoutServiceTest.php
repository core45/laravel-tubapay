<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Models\TubaPayTransaction;
use Core45\LaravelTubaPay\Services\EloquentCheckoutSelectionStore;
use Core45\LaravelTubaPay\Services\TubaPayCheckoutService;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\DTO\CheckoutSelection;
use Core45\TubaPay\DTO\Customer;
use Core45\TubaPay\DTO\OrderItem;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\TubaPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;

final class TubaPayCheckoutServiceTest extends TestCase
{
    #[Test]
    public function it_creates_transaction_from_stored_selection_and_tracks_it(): void
    {
        $history = [];
        $handlerStack = HandlerStack::create(new MockHandler([
            $this->tokenResponse(),
            $this->transactionResponse(),
        ]));
        $handlerStack->push(Middleware::history($history));

        $store = new EloquentCheckoutSelectionStore;
        $store->put(
            'ORDER-123',
            new CheckoutSelection(
                installments: 12,
                acceptedConsents: ['RODO_BP'],
                returnUrl: 'https://example.com/return',
            ),
        );

        $service = new TubaPayCheckoutService(
            $this->createTubaPay($handlerStack),
            $store,
        );

        $transaction = $service->createTransactionForOrder(
            externalRef: 'ORDER-123',
            customer: $this->customer(),
            items: [$this->item(1200.0)],
            callbackUrl: 'https://example.com/webhook',
        );

        /** @var Request $request */
        $request = $history[1]['request'];
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $tracked = TubaPayTransaction::findByExternalRef('ORDER-123');

        $this->assertSame('transaction-id', $transaction->transactionId);
        $this->assertSame(12, $payload['offer']['installmentsNumber']);
        $this->assertSame(['RODO_BP'], $payload['order']['acceptedConsents']);
        $this->assertSame('laravel', $payload['order']['source']);
        $this->assertNotNull($tracked);
        $this->assertSame('https://tubapay-test.example/transaction', $tracked->transaction_link);
        $this->assertSame(1200.0, (float) $tracked->amount);
        $this->assertSame(12, $tracked->installments);
        $this->assertSame(['RODO_BP'], $tracked->consents_accepted);
        $this->assertSame('stored', $tracked->selection_source);
        $this->assertNull($store->get('ORDER-123'));
    }

    private function createTubaPay(HandlerStack $handlerStack): TubaPay
    {
        return TubaPay::create(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            webhookSecret: 'webhook-secret',
            environment: Environment::Test,
            httpClient: new Client(['handler' => $handlerStack]),
        );
    }

    private function tokenResponse(): Response
    {
        return new Response(200, [], json_encode([
            'token' => 'token',
            'refreshToken' => 'refresh',
            'expires' => date('Y-m-d H:i:s', time() + 86400),
        ], JSON_THROW_ON_ERROR));
    }

    private function transactionResponse(): Response
    {
        return new Response(200, [], json_encode([
            'result' => [
                'response' => [
                    'referenceId' => 'ref-123',
                    'transaction' => [
                        'transactionId' => 'transaction-id',
                        'transactionLink' => 'https://tubapay-test.example/transaction',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function customer(): Customer
    {
        return new Customer(
            firstName: 'Jan',
            lastName: 'Kowalski',
            email: 'jan@example.com',
            phone: '519088975',
            street: 'Testowa',
            zipCode: '00-001',
            town: 'Warszawa',
        );
    }

    private function item(float $amount): OrderItem
    {
        return new OrderItem(
            name: 'Course',
            totalValue: $amount,
        );
    }
}
