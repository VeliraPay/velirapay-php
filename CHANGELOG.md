# Changelog

All notable changes to this library are documented here. It follows [Semantic Versioning](https://semver.org).

## 0.6.5 - 2026-11-09

- The `member` role is gone: the API refuses it with a 422, and the people who had it are `finance` now, which may do everything it could. `role` stays a string, so nothing breaks.
- `Asset::USDT_POLYGON`, `Asset::USDT_ARBITRUM` and `Asset::USDC_ARBITRUM`, for the stablecoins on Polygon and Arbitrum One, for the stablecoins VeliraPay switches on chain by chain: `USDC_BASE`, `USDT_POLYGON` and `USDC_POLYGON` are on offer since 2026-11-09, and Arbitrum One's coins follow when it is switched on. `$supportedAsset->asset()` stays null for a coin a version does not know, so older versions keep working.

## 0.6.4 - 2026-11-08

- `webhookEndpoints->retrieveDelivery()` reads one delivery with `$delivery->attemptLog`, every attempt to send it, the latest first, as `WebhookDeliveryAttempt` objects: the URL and headers sent, the endpoint's status, headers and the first 2,000 characters of its answer, how long it took and the error. `$delivery->payload` is the JSON body it carried, or null for a key without `events:read`. Listed deliveries leave both out, as before.
- Members and invitations can have the new `finance`, `support` and `viewer` roles beside `owner`, `admin` and `member`; `role` stays a string, so nothing breaks.

## 0.6.3 - 2026-11-07

- An `ApiKeyPermission` enum of every permission an API key can have, which `apiKeys->create()` and `update()` take as well as strings.
- `$endpoint->previousSecretExpiresAt` on the answer to `webhookEndpoints->rotateSecret()`: until when the secret it replaced still signs deliveries.
- `activity->list()` and `iterator()` document the `network` filter, which leaves out what belongs to no mode.
- The README and `$account->business` say that the API only sends the account's business details to a key with the sensitive `account:write` permission. For any other key every business field is `null`, and `$account->get('business')` is `null` too; earlier versions read it the same way, so nothing breaks.
- The README says that adding an endpoint, giving one a new `url`, `verify()`, `test()` and `retryDelivery()` share a limit of 10 a minute per key, and that `assets->list()` returns a single page rather than a plain array.

## 0.6.2 - 2026-11-06

- `$account->checkout['default_coin']`: the coin the checkout starts on, or null when the merchant has not picked one (the checkout then starts on Litecoin where it is on offer).

## 0.6.1 - 2026-11-06

- Account resources expose the current checkout, invoice and payment settings, dark logo and legal links.
- Negative webhook signature tolerances are rejected instead of silently disabling replay protection.
- Corrected the guidance for secret-bearing idempotent responses, charge email validation and unpaged lists.

## 0.6.0 - 2026-11-06

- `$velirapay->account->update()` and `updatePaymentSettings()`, and the plan on every account: `$account->plan`, `feePercent`, `proEndsAt` and `isPro()`.
- `$velirapay->overview->retrieve()`, `$velirapay->activity->list()` and `iterator()`, and `$velirapay->payoutAddresses`: `list()`, `update()` and `delete()`, as `Overview`, `Activity` and `PayoutAddress` objects.
- `$velirapay->webhookEndpoints`: `retrieve()`, `create()`, `update()`, `delete()`, `verify()`, `test()`, `rotateSecret()`, `deliveries()` and `retryDelivery()`. `$endpoint->secret` carries the signing secret in the answers that add or rotate it, and `$endpoint->verificationError` says why one did not answer the challenge. Deliveries are `WebhookDelivery` objects.
- `$velirapay->apiKeys`: `list()`, `iterator()`, `create()`, `update()` and `revoke()`, as `ApiKey` objects; `$apiKey->key` is only in the answer that creates it.
- `$velirapay->members` (`list()`, `iterator()`, `update()`, `remove()`) and `$velirapay->invitations` (`list()`, `iterator()`, `create()`, `cancel()`), as `Member` and `Invitation` objects.
- `$velirapay->billing->startProPayment()` for a month of Pro in crypto, as a `ProPayment`.
- Most of these need a permission ticked on a restricted key; the README says which.

## 0.5.0 - 2026-11-03

- `Asset::TRX`, for payments on Tron.
- `EventType::PayoutSent` and `EventType::PayoutConfirmed` for the `payout.sent` and `payout.confirmed` webhooks, and `$event->payout` with the payout they are about.

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
