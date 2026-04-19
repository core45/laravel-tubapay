<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Services;

use Core45\TubaPay\Enum\AgreementStatus;

final class TubaPayStatusMapper
{
    public function map(AgreementStatus $status): ?string
    {
        $statusMap = config('tubapay.status_map', []);

        if (! is_array($statusMap)) {
            return null;
        }

        $mappedStatus = $statusMap[$status->value] ?? null;

        return is_string($mappedStatus) && $mappedStatus !== '' ? $mappedStatus : null;
    }
}
