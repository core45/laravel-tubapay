@props([
    'status' => 'draft',
])

@php
    $statusEnum = is_string($status)
        ? \Core45\TubaPay\Enum\AgreementStatus::tryFrom($status) ?? \Core45\TubaPay\Enum\AgreementStatus::Draft
        : $status;

    $colorClasses = match(true) {
        $statusEnum->isPending() => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        $statusEnum->isSuccessful() => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        $statusEnum->isFailed() => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colorClasses}"]) }}>
    {{ __('tubapay::statuses.' . $statusEnum->value) }}
</span>
