<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Tests\Unit;

use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;
use Core45\LaravelTubaPay\Tests\TestCase;
use Core45\TubaPay\Enum\AgreementStatus;
use PHPUnit\Framework\Attributes\Test;

final class TubaPayStatusMapperTest extends TestCase
{
    #[Test]
    public function defaults_return_null(): void
    {
        $mapper = new TubaPayStatusMapper;

        $this->assertNull($mapper->map(AgreementStatus::Accepted));
    }

    #[Test]
    public function configured_status_returns_mapped_value(): void
    {
        $this->app['config']->set('tubapay.status_map.accepted', 'paid');

        $mapper = new TubaPayStatusMapper;

        $this->assertSame('paid', $mapper->map(AgreementStatus::Accepted));
    }
}
