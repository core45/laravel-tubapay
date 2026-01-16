<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Facades;

use Core45\TubaPay\Api\OfferApi;
use Core45\TubaPay\Api\TransactionApi;
use Core45\TubaPay\DTO\Webhook\WebhookPayload;
use Core45\TubaPay\Enum\Environment;
use Core45\TubaPay\Http\TubaPayClient;
use Core45\TubaPay\Security\SignatureVerifier;
use Illuminate\Support\Facades\Facade;

/**
 * TubaPay Facade.
 *
 * @method static OfferApi offers()
 * @method static TransactionApi transactions()
 * @method static WebhookPayload verifyAndParseWebhook(string $payload, ?string $signature)
 * @method static WebhookPayload parseWebhook(string $payload)
 * @method static TubaPayClient getClient()
 * @method static SignatureVerifier getSignatureVerifier()
 * @method static Environment getEnvironment()
 *
 * @see \Core45\TubaPay\TubaPay
 */
final class TubaPay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Core45\TubaPay\TubaPay::class;
    }
}
