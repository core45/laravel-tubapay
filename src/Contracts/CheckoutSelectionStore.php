<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Contracts;

use Core45\TubaPay\DTO\CheckoutSelection;
use DateTimeInterface;

interface CheckoutSelectionStore
{
    public function put(string $externalRef, CheckoutSelection $selection, ?DateTimeInterface $expiresAt = null): void;

    public function get(string $externalRef): ?CheckoutSelection;

    public function forget(string $externalRef): void;

    public function pruneExpired(?DateTimeInterface $before = null): int;
}
