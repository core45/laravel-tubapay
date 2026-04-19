<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Models\TubaPayCheckoutSelection;
use Core45\LaravelTubaPay\Services\EloquentCheckoutSelectionStore;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\DTO\CheckoutSelection;
use Core45\TubaPay\DTO\TransactionMetadata;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

final class EloquentCheckoutSelectionStoreTest extends TestCase
{
    #[Test]
    public function it_stores_and_returns_checkout_selection(): void
    {
        $store = new EloquentCheckoutSelectionStore;

        $store->put(
            'ORDER-123',
            new CheckoutSelection(
                installments: 12,
                acceptedConsents: ['RODO_BP'],
                returnUrl: 'https://example.com/return',
                metadata: TransactionMetadata::forIntegration('laravel-test'),
            ),
        );

        $selection = $store->get('ORDER-123');

        $this->assertNotNull($selection);
        $this->assertSame(12, $selection->installments);
        $this->assertSame(['RODO_BP'], $selection->acceptedConsents);
        $this->assertSame('https://example.com/return', $selection->returnUrl);
        $this->assertSame('laravel-test', $selection->metadata?->source);
    }

    #[Test]
    public function it_forgets_checkout_selection(): void
    {
        $store = new EloquentCheckoutSelectionStore;

        $store->put('ORDER-123', new CheckoutSelection(installments: 12));
        $store->forget('ORDER-123');

        $this->assertNull($store->get('ORDER-123'));
    }

    #[Test]
    public function it_deletes_expired_selection_when_read(): void
    {
        $store = new EloquentCheckoutSelectionStore;

        $store->put(
            'ORDER-123',
            new CheckoutSelection(installments: 12),
            Carbon::now()->subMinute(),
        );

        $this->assertNull($store->get('ORDER-123'));
        $this->assertSame(0, TubaPayCheckoutSelection::query()->count());
    }

    #[Test]
    public function it_prunes_expired_selections(): void
    {
        $store = new EloquentCheckoutSelectionStore;

        $store->put('EXPIRED', new CheckoutSelection(installments: 6), Carbon::now()->subMinute());
        $store->put('ACTIVE', new CheckoutSelection(installments: 12), Carbon::now()->addMinute());

        $this->assertSame(1, $store->pruneExpired());
        $this->assertNotNull($store->get('ACTIVE'));
    }
}
