<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Http\Middleware;

use Closure;
use Core45\TubaPay\Exception\WebhookVerificationException;
use Core45\TubaPay\Security\SignatureVerifier;
use Core45\TubaPay\TubaPay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware to verify TubaPay webhook signatures.
 *
 * This middleware validates the X-TUBAPAY-CHECKSUM header against
 * the request payload using HMAC-SHA512 signature verification.
 */
final class VerifyTubaPaySignature
{
    public function __construct(
        private readonly TubaPay $tubaPay,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): SymfonyResponse  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $payload = $request->getContent();
        $signature = $request->header(SignatureVerifier::HEADER_NAME);

        try {
            $this->tubaPay->getSignatureVerifier()->verify(
                $payload,
                is_string($signature) ? $signature : ''
            );
        } catch (WebhookVerificationException $e) {
            if (config('tubapay.logging.log_webhooks', false)) {
                Log::channel(config('tubapay.logging.channel'))
                    ->warning('TubaPay webhook signature verification failed', [
                        'error' => $e->getMessage(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
            }

            return new Response('Invalid signature', 401);
        }

        return $next($request);
    }
}
