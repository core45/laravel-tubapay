<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\ViewModels;

use Core45\TubaPay\DTO\Offer;
use Core45\TubaPay\DTO\UiTexts;

readonly class CheckoutOptions
{
    /**
     * @param  list<InstallmentOption>  $installments
     * @param  list<ConsentOption>  $consents
     */
    public function __construct(
        public float $amount,
        public bool $available,
        public array $installments,
        public array $consents,
        public UiTexts $uiTexts,
        public ?int $recommendedInstallments = null,
        public ?Offer $rawOffer = null,
    ) {}

    public static function fromOffer(
        Offer $offer,
        float $amount,
        UiTexts $uiTexts,
        ?int $selectedInstallments = null,
    ): self {
        $availableInstallments = $offer->getAvailableInstallments();
        $recommendedInstallments = max($availableInstallments ?: [0]) ?: null;
        $defaultSelected = $selectedInstallments ?? $recommendedInstallments;

        $installments = array_map(
            static fn (int $installments): InstallmentOption => InstallmentOption::fromInstallments(
                installments: $installments,
                amount: $amount,
                selected: $installments === $defaultSelected,
            ),
            $availableInstallments,
        );

        $consents = array_map(
            static fn ($consent): ConsentOption => ConsentOption::fromConsent($consent),
            $offer->consents,
        );

        return new self(
            amount: $amount,
            available: count($availableInstallments) > 0,
            installments: $installments,
            consents: $consents,
            uiTexts: $uiTexts,
            recommendedInstallments: $recommendedInstallments,
            rawOffer: $offer,
        );
    }

    public function installmentTitle(): string
    {
        $default = __('tubapay::messages.choose_installments');

        if (! is_string($default)) {
            $default = 'Choose installments';
        }

        return $this->uiTexts->get('TP_CHOOSE_RATES_TITLE', $default) ?? $default;
    }
}
