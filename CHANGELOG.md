# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-04-19

### Added

- Added opt-in UI helper routes for checkout options, UI texts, top bar content, and popup content.
- Added Blade components for TubaPay top bar and popup content.
- Added `TubaPayOrderResolver` and `TubaPayTransactable` contracts for host application order integration.
- Added opt-in default listeners for accepted, rejected, payment received, and recurring order requested events.

### Changed

- Updated the required `core45/tubapay-php` version to `^0.2.1`.
- Updated default integration metadata to report `laravel-tubapay` `0.4.0`.

## [0.3.0] - 2026-04-19

### Added

- Added payment webhook persistence in `tubapay_payments`.
- Added recurring order request persistence in `tubapay_recurring_requests`.
- Added webhook event idempotency in `tubapay_webhook_events` with received, processing, processed, and failed states.
- Added `RecurringOrderRequested` event as the clearer alias for recurring request webhooks while keeping `InvoiceRequested`.
- Added `HandlesTubaPayRecurringOrder` contract for application listeners.
- Added `TubaPayStatusMapper` helper for configurable application status mapping.

## [0.2.0] - 2026-04-19

### Added

- Added checkout option generation from TubaPay offers, including installments and consents.
- Added persisted checkout selection storage with pruning command.
- Added checkout transaction service that wraps the SDK transaction API and writes local transaction tracking rows.
- Added Blade components for installment selection and consent checkboxes.
- Added connection check command.
- Added checkout selection metadata fields to tracked transactions.

### Changed

- Updated the required `core45/tubapay-php` version to `^0.2`.

## [0.1.2] - 2026-04-19

### Changed

- Updated the required `core45/tubapay-php` version to `^0.1.2` for current TubaPay v2 token, offer, and transaction compatibility.
- Updated README examples to show current transaction fields for return URLs and accepted consent identifiers.

## [0.1.1] - 2026-04-19

### Changed

- Added Laravel 13 support to the Illuminate package constraints.
- Updated the required `core45/tubapay-php` version to `^0.1.1` to include the corrected TubaPay partner authentication request.
- Added Orchestra Testbench 11 support for Laravel 13 package testing.

## [0.1.0] - 2025-01-15

### Added

- Initial release of Laravel TubaPay integration
- TubaPayServiceProvider with auto-discovery
- TubaPay Facade for convenient static access
- LaravelTokenStorage using Laravel Cache for OAuth token persistence
- Webhook handling:
  - WebhookController with automatic signature verification
  - VerifyTubaPaySignature middleware
  - WebhookReceived event (base event for all webhooks)
  - TransactionStatusChanged event
  - PaymentReceived event
  - InvoiceRequested event
- TubaPayTransaction model for tracking payment lifecycle
- Database migration for transactions table
- Blade component: status-badge
- Translations in 6 languages: English, Polish, German, Spanish, French, Italian
- Comprehensive configuration file
- PHPStan level 8 static analysis
- PHPUnit test suite with Orchestra Testbench
