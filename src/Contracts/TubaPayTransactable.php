<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Contracts;

interface TubaPayTransactable
{
    public function markTubaPayAccepted(string $agreementNumber): void;

    public function markTubaPayRejected(string $status, ?string $agreementNumber = null): void;

    public function recordTubaPayEvent(string $event, string $details): void;

    public function isTubaPayPaid(): bool;
}
