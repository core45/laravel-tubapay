<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Http\LaravelTokenStorage;
use Core45\LaravelTubaPay\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

final class LaravelTokenStorageTest extends TestCase
{
    private LaravelTokenStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new LaravelTokenStorage(
            Cache::store(),
            'test_tubapay_',
            60
        );
    }

    #[Test]
    public function it_stores_and_retrieves_token(): void
    {
        $this->storage->setToken('test-token', 3600);

        $this->assertSame('test-token', $this->storage->getToken());
    }

    #[Test]
    public function it_returns_null_when_no_token(): void
    {
        $this->assertNull($this->storage->getToken());
    }

    #[Test]
    public function it_validates_token_existence(): void
    {
        $this->assertFalse($this->storage->hasValidToken());

        $this->storage->setToken('test-token', 3600);

        $this->assertTrue($this->storage->hasValidToken());
    }

    #[Test]
    public function it_clears_token(): void
    {
        $this->storage->setToken('test-token', 3600);
        $this->assertTrue($this->storage->hasValidToken());

        $this->storage->clearToken();

        $this->assertFalse($this->storage->hasValidToken());
        $this->assertNull($this->storage->getToken());
    }

    #[Test]
    public function it_returns_expires_at(): void
    {
        $this->storage->setToken('test-token', 3600);

        $expiresAt = $this->storage->getExpiresAt();

        $this->assertNotNull($expiresAt);
        $this->assertGreaterThan(time(), $expiresAt);
    }

    #[Test]
    public function it_returns_remaining_ttl(): void
    {
        $this->storage->setToken('test-token', 3600);

        $ttl = $this->storage->getRemainingTtl();

        $this->assertNotNull($ttl);
        $this->assertGreaterThan(3500, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }

    #[Test]
    public function it_considers_expiration_buffer(): void
    {
        // Set token that expires in 30 seconds (less than 60 second buffer)
        $this->storage->setToken('test-token', 30);

        // Should be invalid because it expires within the buffer
        $this->assertFalse($this->storage->hasValidToken());
    }
}
