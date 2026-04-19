<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Services\EloquentCheckoutSelectionStore;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\DTO\CheckoutSelection;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\TubaPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

final class CommandsTest extends TestCase
{
    #[Test]
    public function check_connection_command_returns_success_when_credentials_authenticate(): void
    {
        $this->app->instance(TubaPay::class, TubaPay::create(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            webhookSecret: 'webhook-secret',
            environment: Environment::Test,
            httpClient: new Client(['handler' => HandlerStack::create(new MockHandler([
                $this->tokenResponse(),
            ]))]),
        ));

        $this->artisan('tubapay:check-connection')
            ->expectsOutput('Authorization successful.')
            ->assertExitCode(0);
    }

    #[Test]
    public function prune_selections_command_prunes_expired_selections(): void
    {
        $store = new EloquentCheckoutSelectionStore;
        $store->put('EXPIRED', new CheckoutSelection(installments: 6), Carbon::now()->subMinute());

        $this->artisan('tubapay:prune-selections')
            ->expectsOutput('Pruned 1 expired TubaPay checkout selection(s).')
            ->assertExitCode(0);
    }

    private function tokenResponse(): Response
    {
        return new Response(200, [], json_encode([
            'token' => 'token',
            'refreshToken' => 'refresh',
            'expires' => date('Y-m-d H:i:s', time() + 86400),
        ], JSON_THROW_ON_ERROR));
    }
}
