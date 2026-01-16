<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Http;

use Core45\TubaPay\Http\TokenStorageInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Laravel Cache-based token storage implementation.
 *
 * Uses Laravel's cache system to persist OAuth tokens across requests,
 * allowing tokens to be shared between PHP-FPM workers and queue workers.
 */
final class LaravelTokenStorage implements TokenStorageInterface
{
    private const TOKEN_KEY = 'oauth_token';
    private const EXPIRES_KEY = 'oauth_token_expires_at';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $prefix = 'tubapay_',
        private readonly int $expirationBuffer = 60,
    ) {}

    public function getToken(): ?string
    {
        return $this->cache->get($this->prefixKey(self::TOKEN_KEY));
    }

    public function setToken(string $token, int $expiresIn): void
    {
        // Apply expiration buffer to refresh tokens before they actually expire
        $ttl = max(0, $expiresIn - $this->expirationBuffer);

        $this->cache->put(
            $this->prefixKey(self::TOKEN_KEY),
            $token,
            $ttl > 0 ? $ttl : null
        );

        $this->cache->put(
            $this->prefixKey(self::EXPIRES_KEY),
            time() + $expiresIn,
            $ttl > 0 ? $ttl : null
        );
    }

    public function hasValidToken(): bool
    {
        $token = $this->getToken();

        if ($token === null) {
            return false;
        }

        $expiresAt = $this->cache->get($this->prefixKey(self::EXPIRES_KEY));

        if ($expiresAt === null) {
            return false;
        }

        // Check if token will expire within the buffer period
        return (int) $expiresAt > (time() + $this->expirationBuffer);
    }

    public function clearToken(): void
    {
        $this->cache->forget($this->prefixKey(self::TOKEN_KEY));
        $this->cache->forget($this->prefixKey(self::EXPIRES_KEY));
    }

    /**
     * Get the expiration timestamp of the current token.
     */
    public function getExpiresAt(): ?int
    {
        $expiresAt = $this->cache->get($this->prefixKey(self::EXPIRES_KEY));

        return $expiresAt !== null ? (int) $expiresAt : null;
    }

    /**
     * Get the remaining TTL in seconds for the current token.
     */
    public function getRemainingTtl(): ?int
    {
        $expiresAt = $this->getExpiresAt();

        if ($expiresAt === null) {
            return null;
        }

        $remaining = $expiresAt - time();

        return $remaining > 0 ? $remaining : 0;
    }

    /**
     * Prefix a cache key with the configured prefix.
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
