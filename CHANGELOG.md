# Changelog

All notable changes to this library are documented here. It follows [Semantic Versioning](https://semver.org).

## Unreleased

- `Asset::TRX`, for payments on Tron.

## 0.4.0 - 2026-11-03

- `$velirapay->assets->list()` for the coins on offer and the lowest price each can be charged at, as `SupportedAsset` objects.
- `$velirapay->payouts`: `list()`, `iterator()`, `retrieve()` by the charge's id and `summary()`, for what became of each paid charge, as `Payout` and `PayoutSummary` objects, with a `PayoutStatus` enum.
- `$velirapay->webhookEndpoints->list()` to see whether an endpoint can receive deliveries, as `WebhookEndpoint` objects.
- `$velirapay->charges->send()`, `accept()` and `simulate()`: email a pending charge to its customer, accept an underpaid charge or a late payment as paid, and pay a test charge without coins.
- `charges->create()` also takes `custom_fields`, `success_url`, `cancel_url`, `success_message` and `send_email`, and `$charge->successUrl`, `cancelUrl` and `successMessage` read them back.
- New filters on `list()` and `iterator()`: `asset`, `search`, `created_from` and `created_to` for charges, `search` and `overdue` for invoices, `status` and `search` for payment links, and `search` for coupons.
- `Asset::BNB` and `Asset::USDC_BSC`, for payments on BNB Chain.
- The README starts from the recommended way to charge a customer: a charge, with what you already know about them, rather than a payment link per customer.
- `$event->isVerification()`, `$event->challenge()` and `$event->challengeResponse()` for the `webhook.verification` challenge (`Webhook::VERIFICATION_EVENT`) VeliraPay now sends an endpoint when it is added or its URL changes. The endpoint receives nothing else until it answers with the challenge.
- The README covers the challenge, HTTPS-only endpoints, and the second `v1` signature deliveries carry for a day after the signing secret is rotated.

## 0.3.0 - 2026-10-15

- Coupons: `$velirapay->coupons` creates, retrieves, updates, lists and deletes them, as `Coupon` objects.
- A `coupon` parameter to apply one when you create a charge, and `$charge->coupon` and `$charge->discountAmount` on every charge, webhooks included.
- The README covers the limits the API now enforces: 300 charges per account and mode in 30 minutes, and 100 emails to customers an hour, shared with the other accounts of the same owner. Past either, a `RateLimitException` says how long to wait.

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
