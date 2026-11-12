# Changelog

All notable changes to this library are documented here. It follows [Semantic Versioning](https://semver.org).

## Unreleased

- An answer cut off or timed out after its headers arrived, which Guzzle 8 throws as a `ResponseTransferException` or `ResponseTimeoutException`, is retried like a network error, for reads and for writes carrying an idempotency key, which every write does while retries are on. Before, it was thrown as a `ConnectionException` at once.
- Cloudflare's 520 to 524, its answers when it gets nothing from VeliraPay, are retried like 502, 503 and 504.
- `ConnectionException::$idempotencyKey`: the key a write was sent with, the one the library made up included, so the call can be repeated safely once the API answers again.
- `ConflictException::wasReplayed()`: whether the API answered from an earlier request with the same idempotency key. It is what a retried `apiKeys->create()`, `webhookEndpoints->create()` or `rotateSecret()` gets when its first answer was lost: the write went through, but the key or signing secret it carried is shown once and gone. Their docblocks and the README say how to recover.
- A `DateTimeInterface` given as `created_from`, `created_to` or `expires_at` is sent as the same moment in UTC. It went out with its own offset, which the API read as a time in UTC, so a date outside UTC moved a charge filter, a coupon's expiry or a payment link's by hours. An invoice's `due_at` is still sent as the day it falls on where it is, and the activity log's `from` and `to` now take a `DateTimeInterface` the same way, where the API ignored a full timestamp.
- A float given as an amount, a price, a quantity, a discount or a tolerance is sent as a plain decimal string, `0.00005` as `'0.00005'`. PHP writes small and large floats with an exponent, as `5.0e-5`, which the API refuses, so a small refund or simulated payment given as a float failed. Strings and integers are sent as before, and so is `metadata`, whose numbers stay numbers.
- Enums for the rest of the values the API uses: `Currency`, `MemberRole` (with `assignable()`, the four roles an invitation or a role change can give), `AddressNetwork`, `CheckoutAppearance`, `CheckoutLocale`, `WebhookDeliveryStatus`, `PlanName`, `AccountType` and `ActivityAction`. Every parameter that takes one of these values takes its case as well as a string, `payoutAddresses->update()` and `delete()` included, and the docblocks of `webhookEndpoints->create()`, `update()` and `deliveries()` and of `billing->startProPayment()` now admit the `EventType`, `WebhookDeliveryStatus` and `Asset` cases they always took. `charges->create()` still types `currency` as a string. What the API sends back stays a string; `$payoutAddress->addressNetwork()` gives the case, or null for a network newer than the library.
- `members->update()` no longer offers the `member` role in its docblock, which the API has refused since 0.6.5.
- `account->update()` documents every field it takes, in the shape `account->retrieve()` reads, with `null` where a field can be cleared and the enum cases where the API takes one, so editors complete them and static analysis checks their types. It sends what it is given as before. Code that passes it an `array<string, mixed>` may now need that array typed to satisfy PHPStan.
- The rest of the overview, which was only read with `get()`: `$overview->period` (`OverviewPeriod`, its first and last day), `funnel` (`OverviewFunnel`, how the period's attempts to pay ended, with `OverviewFunnelAsset` per coin), `attention` (`OverviewAttention`: `OverviewChargeAmount` short and late payments, `OverviewWaitingPayouts` per coin and failing endpoints), `assets` (`OverviewAsset`), `topPaymentLinks` (`OverviewPaymentLink`), `setup` (`OverviewSetup`), `daily` (`OverviewDay`) and `mode`. Amounts stay strings, days are `DateTimeImmutable` at midnight UTC, and every new constructor parameter comes last with a default.
- A page keeps the meta its answer carried: `$page->meta` as sent, `$page->mode` for the lists that say, the coins on offer and the activity log, and `$page->currencies` on `assets->list()`, every currency prices can be asked in. `ApiResponse::meta()` reads the meta of any answer, such as `api_payout_address_changes` after `payoutAddresses->list()` and the mode after `payouts->summary()`, which keep returning plain arrays.

## 0.6.6 - 2026-11-12

- `Asset::BTC_LIGHTNING`, `Asset::ETH_BASE`, `Asset::ETH_ARBITRUM` and `Asset::POL`, which the API defines. A coin in the enum is not necessarily on offer: `assets->list()` and `$account->acceptedAssets` say which can be paid right now.
- `$charge->networkSurcharge` and `$supportedAsset->networkSurcharge`: what a coin such as USDT on Tron asks the customer for on top of the price, for the network, which VeliraPay keeps. A charge counts it in `assetAmount`. It is null for a coin without one.
- `$charge->paymentRequest`: the Lightning invoice (BOLT11) of a charge in `BTC_LIGHTNING`, whose `depositAddress` is then the invoice's payment hash, and null for every other coin.
- `$overview->volumeChangePercent`, `sentVolume` and `conversionRatePercent`: how the volume changed against the 30 days before, what the payouts confirmed in the period came to, and the share of the period's paid, expired and canceled charges that were paid. Each percentage is null when there is nothing to compare.
- The README and the docblocks of `charges->accept()` and `recordRefund()` cover late payments on underpaid charges: a top-up after the rate lock ended arrives as `charge.late_payment` once it confirms and waits to be accepted, `accept()` answers 409 while a payment to an underpaid charge is still confirming, and a refund needs the charge accepted first and never includes the network surcharge.

## 0.6.5 - 2026-11-09

- The `member` role is gone: the API refuses it with a 422, and the people who had it are `finance` now, which may do everything it could. `role` stays a string, so nothing breaks.
- `Asset::USDT_POLYGON`, `Asset::USDT_ARBITRUM` and `Asset::USDC_ARBITRUM`, for the stablecoins VeliraPay switches on chain by chain: `USDC_BASE`, `USDT_POLYGON` and `USDC_POLYGON` are on offer since 2026-11-09, and Arbitrum One's coins follow when it is switched on. `$supportedAsset->asset()` stays null for a coin a version does not know, so older versions keep working.

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
