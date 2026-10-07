# Changelog

All notable changes to this library are documented here. It follows [Semantic Versioning](https://semver.org).

## 0.2.0 - 2026-10-07

- The account's support phone number (`$account->supportPhone`) and business details (`$account->business`): the kind of entity, legal name, registration number, tax id, address, industry, product description, expected monthly volume and phone number, with `BusinessType`, `Industry` and `MonthlyVolume` enums. A value the API adds later reads as `null` instead of failing, and `get()` still returns it.
- An `Asset` enum of every coin the API knows, which `asset` and `accepted_assets` take as well as strings.
- A write that runs into an earlier one still being processed under the same idempotency key now waits for it and is tried again, instead of throwing a `ConflictException`.
- A `maxRetryAfter` option for the longest `Retry-After` that is waited out before trying again, 10 seconds as before. A longer one is still thrown straight away, and `retryAfter()` now works on every API exception, not only `RateLimitException`.
- An empty id throws an `InvalidArgumentException`, instead of requesting `/v1/charges/` or `/v1/charges//cancel`.
- An API key that does not start with `vp_live_` or `vp_test_` is refused when the client is created, rather than by the API on the first request.
- `$transaction->requiredConfirmations` is always an integer.
- The README's webhook guidance is corrected: the full retry schedule, skipping duplicates by `eventId`, what deliveries leave out, and keeping the webhook route out of CSRF protection on Laravel 13.
- PHP 8.2 or later is required.

## 0.1.1 - 2026-09-26

- The customer's details on every charge (`$charge->customer`), including the IP address and browser a checkout was started from, and a `customer` parameter to pass them when you create a charge.
- The transfer a `charge.payment_detected` or `charge.late_payment` delivery is about, as `$event->transaction`.

## 0.1.0 - 2026-09-24

First release.

- A client for every endpoint of the v1 API: account, charges, payment links, invoices and events.
- Typed resources, with every attribute the API sends kept and readable.
- Pages and an auto-paging iterator for lists.
- Automatic retries of network errors, rate limits and outages, with idempotency keys so a retried write is never carried out twice.
- An exception for each kind of API error.
- Webhook signature verification and parsing, for plain PHP and PSR-7 requests.
