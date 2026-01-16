# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
