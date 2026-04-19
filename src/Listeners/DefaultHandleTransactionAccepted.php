<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Listeners;

use Core45\LaravelTubaPay\Contracts\TubaPayOrderResolver;
use Core45\LaravelTubaPay\Contracts\TubaPayTransactable;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

final class DefaultHandleTransactionAccepted
{
    public function __construct(
        private readonly Container $container,
        private readonly TubaPayStatusMapper $statusMapper,
    ) {}

    public function handle(TransactionStatusChanged $event): void
    {
        if (! $event->isAccepted()) {
            return;
        }

        $order = $this->resolveOrder($event->getExternalRef());

        if ($order === null) {
            return;
        }

        $agreementNumber = $event->getAgreementNumber() ?? '';
        $order->markTubaPayAccepted($agreementNumber);

        $mappedStatus = $this->statusMapper->map($event->getStatus());
        $order->recordTubaPayEvent(
            'transaction_accepted',
            $mappedStatus !== null ? 'Mapped status: '.$mappedStatus : 'TubaPay accepted the transaction.',
        );
    }

    private function resolveOrder(string $externalRef): ?TubaPayTransactable
    {
        if ($externalRef === '') {
            return null;
        }

        try {
            /** @var TubaPayOrderResolver $resolver */
            $resolver = $this->container->make(TubaPayOrderResolver::class);
        } catch (BindingResolutionException) {
            Log::debug('TubaPay order resolver is not bound.');

            return null;
        }

        return $resolver->resolve($externalRef);
    }
}
