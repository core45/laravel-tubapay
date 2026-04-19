<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Listeners;

use Core45\LaravelTubaPay\Contracts\TubaPayOrderResolver;
use Core45\LaravelTubaPay\Contracts\TubaPayTransactable;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

final class DefaultHandleRecurringOrderRequested
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function handle(RecurringOrderRequested $event): void
    {
        $externalRef = $event->getExternalRef();

        if ($externalRef === null || $externalRef === '') {
            return;
        }

        $order = $this->resolveOrder($externalRef);

        if ($order === null) {
            return;
        }

        $position = $event->getFirstPosition();
        $order->recordTubaPayEvent(
            'recurring_order_requested',
            sprintf(
                'Payment schedule %s, rate %s requested.',
                $event->getPaymentScheduleId() ?? 'unknown',
                $position->rateNumber ?? 'unknown',
            ),
        );
    }

    private function resolveOrder(string $externalRef): ?TubaPayTransactable
    {
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
