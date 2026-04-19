<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Services;

use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Core45\LaravelTubaPay\Models\TubaPayTransaction;
use Core45\TubaPay\DTO\CheckoutSelection;
use Core45\TubaPay\DTO\Customer;
use Core45\TubaPay\DTO\OrderItem;
use Core45\TubaPay\DTO\Transaction;
use Core45\TubaPay\DTO\TransactionMetadata;
use Core45\TubaPay\Enum\AgreementStatus;
use Core45\TubaPay\Exception\ValidationException;
use Core45\TubaPay\TubaPay;

final class TubaPayCheckoutService
{
    public function __construct(
        private readonly TubaPay $tubaPay,
        private readonly CheckoutSelectionStore $selectionStore,
    ) {}

    /**
     * Create a TubaPay transaction and persist the local tracking row.
     *
     * @param  list<OrderItem>  $items
     */
    public function createTransaction(
        string $externalRef,
        Customer $customer,
        array $items,
        string $callbackUrl,
        ?CheckoutSelection $selection = null,
    ): Transaction {
        if ($items === []) {
            throw ValidationException::missingField('items');
        }

        [$resolvedSelection, $selectionSource] = $this->resolveSelection($externalRef, $selection);

        if ($resolvedSelection->metadata === null) {
            $resolvedSelection = $resolvedSelection->withMetadata($this->defaultMetadata());
        }

        $transaction = $this->tubaPay->transactions()->createTransactionFromSelection(
            customer: $customer,
            items: $items,
            callbackUrl: $callbackUrl,
            selection: $resolvedSelection,
            externalRef: $externalRef,
        );

        $this->persistTransaction(
            externalRef: $externalRef,
            customer: $customer,
            items: $items,
            selection: $resolvedSelection,
            selectionSource: $selectionSource,
            transaction: $transaction,
        );

        $this->selectionStore->forget($externalRef);

        return $transaction;
    }

    /**
     * Create a TubaPay transaction using a previously persisted checkout selection.
     *
     * @param  list<OrderItem>  $items
     */
    public function createTransactionForOrder(
        string $externalRef,
        Customer $customer,
        array $items,
        string $callbackUrl,
    ): Transaction {
        return $this->createTransaction(
            externalRef: $externalRef,
            customer: $customer,
            items: $items,
            callbackUrl: $callbackUrl,
        );
    }

    /**
     * @return array{0: CheckoutSelection, 1: string}
     */
    private function resolveSelection(string $externalRef, ?CheckoutSelection $selection): array
    {
        if ($selection !== null) {
            return [$selection, 'provided'];
        }

        $storedSelection = $this->selectionStore->get($externalRef);

        if ($storedSelection !== null) {
            return [$storedSelection, 'stored'];
        }

        return [
            new CheckoutSelection(
                installments: (int) config('tubapay.checkout.default_installments', 12),
                returnUrl: $this->defaultReturnUrl(),
            ),
            'default',
        ];
    }

    private function defaultReturnUrl(): ?string
    {
        $returnUrl = config('tubapay.return_url');

        return is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : null;
    }

    private function defaultMetadata(): TransactionMetadata
    {
        return new TransactionMetadata(
            appVersion: $this->stringConfig('tubapay.integration.app_version'),
            appDetailedVersion: $this->stringConfig('tubapay.integration.app_detailed_version'),
            source: $this->stringConfig('tubapay.integration.source') ?? 'laravel',
        );
    }

    private function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<OrderItem>  $items
     */
    private function persistTransaction(
        string $externalRef,
        Customer $customer,
        array $items,
        CheckoutSelection $selection,
        string $selectionSource,
        Transaction $transaction,
    ): void {
        if (! (bool) config('tubapay.database.track_transactions', true)) {
            return;
        }

        TubaPayTransaction::query()->updateOrCreate(
            ['external_ref' => $externalRef],
            [
                'transaction_link' => $transaction->transactionLink,
                'status' => AgreementStatus::Draft->value,
                'amount' => $this->totalAmount($items),
                'currency' => 'PLN',
                'installments' => $selection->installments,
                'consents_accepted' => $selection->acceptedConsents,
                'selection_source' => $selectionSource,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'customer_name' => trim($customer->firstName.' '.$customer->lastName),
                'metadata' => $selection->metadata?->toArray(),
            ],
        );
    }

    /**
     * @param  list<OrderItem>  $items
     */
    private function totalAmount(array $items): float
    {
        return array_sum(array_map(
            static fn (OrderItem $item): float => $item->totalValue,
            $items,
        ));
    }
}
