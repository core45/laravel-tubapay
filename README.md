# Laravel TubaPay

A Laravel integration for TubaPay BNPL (Buy Now, Pay Later) payment solutions.

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- core45/tubapay-php ^0.1

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

// Create transaction with selected installments
$transaction = TubaPay::transactions()->createTransaction(
    customer: $customer,
    item: $item,
    installments: 6,
    callbackUrl: route('tubapay.webhook'),
    externalRef: 'ORDER-123',
);

// Redirect customer to payment page
return redirect($transaction->transactionLink);
```

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
- Logging options

## Testing

```bash
composer test
composer phpstan
```

## License

MIT License. See LICENSE file for details.
