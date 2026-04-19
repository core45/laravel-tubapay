# Laravel TubaPay

A Laravel integration for TubaPay BNPL (Buy Now, Pay Later) payment solutions.

## Requirements

- PHP 8.2 or higher; PHP 8.3 or higher for Laravel 13
- Laravel 10.x, 11.x, 12.x, or 13.x
- core45/tubapay-php ^0.2.1

## Installation

```bash
composer require core45/laravel-tubapay
```

The service provider will be auto-discovered by Laravel.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=tubapay-config
```

Add these environment variables to your `.env` file:

```env
TUBAPAY_CLIENT_ID=your-client-id
TUBAPAY_CLIENT_SECRET=your-client-secret
TUBAPAY_WEBHOOK_SECRET=your-webhook-secret
TUBAPAY_ENVIRONMENT=test  # or production
TUBAPAY_RETURN_URL=https://example.com/checkout/thanks
TUBAPAY_UI_ROUTES=false
TUBAPAY_AUTO_LISTENERS=false
```

## Quick Start

### Using the Facade

```php
use Core45\LaravelTubaPay\Facades\TubaPay;
use Core45\TubaPay\DTO\Customer;
use Core45\TubaPay\DTO\OrderItem;

// Create customer
$customer = new Customer(
    firstName: 'Jan',
    lastName: 'Kowalski',
    email: 'jan@example.com',
    phone: '519088975',
    street: 'Testowa',
    zipCode: '00-001',
    town: 'Warszawa',
);

// Create order item
$item = new OrderItem(
    name: 'Product Name',
    totalValue: 1000.00,
);

// Get available installment options
$offer = TubaPay::offers()->createOffer(
    amount: 1000.00,
    customer: $customer,
    item: $item,
    externalRef: 'ORDER-123',
);

$installments = $offer->getAvailableInstallments(); // [3, 6, 9, 12]
$consents = $offer->consents;

// Create transaction with selected installments
$transaction = TubaPay::transactions()->createTransaction(
    customer: $customer,
    item: $item,
    installments: 6,
    callbackUrl: route('tubapay.webhook'),
    externalRef: 'ORDER-123',
    returnUrl: route('checkout.success', ['order' => 'ORDER-123']),
    acceptedConsents: ['RODO_BP'],
);

// Redirect customer to payment page
return redirect($transaction->transactionLink);
```

`acceptedConsents` should contain consent type identifiers returned by the TubaPay offer response. The current WooCommerce plugin sends `RODO_BP` when that consent is accepted.

### Laravel Checkout Services

Use `TubaPayCheckoutOptions` to build installment and consent options for checkout screens:

```php
use Core45\LaravelTubaPay\Services\TubaPayCheckoutOptions;

$options = app(TubaPayCheckoutOptions::class)->forAmount(1000.00);
```

Render the included Blade primitives:

```blade
<x-tubapay::installment-selector :options="$options" />
<x-tubapay::consent-checkboxes :options="$options" />
```

Persist the customer's selection between checkout steps:

```php
use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Core45\TubaPay\DTO\CheckoutSelection;

app(CheckoutSelectionStore::class)->put(
    'ORDER-123',
    new CheckoutSelection(
        installments: 12,
        acceptedConsents: ['RODO_BP'],
        returnUrl: route('checkout.success', ['order' => 'ORDER-123']),
    ),
);
```

Then create and track the transaction from the stored selection:

```php
use Core45\LaravelTubaPay\Services\TubaPayCheckoutService;

$transaction = app(TubaPayCheckoutService::class)->createTransactionForOrder(
    externalRef: 'ORDER-123',
    customer: $customer,
    items: [$item],
    callbackUrl: route('tubapay.webhook'),
);

return redirect($transaction->transactionLink);
```

The checkout service writes a `TubaPayTransaction` row with selected installments, accepted consents, selection source, transaction link, customer details, and integration metadata.

### Optional UI Helper Routes

Enable opt-in JSON routes when your checkout needs browser-side installment or TubaPay content loading:

```env
TUBAPAY_UI_ROUTES=true
```

Registered routes:

| Method | Path | Route name | Purpose |
|--------|------|------------|---------|
| GET | `/tubapay/installments?amount=1000` | `tubapay.ui.installments` | Returns available installments, consents, and UI texts |
| GET | `/tubapay/content/top-bar` | `tubapay.ui.content.top-bar` | Returns the TubaPay top bar content |
| GET | `/tubapay/content/popup` | `tubapay.ui.content.popup` | Returns the TubaPay popup content |
| GET | `/tubapay/texts` | `tubapay.ui.texts` | Returns checkout UI text labels |

Content and UI text responses are cached for `TUBAPAY_UI_CACHE_TTL` seconds.

### Dependency Injection

```php
use Core45\TubaPay\TubaPay;

class PaymentController extends Controller
{
    public function __construct(
        private readonly TubaPay $tubaPay,
    ) {}

    public function createPayment(Request $request): RedirectResponse
    {
        $transaction = $this->tubaPay->transactions()->createTransaction(...);

        return redirect($transaction->transactionLink);
    }
}
```

## Webhook Handling

The package automatically registers a webhook route at `/webhooks/tubapay` (configurable).

### Listening to Events

Register listeners in your `EventServiceProvider`:

```php
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;
use Core45\LaravelTubaPay\Events\TransactionStatusChanged;
use Core45\LaravelTubaPay\Events\PaymentReceived;
use Core45\LaravelTubaPay\Events\InvoiceRequested;

