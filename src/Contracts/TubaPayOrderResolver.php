<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Contracts;

interface TubaPayOrderResolver
{
    public function resolve(string $externalRef): ?TubaPayTransactable;
}
