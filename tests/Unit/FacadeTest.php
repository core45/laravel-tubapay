<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Facades\TubaPay;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\Api\OfferApi;
use Core45\TubaPay\Api\TransactionApi;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\Http\TubaPayClient;
use Core45\TubaPay\Security\SignatureVerifier;
use PHPUnit\Framework\Attributes\Test;

final class FacadeTest extends TestCase
{
    #[Test]
    public function it_resolves_to_tubapay_instance(): void
    {
        $this->assertInstanceOf(\Core45\TubaPay\TubaPay::class, TubaPay::getFacadeRoot());
    }

    #[Test]
    public function it_returns_offer_api(): void
    {
        $this->assertInstanceOf(OfferApi::class, TubaPay::offers());
    }

    #[Test]
    public function it_returns_transaction_api(): void
    {
        $this->assertInstanceOf(TransactionApi::class, TubaPay::transactions());
    }

    #[Test]
    public function it_returns_client(): void
    {
        $this->assertInstanceOf(TubaPayClient::class, TubaPay::getClient());
    }

    #[Test]
    public function it_returns_signature_verifier(): void
    {
        $this->assertInstanceOf(SignatureVerifier::class, TubaPay::getSignatureVerifier());
    }

    #[Test]
    public function it_returns_environment(): void
    {
        $this->assertInstanceOf(Environment::class, TubaPay::getEnvironment());
    }
}
