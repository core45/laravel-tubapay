<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\ViewModels;

readonly class InstallmentOption
{
    public function __construct(
        public int $installments,
        public float $monthlyAmount,
        public string $label,
        public bool $selected,
    ) {}

    public static function fromInstallments(int $installments, float $amount, bool $selected = false): self
    {
        $monthlyAmount = ceil($amount / $installments);
        $months = __('tubapay::messages.months');
        $monthly = __('tubapay::messages.monthly');

        if (! is_string($months)) {
            $months = 'months';
        }

        if (! is_string($monthly)) {
            $monthly = 'monthly';
        }

        return new self(
            installments: $installments,
            monthlyAmount: $monthlyAmount,
            label: sprintf(
                '%d %s - %.2f %s',
                $installments,
                $months,
                $monthlyAmount,
                $monthly,
            ),
            selected: $selected,
        );
    }
}
