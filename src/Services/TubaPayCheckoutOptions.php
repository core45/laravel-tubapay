<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Services;

use Core45\LaravelTubaPay\ViewModels\CheckoutOptions;
use Core45\TubaPay\DTO\UiTexts;
use Core45\TubaPay\TubaPay;
use Throwable;

final class TubaPayCheckoutOptions
{
    public function __construct(
        private readonly TubaPay $tubaPay,
    ) {}

    public function forAmount(float $amount, ?int $selectedInstallments = null): CheckoutOptions
    {
        $offer = $this->tubaPay->offers()->createClientOffer($amount);
        $uiTexts = $this->fetchUiTexts();

        return CheckoutOptions::fromOffer($offer, $amount, $uiTexts, $selectedInstallments);
    }

    private function fetchUiTexts(): UiTexts
    {
        try {
            return $this->tubaPay->uiTexts()->getTexts();
        } catch (Throwable) {
            return new UiTexts([]);
        }
    }
}
