<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\TubaPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

final class UiRoutesEnabledTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('tubapay.ui.register_routes', true);
        $app['config']->set('tubapay.ui.routes_middleware', ['api']);
    }

    #[Test]
    public function ui_routes_register_when_enabled(): void
    {
        $this->assertTrue(Route::has('tubapay.ui.installments'));
        $this->assertTrue(Route::has('tubapay.ui.content.top-bar'));
        $this->assertTrue(Route::has('tubapay.ui.content.popup'));
        $this->assertTrue(Route::has('tubapay.ui.texts'));
    }

    #[Test]
    public function installments_route_validates_amount(): void
    {
        $this->getJson('/tubapay/installments')
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    #[Test]
    public function installments_route_returns_checkout_options(): void
    {
        $this->bindTubaPay(new MockHandler([
            $this->tokenResponse(),
            $this->offerResponse(),
            $this->textsResponse(),
        ]));

        $this->getJson('/tubapay/installments?amount=1200')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('recommendedInstallments', 12)
            ->assertJsonPath('installments.0.installments', 6)
            ->assertJsonPath('consents.0.type', 'RODO_BP');
    }

    #[Test]
    public function top_bar_route_returns_content(): void
    {
        $this->bindTubaPay(new MockHandler([
            $this->tokenResponse(),
            new Response(200, [], json_encode([
                'data' => [
                    'main_text' => 'Top text',
                    'button_text' => 'Button',
                    'button_text_mobile' => 'Mobile',
                ],
            ], JSON_THROW_ON_ERROR)),
        ]));

        $this->getJson('/tubapay/content/top-bar')
            ->assertOk()
            ->assertJsonPath('mainText', 'Top text');
    }

    private function bindTubaPay(MockHandler $mockHandler): void
    {
        $this->app->forgetInstance(TubaPay::class);
        $this->app->instance(TubaPay::class, TubaPay::create(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            webhookSecret: 'webhook-secret',
            environment: Environment::Test,
            httpClient: new Client(['handler' => HandlerStack::create($mockHandler)]),
        ));
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

    private function textsResponse(): Response
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
