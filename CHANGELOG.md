# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
