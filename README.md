# Laravel TubaPay

A Laravel integration for [TubaPay](https://tubapay.pl) BNPL (Buy Now, Pay Later) payment solutions.

Sits on top of [`core45/tubapay-php`](https://packagist.org/packages/core45/tubapay-php) and gives you:

- A webhook endpoint with idempotency and optional persistence
- Checkout helpers (installment/consents resolution + Blade components)
- A selection store so the customer's choice survives between HTTP requests
- A transaction creation service that writes a local tracking row
- Optional default listeners wired to any order model implementing a small contract
- Blade components for status badges, top bar, popup, installment selector, and consent checkboxes
- Translations in 6 languages (en, pl, de, es, fr, it)

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [How TubaPay Works (Flow Overview)](#how-tubapay-works-flow-overview)
5. [End-to-End Integration Guide](#end-to-end-integration-guide)
   - [Step 1 — Show TubaPay on checkout](#step-1--show-tubapay-on-checkout)
   - [Step 2 — Validate selection before creating the order](#step-2--validate-selection-before-creating-the-order)
   - [Step 3 — Persist the selection, then create the transaction](#step-3--persist-the-selection-then-create-the-transaction)
   - [Step 4 — Handle webhooks](#step-4--handle-webhooks)
6. [Livewire Integration](#livewire-integration)
7. [Webhook Events Reference](#webhook-events-reference)
8. [Optional Default Listeners](#optional-default-listeners)
9. [Blade Components](#blade-components)
10. [Console Commands & Scheduling](#console-commands--scheduling)
11. [Transaction Tracking Model](#transaction-tracking-model)
12. [Agreement Statuses](#agreement-statuses)
13. [Configuration Options](#configuration-options)
14. [Translations](#translations)
15. [Testing](#testing)
16. [License](#license)

---

## Requirements

- PHP 8.2 or higher (PHP 8.3+ for Laravel 13)
- Laravel 10.x, 11.x, 12.x, or 13.x
- `core45/tubapay-php` ^0.2.1
- TubaPay merchant account (sandbox credentials for `test`, production credentials for `production`)

## Installation

```bash
composer require core45/laravel-tubapay
```

The service provider is auto-discovered. Publish the config and migrations:

```bash
php artisan vendor:publish --tag=tubapay-config
php artisan vendor:publish --tag=tubapay-migrations
php artisan migrate
```

Migrations create:

| Table | Purpose |
|-------|---------|
| `tubapay_transactions` | Local tracking row per transaction — status, amount, customer, installments, consents |
| `tubapay_checkout_selections` | Short-lived store for the customer's installment/consent choices, keyed by your order reference |
| `tubapay_webhook_events` | Idempotency log — deduplicates webhook deliveries by `commandType:commandRef` |
| `tubapay_payments` | Merchant payout notifications (optional, populated by persistence layer) |
| `tubapay_recurring_requests` | Recurring order requests from TubaPay (optional) |

## Configuration

Minimum `.env`:

```env
TUBAPAY_CLIENT_ID=your-client-id
TUBAPAY_CLIENT_SECRET=your-client-secret
TUBAPAY_WEBHOOK_SECRET=your-webhook-secret

# test | production
TUBAPAY_ENVIRONMENT=test

# Where to send the customer after they sign the agreement
TUBAPAY_RETURN_URL=https://example.com/checkout/thanks

# Toggle JSON UI routes (off by default — only enable if your frontend needs them)
TUBAPAY_UI_ROUTES=false

# Toggle auto-registration of default listeners (off by default — prefer writing your own)
TUBAPAY_AUTO_LISTENERS=false
```

Additional options (see `config/tubapay.php` for the full list):

```env
# Used as fallback when the customer's actual selection is unavailable
TUBAPAY_DEFAULT_INSTALLMENTS=12

# Webhook path (default: webhooks/tubapay)
TUBAPAY_WEBHOOK_PATH=webhooks/tubapay

# Signature verification — keep on in production
TUBAPAY_VERIFY_SIGNATURES=true

# Dedupe repeated webhook deliveries (recommended on)
TUBAPAY_WEBHOOK_IDEMPOTENCY=true

# Prune expired checkout selections after this many minutes
TUBAPAY_SELECTION_TTL_MINUTES=30

# Local DB tracking of transactions
TUBAPAY_TRACK_TRANSACTIONS=true

# Integration metadata — sent with every transaction creation
TUBAPAY_INTEGRATION_SOURCE=laravel
TUBAPAY_APP_VERSION=laravel-tubapay
TUBAPAY_APP_DETAILED_VERSION=0.4.0

# Promotional top bar (site-wide banner)
TUBAPAY_TOP_BAR_ENABLED=false
TUBAPAY_UI_CACHE_TTL=3600

# Debug logging
TUBAPAY_LOG_WEBHOOKS=false
TUBAPAY_LOG_REQUESTS=false
```

## How TubaPay Works (Flow Overview)

```
[Customer cart]
      │
      │  1. Customer picks TubaPay, installment count, accepts required consents
      ▼
[Your app]
      │  2. You create the order locally (status: pending payment)
      │  3. You persist the CheckoutSelection keyed by order reference
      ▼
[TubaPayCheckoutService::createTransaction()]
      │  4. SDK contacts TubaPay, creates an agreement, returns a redirect link
      ▼
[Customer redirected to TubaPay]
      │  5. Customer completes KYC + signs the financing agreement
      ▼
[Webhooks fire]
      │
      ├── TRANSACTION_STATUS_CHANGED (accepted / rejected / signed / …)
      │       └─ You mark the order paid or failed
      │
      ├── TRANSACTION_MERCHANT_PAYMENT
      │       └─ Informational: TubaPay has paid you; log it
      │
      └── CUSTOMER_RECURRING_ORDER_REQUEST
              └─ Generate a monthly recurring order (if applicable)
```

**Two important moments to distinguish:**

- **Credit accepted** (`TransactionStatusChanged` with `isAccepted()`) — TubaPay has approved the customer's financing application. At this point TubaPay has assumed the credit risk and the merchant can fulfill the order. This is the right moment to mark the order paid.
- **Merchant payment** (`PaymentReceived`) — TubaPay has wired the funds to your merchant account. This happens later, on TubaPay's payout schedule. It's informational — your order is already paid from the customer's perspective when the credit was accepted.

---

## End-to-End Integration Guide

This section walks through a typical checkout integration. If you use Livewire, jump to [Livewire Integration](#livewire-integration) for adjustments to the Blade components.

### Step 1 — Show TubaPay on checkout

Resolve installment and consent options for the current cart total and render the Blade components.

```php
use Core45\LaravelTubaPay\Services\TubaPayCheckoutOptions;

public function __construct(private readonly TubaPayCheckoutOptions $checkoutOptions)
{
}

public function render(): View
{
    $grandTotalInMajorUnits = $this->cart->grandTotalGross() / 100; // if you store cents

    $tubaPayOptions = $this->checkoutOptions->forAmount($grandTotalInMajorUnits);

    // $tubaPayOptions->available === false  => TubaPay is not eligible for this amount
    // $tubaPayOptions->recommendedInstallments => pre-select this value
    // $tubaPayOptions->installments => list of available plans
    // $tubaPayOptions->consents => list of consents the customer must accept

    return view('checkout.cart', [
        'tubaPayOptions' => $tubaPayOptions,
    ]);
}
```

In the Blade view:

```blade
@if ($tubaPayOptions->available)
    <x-tubapay::installment-selector
        :options="$tubaPayOptions"
        name="tubapay_installments"
    />

    <x-tubapay::consent-checkboxes
        :options="$tubaPayOptions"
        name="tubapay_consents"
    />
@else
    <p>{{ __('TubaPay is not available for this cart value.') }}</p>
@endif
```

Cache the `CheckoutOptions` response per cart total — the SDK hits the TubaPay API each time you call `forAmount()`.

### Step 2 — Validate selection before creating the order

Before persisting the order, reject submissions that chose TubaPay with an incomplete selection:

```php
use Illuminate\Validation\ValidationException;

public function placeOrder(Request $request): RedirectResponse
{
    if ($request->input('payment_method') === 'tubapay') {
        $options = $this->checkoutOptions->forAmount($this->cart->total());

        if (! $options->available) {
            throw ValidationException::withMessages([
                'payment_method' => __('TubaPay is not available for this cart.'),
            ]);
        }

        $installments = (int) $request->input('tubapay_installments');
        if ($installments <= 0) {
            throw ValidationException::withMessages([
                'tubapay_installments' => __('Please pick an installment plan.'),
            ]);
        }

        $acceptedConsents = (array) $request->input('tubapay_consents', []);
        foreach ($options->consents as $consent) {
            if ($consent->required && ! in_array($consent->type, $acceptedConsents, true)) {
                throw ValidationException::withMessages([
                    'tubapay_consents' => __('Please accept all required consents.'),
                ]);
            }
        }
    }

    // ... create the order ...
}
```

### Step 3 — Persist the selection, then create the transaction

After the order exists in your database, write a `CheckoutSelection` keyed by a reference you control — typically the order's UUID. Then create the TubaPay transaction, passing the selection **explicitly** to avoid the SDK silently falling back to defaults with empty consents.

```php
use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Core45\LaravelTubaPay\Services\TubaPayCheckoutService;
use Core45\TubaPay\DTO\CheckoutSelection;
use Core45\TubaPay\DTO\Customer;
use Core45\TubaPay\DTO\OrderItem;

public function __construct(
    private readonly CheckoutSelectionStore $selectionStore,
    private readonly TubaPayCheckoutService $checkoutService,
) {}

public function startTubaPayPayment(Order $order, array $installments, array $acceptedConsents): RedirectResponse
{
    $selection = new CheckoutSelection(
        installments: (int) $installments,
        acceptedConsents: $acceptedConsents,
        returnUrl: route('checkout.thanks', ['order' => $order->uuid]),
    );

    // Persist so it survives a request boundary (and so you can recover it if anything fails)
    $this->selectionStore->put($order->uuid, $selection);

    $customer = new Customer(
        firstName: $order->billing_first_name,
        lastName: $order->billing_last_name,
        email: $order->billing_email,
        phone: $order->billing_phone,
        street: $order->billing_street,
        zipCode: $order->billing_zip,
        town: $order->billing_town,
    );

    $items = $order->items->map(fn ($item) => new OrderItem(
        name: $item->name,
        totalValue: $item->total_gross_major, // major units (e.g. 1000.00 for 1000 PLN)
    ))->all();

    $transaction = $this->checkoutService->createTransaction(
        externalRef: $order->uuid,
        customer: $customer,
        items: $items,
        callbackUrl: route('tubapay.webhook'), // or the package's built-in route
        selection: $selection, // pass it explicitly — do not rely on the store fallback
    );

    return redirect($transaction->transactionLink);
}
```

> **Why pass `selection` explicitly?**
> If you omit the `selection` argument, `TubaPayCheckoutService` will look for a stored selection by `externalRef` and — if none is found — fall back to a default `CheckoutSelection` with **no accepted consents**, which will likely be rejected by TubaPay or create an agreement the customer hasn't actually consented to. Always pass the explicit selection you validated in Step 2.

### Step 4 — Handle webhooks

The package registers a webhook route at `POST /webhooks/tubapay` (configurable via `TUBAPAY_WEBHOOK_PATH`). Signature verification and idempotency happen automatically. You subscribe to the dispatched events in your own listeners.

Register them in `AppServiceProvider::boot()` (Laravel 11/12 style) or in an `EventServiceProvider`:

```php
use App\Listeners\TubaPay\HandlePaymentReceived;
use App\Listeners\TubaPay\HandleRecurringOrderRequested;
use App\Listeners\TubaPay\HandleTransactionAccepted;
use App\Listeners\TubaPay\HandleTransactionRejected;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(TransactionStatusChanged::class, HandleTransactionAccepted::class);
    Event::listen(TransactionStatusChanged::class, HandleTransactionRejected::class);
    Event::listen(PaymentReceived::class, HandlePaymentReceived::class);
    Event::listen(RecurringOrderRequested::class, HandleRecurringOrderRequested::class);
}
```

Example "mark order paid on credit acceptance" listener:

```php
namespace App\Listeners\TubaPay;

use App\Models\Order;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class HandleTransactionAccepted implements ShouldQueue
{
    public function handle(TransactionStatusChanged $event): void
    {
        if (! $event->isAccepted()) {
            return;
        }

        $order = Order::query()->where('uuid', $event->getExternalRef())->first();

        if ($order === null) {
            Log::warning('TubaPay: order not found for accepted transaction', [
                'external_ref' => $event->getExternalRef(),
            ]);

            return;
        }

        // Idempotency — webhooks can retry
        if ($order->paid && $order->payment_gateway_name === 'tubapay') {
            return;
        }

        $order->forceFill([
            'paid' => true,
            'status' => 'processing',
            'payment_gateway_name' => 'tubapay',
            'payment_gateway_order_id' => $event->getAgreementNumber(),
        ])->save();
    }
}
```

Example rejection listener:

```php
final class HandleTransactionRejected implements ShouldQueue
{
    public function handle(TransactionStatusChanged $event): void
    {
        if (! $event->isRejected()) {
            return;
        }

        $order = Order::query()->where('uuid', $event->getExternalRef())->first();

        $order?->forceFill(['status' => 'cancelled'])->save();
    }
}
```

---

## Livewire Integration

The shipped Blade components use plain `<input>` tags bound to a `name` attribute. If you bind them to a Livewire component with `wire:model` / `wire:model.live`, you need to publish and patch the views so the binding lands on the `<input>` element rather than the wrapping `<div>`:

```bash
php artisan vendor:publish --tag=tubapay-views
```

Then edit `resources/views/vendor/tubapay/components/installment-selector.blade.php`:

```blade
@props([
    'options',
    'name' => 'tubapay_installments',
    'wireModel' => null,
])

<div {{ $attributes->merge(['class' => 'tubapay-installment-selector']) }}>
    <p>{{ $options->installmentTitle() }}</p>

    @foreach ($options->installments as $option)
        <label for="{{ $name }}_{{ $option->installments }}">
            <input
                type="radio"
                name="{{ $name }}"
                id="{{ $name }}_{{ $option->installments }}"
                value="{{ $option->installments }}"
                @if ($wireModel) wire:model.live="{{ $wireModel }}" @endif
                @checked($option->selected)
            >
            <span>{{ $option->label }}</span>
        </label>
    @endforeach
</div>
```

Apply the same pattern to `consent-checkboxes.blade.php`. Usage:

```blade
<x-tubapay::installment-selector
    :options="$tubaPayOptions"
    wire-model="tubaPayInstallments"
/>

<x-tubapay::consent-checkboxes
    :options="$tubaPayOptions"
    wire-model="tubaPayConsents"
/>
```

> **Be careful with `--force`.** Re-publishing views with `--force` will overwrite your Livewire-bound copies. Commit the overrides to your repo and avoid `--force` after that.

---

## Webhook Events Reference

| Event | TubaPay command | When it fires | Typical action |
|-------|-----------------|---------------|----------------|
| `TransactionStatusChanged` | `TRANSACTION_STATUS_CHANGED` | Credit application transitions through draft → registered → signed → **accepted**/rejected | Mark order paid on `isAccepted()`; cancel on `isRejected()` |
| `PaymentReceived` | `TRANSACTION_MERCHANT_PAYMENT` | TubaPay has paid out funds to the merchant | Log for bookkeeping; do not re-mark the order paid (it already is) |
| `RecurringOrderRequested` | `CUSTOMER_RECURRING_ORDER_REQUEST` | Customer's recurring billing cycle generates a new order | Create the next local order against the saved agreement |
| `InvoiceRequested` | (legacy alias of above) | Same as `RecurringOrderRequested` | Prefer `RecurringOrderRequested` for new code |
| `WebhookReceived` | any | Every incoming webhook (raw) | Debugging / custom routing |

Common methods on `TransactionStatusChanged`:

```php
$event->getExternalRef();      // your order reference
$event->getAgreementNumber();  // TubaPay agreement number
$event->getStatus();           // AgreementStatus string
$event->isAccepted();
$event->isRejected();
$event->isPending();
```

Webhook idempotency is handled by the `tubapay_webhook_events` table — duplicate deliveries with the same `commandType:commandRef` are acknowledged (HTTP 200) but not re-dispatched.

---

## Optional Default Listeners

If you'd rather not write your own listeners and your order model can implement a small contract, the package can auto-register default listeners for accepted/rejected/payment/recurring events.

**1. Implement the resolver:**

```php
use App\Models\Order;
use Core45\LaravelTubaPay\Contracts\TubaPayOrderResolver;
use Core45\LaravelTubaPay\Contracts\TubaPayTransactable;

final class OrderResolver implements TubaPayOrderResolver
{
    public function resolve(string $externalRef): ?TubaPayTransactable
    {
        return Order::query()->where('uuid', $externalRef)->first();
    }
}
```

**2. Implement `TubaPayTransactable` on the order model:**

```php
use Core45\LaravelTubaPay\Contracts\TubaPayTransactable;

final class Order extends Model implements TubaPayTransactable
{
    public function markTubaPayAccepted(string $agreementNumber): void
    {
        $this->forceFill([
            'status' => 'paid',
            'tubapay_agreement_number' => $agreementNumber,
        ])->save();
    }

    public function markTubaPayRejected(string $status, ?string $agreementNumber = null): void
    {
        $this->forceFill([
            'status' => $status,
            'tubapay_agreement_number' => $agreementNumber,
        ])->save();
    }

    public function recordTubaPayEvent(string $event, string $details): void
    {
        $this->history()->create([
            'event' => $event,
            'details' => $details,
        ]);
    }

    public function isTubaPayPaid(): bool
    {
        return $this->status === 'paid';
    }
}
```

**3. Bind the resolver and enable auto-listeners:**

```php
// AppServiceProvider::register()
$this->app->bind(
    \Core45\LaravelTubaPay\Contracts\TubaPayOrderResolver::class,
    \App\Services\OrderResolver::class,
);
```

```env
TUBAPAY_AUTO_LISTENERS=true
```

---

## Blade Components

```blade
{{-- Status badge for a transaction --}}
<x-tubapay::status-badge :status="$transaction->status" />

{{-- Checkout controls --}}
<x-tubapay::installment-selector :options="$options" name="tubapay_installments" />
<x-tubapay::consent-checkboxes :options="$options" name="tubapay_consents" />

{{-- Official TubaPay content (cached via TUBAPAY_UI_CACHE_TTL) --}}
<x-tubapay::top-bar :content="$topBarContent" />
<x-tubapay::popup :content="$popupContent" />
```

Fetching the top bar content:

```php
$topBarContent = app(\Core45\LaravelTubaPay\Facades\TubaPay::class)::content()->topBar();
```

---

## Console Commands & Scheduling

```bash
# Verify credentials and API reachability
php artisan tubapay:check-connection

# Remove expired rows from tubapay_checkout_selections
php artisan tubapay:prune-selections
```

Schedule the prune task daily in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tubapay:prune-selections')
    ->daily()
    ->onOneServer();
```

---

## Transaction Tracking Model

```php
use Core45\LaravelTubaPay\Models\TubaPayTransaction;

$transaction = TubaPayTransaction::findByExternalRef('order-uuid');

TubaPayTransaction::pending()->get();
TubaPayTransaction::successful()->get();
TubaPayTransaction::failed()->get();
TubaPayTransaction::forCustomer('jan@example.com')->get();

if ($transaction->isSuccessful()) {
    // ...
}
```

The row is created with `currency = 'PLN'` (the TubaPay platform's native currency) and `status = draft`. If your order is denominated in another currency (e.g. EUR), update the tracking row's currency after `createTransaction()` returns:

```php
$transaction = $this->checkoutService->createTransaction(...);

TubaPayTransaction::query()
    ->where('external_ref', $order->uuid)
    ->update(['currency' => $order->currency]);
```

---

## Agreement Statuses

| Status | Description | `isPending()` | `isSuccessful()` | `isFailed()` |
|--------|-------------|:-:|:-:|:-:|
| `draft` | Initial state | ✓ | | |
| `registered` | Application submitted | ✓ | | |
| `signed` | Documents signed | ✓ | | |
| `accepted` | Approved — customer is financed, merchant can fulfill | | ✓ | |
| `rejected` | Application rejected | | | ✓ |
| `canceled` | Canceled by customer | | | ✓ |
| `terminated` | Terminated by system | | | ✓ |
| `withdrew` | Customer withdrew | | | ✓ |
| `repaid` | Fully repaid | | ✓ | |
| `closed` | Agreement closed | | ✓ | |

---

## Configuration Options

See `config/tubapay.php` for the full list:

- Webhook route path and signature verification
- Webhook idempotency lease and retry settings
- Token cache store and TTL
- Transaction tracking toggle
- Checkout default installments and selection TTL
- Optional UI JSON route registration
- Optional auto-listener registration
- Optional status map (`accepted` → `paid`, `rejected` → `failed`, etc.)
- Integration metadata sent to TubaPay (source, app version)
- Top bar / popup / content cache TTL
- Debug logging flags

Use the status mapper from your listeners:

```php
use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;

$localStatus = app(TubaPayStatusMapper::class)->map($event->getStatus());
```

---

## Optional UI Helper Routes

Enable JSON endpoints for client-side checkouts (SPA, fetch-based carts):

```env
TUBAPAY_UI_ROUTES=true
```

| Method | Path | Route name | Purpose |
|--------|------|------------|---------|
| GET | `/tubapay/installments?amount=1000` | `tubapay.ui.installments` | Installments + consents + UI texts for amount |
| GET | `/tubapay/content/top-bar` | `tubapay.ui.content.top-bar` | Top bar HTML content |
| GET | `/tubapay/content/popup` | `tubapay.ui.content.popup` | Popup HTML content |
| GET | `/tubapay/texts` | `tubapay.ui.texts` | Checkout UI text labels |

Content responses are cached for `TUBAPAY_UI_CACHE_TTL` seconds.

---

## Translations

Translations ship in en, pl, de, es, fr, it. Publish to customize:

```bash
php artisan vendor:publish --tag=tubapay-lang
```

---

## Testing

```bash
composer test
composer phpstan
```

End-to-end transaction creation is covered manually against the TubaPay sandbox because `TubaPayCheckoutService` is `final` and `TubaPay` client methods are not easily mocked. For unit tests, stub the `TubaPay` client binding:

```php
$this->app->instance(\Core45\TubaPay\TubaPay::class, $fakeTubaPay);
```

---

## License

MIT License. See LICENSE file for details.
