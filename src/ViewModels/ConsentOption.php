<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\ViewModels;

use Core45\TubaPay\DTO\Consent;

readonly class ConsentOption
{
    public function __construct(
        public string $type,
        public string $label,
        public bool $required,
    ) {}

    public static function fromConsent(Consent $consent): self
    {
        return new self(
            type: $consent->type,
            label: $consent->title,
            required: $consent->isRequired(),
        );
    }
}
