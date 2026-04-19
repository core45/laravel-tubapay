<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Contracts;

use Core45\LaravelTubaPay\Models\TubaPayRecurringRequest;
use Core45\TubaPay\DTO\Webhook\InvoicePayload;

interface HandlesTubaPayRecurringOrder
{
    public function handle(InvoicePayload $payload, ?TubaPayRecurringRequest $record = null): void;
}
