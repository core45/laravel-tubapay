<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Services;

use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Core45\LaravelTubaPay\Models\TubaPayCheckoutSelection;
use Core45\TubaPay\DTO\CheckoutSelection;
use Core45\TubaPay\DTO\TransactionMetadata;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final class EloquentCheckoutSelectionStore implements CheckoutSelectionStore
{
    public function put(string $externalRef, CheckoutSelection $selection, ?DateTimeInterface $expiresAt = null): void
    {
        TubaPayCheckoutSelection::query()->updateOrCreate(
            ['external_ref' => $externalRef],
            [
                'installments' => $selection->installments,
                'consents_accepted' => $selection->acceptedConsents,
                'return_url' => $selection->returnUrl,
                'metadata' => $selection->metadata?->toArray(),
                'expires_at' => $expiresAt ?? Carbon::now()->addMinutes($this->ttlMinutes()),
            ],
        );
    }

    public function get(string $externalRef): ?CheckoutSelection
    {
        /** @var TubaPayCheckoutSelection|null $storedSelection */
        $storedSelection = TubaPayCheckoutSelection::query()
            ->where('external_ref', $externalRef)
            ->first();

        if ($storedSelection === null) {
            return null;
        }

        if ($storedSelection->expires_at->isPast()) {
            $storedSelection->delete();

            return null;
        }

        return new CheckoutSelection(
            installments: $storedSelection->installments,
            acceptedConsents: $this->normalizeConsents($storedSelection->consents_accepted),
            returnUrl: $storedSelection->return_url,
            metadata: $this->metadataFromArray($storedSelection->metadata),
        );
    }

    public function forget(string $externalRef): void
    {
        TubaPayCheckoutSelection::query()
            ->where('external_ref', $externalRef)
            ->delete();
    }

    public function pruneExpired(?DateTimeInterface $before = null): int
    {
        return TubaPayCheckoutSelection::query()
            ->where('expires_at', '<=', $before ?? Carbon::now())
            ->delete();
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('tubapay.checkout.selection_ttl_minutes', 30));
    }

    /**
     * @return list<string>
     */
    private function normalizeConsents(mixed $consents): array
    {
        if (! is_array($consents)) {
            return [];
        }

        $normalized = [];

        foreach ($consents as $consent) {
            if (is_scalar($consent)) {
                $normalized[] = (string) $consent;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function metadataFromArray(?array $metadata): ?TransactionMetadata
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $knownKeys = ['appVersion', 'appDetailedVersion', 'source'];
        $additional = array_diff_key($metadata, array_flip($knownKeys));

        return new TransactionMetadata(
            appVersion: isset($metadata['appVersion']) ? (string) $metadata['appVersion'] : null,
            appDetailedVersion: isset($metadata['appDetailedVersion']) ? (string) $metadata['appDetailedVersion'] : null,
            source: isset($metadata['source']) ? (string) $metadata['source'] : null,
            additional: $this->normalizeMetadata($additional),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, scalar|null>
     */
    private function normalizeMetadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