protected $listen = [
    TransactionStatusChanged::class => [
        HandleTubaPayStatusChange::class,
    ],
    PaymentReceived::class => [
        HandleTubaPayPayment::class,
    ],
    RecurringOrderRequested::class => [
        CreateMonthlyOrderFromTubaPayRequest::class,
    ],
];
```

### Example Listener

```php
namespace App\Listeners;

use Core45\LaravelTubaPay\Events\TransactionStatusChanged;

class HandleTubaPayStatusChange
{
    public function handle(TransactionStatusChanged $event): void
    {
        $orderId = $event->getExternalRef();
        $status = $event->getStatus();

        $order = Order::where('external_ref', $orderId)->first();

        if ($event->isAccepted()) {
            $order->markAsPaid();
        } elseif ($event->isRejected()) {
            $order->markAsFailed();
        }
    }
}
```

### Webhook Persistence and Idempotency

When transaction tracking is enabled, the package now also stores:

- Webhook idempotency state in `tubapay_webhook_events`
- Merchant payment notifications in `tubapay_payments`
- Recurring order requests in `tubapay_recurring_requests`

Duplicate webhook deliveries with the same TubaPay `commandType:commandRef` are acknowledged without dispatching duplicate Laravel events after the first successful processing pass. Failed and stuck processing events can be retried based on the configured lease and max-attempt settings.

Recurring requests dispatch both `InvoiceRequested` and `RecurringOrderRequested`. Prefer `RecurringOrderRequested` for new code:

```php
use Core45\LaravelTubaPay\Events\RecurringOrderRequested;

final class CreateMonthlyOrderFromTubaPayRequest
{
    public function handle(RecurringOrderRequested $event): void
    {
        $externalRef = $event->getExternalRef();
        $paymentScheduleId = $event->getPaymentScheduleId();
        $position = $event->getFirstPosition();

        // Find the local order and create the application-specific monthly order.
    }
}
```

### Status Mapping

Configure an optional status map for application listeners:

```php
'status_map' => [
    'accepted' => 'paid',
    'rejected' => 'failed',
],
```

Use the helper in your listener:

```php
use Core45\LaravelTubaPay\Services\TubaPayStatusMapper;

$mappedStatus = app(TubaPayStatusMapper::class)->map($event->getStatus());
```

### Optional Default Listeners

If your order model can implement a small TubaPay integration contract, the package can auto-register default listeners for accepted, rejected, payment, and recurring request events.

Bind an order resolver:

```php
use App\Models\Order;
use Core45\LaravelTubaPay\Contracts\TubaPayOrderResolver;
use Core45\LaravelTubaPay\Contracts\TubaPayTransactable;

final class OrderResolver implements TubaPayOrderResolver
{
    public function resolve(string $externalRef): ?TubaPayTransactable
    {
        return Order::where('external_ref', $externalRef)->first();
    }
}
```

Implement `TubaPayTransactable` on the resolved order object:

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
        // Persist to your order history table.
    }

    public function isTubaPayPaid(): bool
    {
        return $this->status === 'paid';
    }
}
```

Then enable listener registration:

```env
TUBAPAY_AUTO_LISTENERS=true
```

## Transaction Tracking

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=tubapay-migrations
php artisan migrate
```

Use the `TubaPayTransaction` model to track transactions:

```php
use Core45\LaravelTubaPay\Models\TubaPayTransaction;

// Find by external reference
$transaction = TubaPayTransaction::findByExternalRef('ORDER-123');

// Query by status
$pending = TubaPayTransaction::pending()->get();
$successful = TubaPayTransaction::successful()->get();
$failed = TubaPayTransaction::failed()->get();

// Query by customer
$customerOrders = TubaPayTransaction::forCustomer('jan@example.com')->get();

// Check status helpers
if ($transaction->isSuccessful()) {
    // Payment accepted
}
```

## Blade Components

Display status badges:

```blade
<x-tubapay::status-badge :status="$transaction->status" />
```

Render checkout controls:

```blade
<x-tubapay::installment-selector :options="$options" />
<x-tubapay::consent-checkboxes :options="$options" />
```

Render official content returned by the SDK:

```blade
<x-tubapay::top-bar :content="$topBarContent" />
<x-tubapay::popup :content="$popupContent" />
```

## Console Commands

```bash
php artisan tubapay:check-connection
php artisan tubapay:prune-selections
```

## Translations

Translations are available in 6 languages: en, pl, de, es, fr, it.

Publish translations to customize:

```bash
php artisan vendor:publish --tag=tubapay-lang
```

## Agreement Statuses

| Status | Description | `isPending()` | `isSuccessful()` | `isFailed()` |
|--------|-------------|---------------|------------------|--------------|
| `draft` | Initial state | ✓ | | |
| `registered` | Application submitted | ✓ | | |
| `signed` | Documents signed | ✓ | | |
| `accepted` | Approved - payment confirmed | | ✓ | |
| `rejected` | Application rejected | | | ✓ |
| `canceled` | Canceled by customer | | | ✓ |
| `terminated` | Terminated by system | | | ✓ |
| `withdrew` | Customer withdrew | | | ✓ |
| `repaid` | Fully repaid | | ✓ | |
| `closed` | Agreement closed | | ✓ | |

## Configuration Options

See `config/tubapay.php` for all configuration options including:

- Webhook route customization
- Cache settings for token storage
- Database settings for transaction tracking
- Checkout defaults and selection TTL
- Optional UI route registration and content cache TTL
- Optional default listener registration
- Webhook idempotency lease and retry settings
- Optional status mapping
- Integration metadata sent to TubaPay
- Logging options

## Testing

```bash
composer test
composer phpstan
```

## License

MIT License. See LICENSE file for details.
