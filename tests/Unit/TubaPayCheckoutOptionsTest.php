<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Services\TubaPayCheckoutOptions;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\TubaPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;

final class TubaPayCheckoutOptionsTest extends TestCase
{
    #[Test]
    public function it_builds_checkout_options_from_tubapay_offer(): void
    {
        $service = new TubaPayCheckoutOptions($this->createTubaPay(new MockHandler([
            $this->tokenResponse(),
            $this->offerResponse(),
            $this->uiTextResponse(),
        ])));

        $options = $service->forAmount(1200.0);

        $this->assertSame('Choose installments', $options->installmentTitle());
        $this->assertCount(2, $options->installments);
        $this->assertSame(12, $options->installments[1]->installments);
        $this->assertTrue($options->installments[1]->selected);
        $this->assertSame(100.0, $options->installments[1]->monthlyAmount);
        $this->assertCount(1, $options->consents);
        $this->assertSame('RODO_BP', $options->consents[0]->type);
        $this->assertTrue($options->consents[0]->required);
    }

    private function createTubaPay(MockHandler $mockHandler): TubaPay
    {
        return TubaPay::create(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            webhookSecret: 'webhook-secret',
            environment: Environment::Test,
            httpClient: new Client(['handler' => HandlerStack::create($mockHandler)]),
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

    private function offerResponse(): Response
    {
        return new Response(200, [], json_encode([
            'result' => [
                'response' => [
                    'referenceId' => 'ref-123',
                    'offer' => [
                        'type' => 'client',
                        'totalValue' => 1200,
                        'offerItems' => [
                            ['installmentsNumber' => 6],
                            ['installmentsNumber' => 12],
                        ],
                        'consents' => [
                            [
                                'type' => 'RODO_BP',
                                'title' => 'Consent text',
                                'optional' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function uiTextResponse(): Response
    {
        return new Response(200, [], json_encode([
            'result' => [
                'response' => [
                    'TP_CHOOSE_RATES_TITLE' => 'Choose installments',
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }
}
