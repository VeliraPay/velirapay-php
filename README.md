# VeliraPay PHP

[![Tests](https://github.com/VeliraPay/velirapay-php/actions/workflows/tests.yml/badge.svg)](https://github.com/VeliraPay/velirapay-php/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/velirapay/velirapay-php)](https://packagist.org/packages/velirapay/velirapay-php)
[![License](https://img.shields.io/packagist/l/velirapay/velirapay-php)](LICENSE)

The official PHP library for [VeliraPay](https://velirapay.com), the crypto payment processor. It covers the whole v1 API, in 16 groups of endpoints: your account and its payment settings, the coins on offer, charges, payment links, invoices, coupons, payouts, payout addresses, events, webhook endpoints, the overview, the activity log, API keys, members, invitations and Pro billing. It also verifies the webhooks VeliraPay sends you.

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Which one to use](#which-one-to-use)
- [Supported coins](#supported-coins)
- [Charges](#charges)
- [Payment links](#payment-links)
- [Invoices](#invoices)
- [Coupons](#coupons)
- [Payouts](#payouts)
- [Events](#events)
- [Webhook endpoints](#webhook-endpoints)
- [Your account](#your-account)
- [Lists and pagination](#lists-and-pagination)
- [Constants](#constants)
- [Webhooks](#webhooks)
- [Errors](#errors)
- [Retries and idempotency](#retries-and-idempotency)
- [Test mode](#test-mode)
- [Configuration](#configuration)
- [Testing your integration](#testing-your-integration)
- [Laravel](#laravel)
- [Development](#development)

## Requirements

- PHP 8.2 or later, with the `ctype` extension (enabled by default)
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client. Laravel, Symfony and most frameworks already have one, and [Guzzle](https://docs.guzzlephp.org) is used when it is installed.

## Installation

```bash
composer require velirapay/velirapay-php
```

When Composer asks whether to trust `php-http/discovery`, answer yes: it installs an HTTP client if your project has none. You can also install Guzzle yourself:

```bash
composer require guzzlehttp/guzzle
```

## Getting started

Create an API key in the dashboard under **Developers → API keys**. Keep it on your server: anyone holding it can create charges and read your payments.

```php
use VeliraPay\VeliraPayClient;

$apiKey = getenv('VELIRAPAY_API_KEY') ?: throw new RuntimeException('Set VELIRAPAY_API_KEY.');
$velirapay = new VeliraPayClient($apiKey);

$charge = $velirapay->charges->create([
    'amount' => '49.90',
    'currency' => 'EUR',
    'asset' => 'BTC',
    'customer' => ['email' => 'ada@example.com'],
    'metadata' => ['order_id' => '1042'],
    'success_url' => 'https://example.com/orders/1042',
]);

$checkoutUrl = $charge->checkoutUrl ?? throw new RuntimeException('The charge has no checkout URL.');

header('Location: '.$checkoutUrl, true, 303);
```

That is the way to charge a customer: create a charge with what you already know, and send them to its checkout page. They pay there, and VeliraPay tells your server about it with a [webhook](#webhooks). `checkoutUrl` is nullable because the charges in webhooks leave it out; a charge the API answers with always has one.

Amounts are always strings, such as `'49.90'`, never floats, so no precision is lost. Use [bcmath](https://www.php.net/manual/en/book.bc.php) or a money library to do arithmetic on them. An amount you pass as a float is sent as a plain decimal string rounded to 14 significant digits, as the API reads a number, so `0.00005` goes as `'0.00005'` and `0.1 + 0.2` as `'0.3'`. Pass an amount that needs more digits, such as `'0.123456789012345678'` ETH, as a string.

Dates can be given as strings or as `DateTimeInterface` objects. A date and time is sent as the same moment in UTC, whatever its time zone, and a date the API reads as a day, an invoice's `due_at` or the activity log's `from` and `to`, as the day it falls on where it is.

Each group of endpoints is a property, such as `$velirapay->charges`, and also a method, `$velirapay->charges()`, which facades need.

## Which one to use

| When | Use | |
| --- | --- | --- |
| You are asking one customer for one payment | A [charge](#charges) | The default. It has one checkout page for that one payment, with the rate locked for 30 minutes. |
| The customer should choose the coin, or pays later | An [invoice](#invoices) | It needs their name and email, has no timer, can be itemised and reminded, and lets them pick the coin on its page. |
| Many customers pay through the same public page | A [payment link](#payment-links), created once | A product sold again and again, a tip jar, a link on your site. |

Do not create a new payment link for each customer or each order.

A charge needs its coin up front, because its exchange rate, amount and deposit address depend on it. Ask the customer, on your own page, and offer them the coins from [`$velirapay->assets->list()`](#supported-coins).

## Supported coins

`assets->list()` gives the coins that can be paid in the key's mode, whether your account takes each, and the lowest price a charge in each can have.

```php
foreach ($velirapay->assets->list(['currency' => 'EUR']) as $asset) {
    $asset->id;                // "USDT": what to pass as asset, and $asset->asset() is the Asset case
    $asset->name;              // "Tether USD"
    $asset->network;           // "Ethereum (ERC-20)", the network the customer has to send it on
    $asset->accepted;          // whether your account takes it, which needs a payout address for that network
    $asset->minimumAmount;     // "10", in the coin, or null when it has no minimum of its own
    $asset->minimumPrice;      // "8.71", the lowest price in EUR right now
    $asset->exchangeRate;      // "0.8709"
    $asset->confirmationWait;  // "about a minute"
    $asset->networkSurcharge;  // null, or what a charge in it asks on top of the price, in the coin
}
```

USDT and USDC on Ethereum start at 10 of the token, because paying one out costs enough in gas that a smaller payment would leave you with little. A minimum is in the coin, so `minimumPrice` moves a little with the market: leave some room. Below it, `charges->create()` throws a `ValidationException` on `asset`.

A coin with a `networkSurcharge`, such as USDT on Tron, asks the customer for that much more than the price, to pay for passing the payment on, so tell them before they choose it. VeliraPay keeps it: it is not part of your payout, and a refund cannot include it. A charge in such a coin counts it in `assetAmount` and gives it again as `$charge->networkSurcharge`.

Without `currency`, prices are in your account's default currency. The list is not paged: it is one page, whose `mode` is the key's mode and whose `currencies` are every currency prices can be asked in.

## Charges

A charge is how you ask a customer for a payment, in one coin. The exchange rate is locked when it is created, and the customer has 30 minutes to pay.

```php
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\ChargeStatus;

// For an amount you set, with whatever you already know.
$charge = $velirapay->charges->create([
    'amount' => '150.00',
    'currency' => 'USD',
    'asset' => Asset::USDC, // USD Coin on Ethereum
    'description' => 'Pro plan, 1 year',
    'metadata' => ['order_id' => '1042'],
    'customer' => ['email' => 'ada@example.com', 'name' => 'Ada Lovelace'],
    'custom_fields' => [['label' => 'Company', 'value' => 'Analytical Engines']], // shown with the charge in the dashboard
    'success_url' => 'https://example.com/orders/1042', // a button back once they have paid
    'cancel_url' => 'https://example.com/cart',         // a link back from the checkout
    'success_message' => 'Thank you, your licence is on its way.', // on the page and in the receipt
    'send_email' => true,                               // email them the payment page straight away
]);

// For one of your payment links: the currency comes from the link, and so does the price when it is fixed.
$charge = $velirapay->charges->create(['payment_link' => 'p4n8r2t6y1ua', 'asset' => 'ETH']);

// With one of your coupons: fiatAmount is the lower price, and discountAmount what the coupon took off.
$charge = $velirapay->charges->create(['payment_link' => 'p4n8r2t6y1ua', 'asset' => 'ETH', 'coupon' => 'SPRING10']);
$charge->coupon;          // "SPRING10"
$charge->discountAmount;  // "15.00"

$charge = $velirapay->charges->retrieve('k3v9x2m7q8wz');

$charge->status;          // "pending", "underpaid", "paid", "expired" or "canceled"
$charge->isPaid();
$charge->assetAmount;     // "0.0025", what the customer has to send
$charge->receivedAmount;  // what arrived so far
$charge->depositAddress;
$charge->transactions;    // list of VeliraPay\Resources\Transaction
$charge->metadata;        // ['order_id' => '1042']

$paid = $velirapay->charges->list(['status' => ChargeStatus::Paid]);
$hers = $velirapay->charges->list(['search' => 'ada@example.com', 'created_from' => '2026-10-01', 'asset' => Asset::BTC]);

// Email the customer the payment page, if you did not pass send_email.
$velirapay->charges->send('k3v9x2m7q8wz');

// Only while the charge is pending and no transfer to it has been seen.
$velirapay->charges->cancel('k3v9x2m7q8wz');

// Settle an underpaid charge, or a late payment on an expired or canceled one, for what arrived;
// a 409 while a payment to an underpaid charge is still confirming.
$velirapay->charges->accept('k3v9x2m7q8wz');

// Record a refund you sent from your own wallet. An underpaid charge or a late payment is accepted
// first, and the network surcharge is never refundable.
$velirapay->charges->recordRefund('k3v9x2m7q8wz', [
    'amount' => '0.0005',
    'txid' => '7d1c5e9a…',
    'reason' => 'Returned item',
]);
```

`send()` needs a customer email and only works while the charge is pending, or it throws a `ConflictException`. Creating a charge with `send_email` but no customer email throws a `ValidationException` on `customer_email` and creates nothing. A charge can be emailed once every 10 minutes, and the emails count towards the limits under [Invoices](#invoices). In test mode, charge emails can only go to members of your account.

`asset` takes a `VeliraPay\Enums\Asset` case or its value, such as `'BTC'`. The enum lists every coin the API knows, but not all of them can be paid in at any given time: a coin is only offered while VeliraPay can also pay it out, and a network can be switched off. `$account->acceptedAssets` lists the coins your account takes right now ([Your account](#your-account)); a charge in any other coin throws a `ValidationException`.

A charge in `Asset::BTC_LIGHTNING` is paid with a Lightning invoice rather than to an address: show the customer `$charge->paymentRequest`, the BOLT11 invoice, as a `lightning:` link or a QR code. Its `depositAddress` is the invoice's payment hash, and `paymentRequest` is `null` for every other coin.

No charge, payment link or invoice asks for less than the lowest price of its currency, below which a payment would be mostly network fees: 0.50 in USD, EUR, GBP and CHF, 0.70 in CAD, 0.80 in AUD and 25.00 in TRY. A lower `amount` throws a `ValidationException` on `amount`, such as `Enter at least €0.50.`, and a coin with a minimum of its own can ask for more ([Supported coins](#supported-coins)).

`metadata` holds up to 20 keys of at most 40 characters each. Values are strings, numbers, booleans or `null`, and a string is at most 500 characters.

An account can create 300 charges in each mode within 30 minutes, or 30 until one of its owner's accounts has been paid in live mode. The limit counts the charges made through the API, the MCP server and the dashboard together, and is shared with the other accounts its owner owns; canceled charges count, paid live ones do not, and payments customers start on the hosted checkout are not counted. Past that, `create()` throws a `RateLimitException` whose `retryAfter()` says how many seconds to wait.

### Customer details

`$charge->customer` holds what is known about who pays, to help you spot fraud before you ship anything. When the customer starts the payment on the hosted checkout, VeliraPay records the IP address and browser they did it from. A charge you create from your server comes from your server rather than the customer, so pass what you know:

```php
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

$charge = $velirapay->charges->create([
    'amount' => '150.00',
    'currency' => 'USD',
    'asset' => 'BTC',
    'customer' => [
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'ip_address' => is_string($ipAddress) ? $ipAddress : null,
        'user_agent' => is_string($userAgent) ? $userAgent : null,
        'reference' => 'cus_1042',           // your own id for the customer
        'phone' => '+44 20 7946 0958',
        'country' => 'GB',                   // ISO 3166 two-letter code
        'metadata' => ['orders' => 7],       // the same limits as the charge's metadata
    ],
]);

$charge->customer->ipAddress;  // "203.0.113.7"
$charge->customer->userAgent;
$charge->customer->country;    // "GB"
$charge->customer->metadata;   // ['orders' => 7]
```

Every field is optional, and any other key is refused with a `ValidationException`. The older `customer_email` parameter still works.

## Payment links

A payment link is one reusable public checkout page, for a fixed price or one the customer chooses, opened by many customers who each pick their own coin. Create it once and share it. To charge one customer, [create a charge](#charges) instead.

```php
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\PricingType;

$link = $velirapay->paymentLinks->create([
    'title' => 'Pro plan',
    'pricing_type' => PricingType::Fixed,
    'amount' => '150.00',
    'currency' => 'EUR',
    'accepted_assets' => [Asset::BTC, Asset::ETH, Asset::USDC], // null accepts every coin the account takes
    'success_url' => 'https://example.com/thanks',
    'custom_fields' => [['label' => 'Company', 'required' => false]],
]);

echo $link->checkoutUrl; // https://velirapay.com/pay/…

$tipJar = $velirapay->paymentLinks->create([
    'title' => 'Tip jar',
    'pricing_type' => PricingType::Open,
    'currency' => 'USD',
    'suggested_amounts' => ['5.00', '10.00', '25.00'],
    'min_amount' => '1.00',
]);

$link = $velirapay->paymentLinks->retrieve('p4n8r2t6y1ua');
$velirapay->paymentLinks->update($link->id, ['title' => 'Pro plan (yearly)']);
$velirapay->paymentLinks->archive($link->id); // stops taking payments
$velirapay->paymentLinks->restore($link->id);

$existing = $velirapay->paymentLinks->list(['status' => 'active', 'search' => 'Pro plan']);
```

## Invoices

An invoice is a bill with a hosted page, where the customer pays in the coin of their choice. Use it for a bill to pay later, or when the customer should choose the coin.

```php
$invoice = $velirapay->invoices->create([
    'customer_name' => 'Grace Hopper',
    'customer_email' => 'grace@example.com',
    'currency' => 'USD',
    'items' => [
        ['description' => 'Consulting', 'quantity' => '10', 'unit_amount' => '100.00'],
        ['description' => 'Hosting', 'quantity' => '1', 'unit_amount' => '250.00'],
    ],
    'due_at' => new DateTimeImmutable('+14 days'),
    'success_url' => 'https://example.com/orders/1042',
    'send_email' => true,
]);

echo $invoice->number;    // INV-0042
echo $invoice->hostedUrl;

$invoice = $velirapay->invoices->retrieve($invoice->id);
$velirapay->invoices->send($invoice->id); // email it, or send a reminder
$velirapay->invoices->void($invoice->id);

$overdue = $velirapay->invoices->list(['overdue' => true]);
$hers = $velirapay->invoices->list(['search' => 'grace@example.com']);
```

Pass `amount` instead of `items` to bill a single total.

An invoice can be emailed once every 10 minutes, and an account can send 100 emails to customers an hour, or 10 until one of its owner's accounts has been paid in live mode: invoices, charges emailed to their customers and refund notices together, shared with the other accounts its owner owns. Past either limit, `send()` throws a `RateLimitException` whose `retryAfter()` says how many seconds to wait, and `create()` with `send_email` throws it and creates nothing. In test mode, invoices are only emailed to members of your account: anyone else is refused with a `ValidationException` on `customer_email`, by `send()` as by `create()` with `send_email`.

## Coupons

A coupon takes a percentage or a fixed amount off a payment. Customers type its code on the hosted checkout of a payment link, you add it to the link's URL so it is already applied (`https://velirapay.com/pay/p4n8r2t6y1ua?coupon=SPRING10`), or you pass it as `coupon` when you [create a charge](#charges).

```php
$coupon = $velirapay->coupons->create([
    'code' => 'SPRING10',          // 3 to 32 letters, digits, dashes or underscores, stored in capitals
    'percent_off' => '10',         // or 'amount_off' => '5.00' with a 'currency'
    'max_redemptions' => 100,      // an open charge holds a use until it is paid, expires or is canceled
    'once_per_customer' => true,   // the customer's email is then required to use it
    'payment_links' => ['p4n8r2t6y1ua'], // empty for every link, and charges made without one
    'expires_at' => '2026-12-31',  // a bare date ends at 23:59:59 UTC
]);

$coupon->id;              // "SPRING10": the code is the id, and is matched in any case
$coupon->timesRedeemed;   // how many paid charges used it

$coupon = $velirapay->coupons->retrieve('spring10');

$velirapay->coupons->update('SPRING10', ['active' => false]); // pause it
$velirapay->coupons->delete('SPRING10'); // charges that used it keep its code and their discount

$found = $velirapay->coupons->list(['search' => 'spring']);
```

`currency` is required with `amount_off` or `min_amount`, and the coupon then only works on payments in that currency. Once a coupon exists, its code, discount, currency and `min_amount` cannot change: `update()` takes `max_redemptions`, `once_per_customer`, `payment_links` (which replaces the whole list), `active` and `expires_at`.

A coupon that cannot be used throws a `ValidationException` on `coupon` from `charges->create()`, saying why: it does not exist in this mode, is paused, has expired, is limited to other links, is for another currency, needs a higher price, would leave nothing to pay or is used up, or it is once per customer and the charge has no customer email or that customer already used it.

## Payouts

Every paid charge has one payout: what came in, what was kept, what you are owed, what actually landed and the transaction that carried it. These only read: payouts go out on their own, and nothing here can raise, redirect or reverse one.

```php
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\PayoutStatus;

$payout = $velirapay->payouts->retrieve('k3v9x2m7q8wz'); // by the charge's id; 404 until it is paid

$payout->status;       // "pending", "sending", "sent", "failed" or "manual"
$payout->isSent();     // confirmed: the money is in your wallet
$payout->grossAmount;  // what the customer paid
$payout->feeAmount;    // what was kept
$payout->netAmount;    // what you are owed, before the chain's own fee
$payout->networkFee;   // what the chain charged
$payout->sentAmount;   // what therefore reached you
$payout->payoutTxid;   // the transaction, and $payout->explorerUrl its page on a block explorer
$payout->lastError;    // why the last try did not work, or why it is held
$payout->nextAttemptAt;

$failed = $velirapay->payouts->list(['status' => PayoutStatus::Failed]);
$tether = $velirapay->payouts->list(['asset' => Asset::USDT, 'search' => '0x9a4f']); // a charge id or a transaction hash

foreach ($velirapay->payouts->summary() as $coin) {
    $coin->asset;           // one row per coin you have been paid in
    $coin->paidOutAmount;   // what has reached you
    $coin->inFlightAmount;  // what is on its way
    $coin->owedAmount;      // what has not left yet
    $coin->feeAmount;
    $coin->waitingForAddress; // how many payouts wait for you to give an address
}
```

## Events

Every change to a charge or an invoice is recorded as an event, such as `charge.paid` or `invoice.viewed`. Webhooks carry the same events.

```php
use VeliraPay\Enums\EventType;

$events = $velirapay->events->list(['charge' => 'k3v9x2m7q8wz']);
$payments = $velirapay->events->list(['type' => EventType::ChargePaymentDetected]);

$event = $velirapay->events->retrieve('9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b');
$event->is(EventType::ChargePaid);
$event->details; // what was recorded, such as the amount and txid of a payment or a refund
$event->charge;  // the charge as it is now, without its timeline
```

## Webhook endpoints

List them to see from your code why deliveries are not arriving:

```php
use VeliraPay\Enums\WebhookDeliveryStatus;

foreach ($velirapay->webhookEndpoints->list() as $endpoint) {
    $endpoint->url;
    $endpoint->events;                // the event types it takes
    $endpoint->active;
    $endpoint->verified;              // it receives nothing until it has answered the challenge
    $endpoint->isReceiving();         // both of those
    $endpoint->deliveriesCount;       // of the last 30 days
    $endpoint->failedDeliveriesCount;
}

$endpoint = $velirapay->webhookEndpoints->retrieve('12');

foreach ($velirapay->webhookEndpoints->deliveries('12', ['status' => WebhookDeliveryStatus::Failed]) as $delivery) {
    $delivery->event;          // "charge.paid"
    $delivery->responseStatus; // what the endpoint last answered
    $delivery->error;
}
```

Retrieve one delivery to see every attempt to send it, the latest first, and the JSON body it carried:

```php
$delivery = $velirapay->webhookEndpoints->retrieveDelivery('12', $delivery->id);

foreach ($delivery->attemptLog as $attempt) {
    $attempt->requestHeaders;  // the signatures cut out, the timestamp they signed kept
    $attempt->responseStatus;  // null when nothing answered
    $attempt->responseHeaders; // a few dozen at most, never cookies
    $attempt->responseBody;    // the first 2,000 characters
    $attempt->durationMs;
    $attempt->error;           // such as a timeout
}

$delivery->payload; // the JSON body, or null for a key without events:read
```

Attempts are kept as long as their delivery, 30 days. A delivery sent before they were kept, on 8 November 2026, has an empty `attemptLog`.

A key with the sensitive `webhook_endpoints:write` permission ticked can also add and change them. A new endpoint is sent its verification challenge at once, and the answer that adds it, or rotates its secret, is the only one that carries the signing secret:

```php
use VeliraPay\Enums\EventType;

$endpoint = $velirapay->webhookEndpoints->create([
    'url' => 'https://example.com/webhooks/velirapay',
    'events' => [EventType::ChargePaid, EventType::InvoicePaid],
]);

$endpoint->secret;            // "whsec_…", store it now: it is never returned again
$endpoint->verificationError; // null once the endpoint answered the challenge

$velirapay->webhookEndpoints->update('12', ['active' => false]);
$velirapay->webhookEndpoints->verify('12');               // send the challenge again
$velirapay->webhookEndpoints->test('12');                 // a sample charge.paid, marked test
$velirapay->webhookEndpoints->rotateSecret('12')->secret; // the old one keeps signing for a day, until ->previousSecretExpiresAt
$velirapay->webhookEndpoints->retryDelivery('12', $delivery->id);
$velirapay->webhookEndpoints->delete('12');
```

Adding an endpoint, giving one a new `url`, `verify()`, `test()` and `retryDelivery()` each send the endpoint a request, so together they are limited to 10 a minute per key. Past that the API answers 429: a short wait is [retried](#retries-and-idempotency) for you, and a longer one throws a `RateLimitException` whose `retryAfter()` says how many seconds to wait.

The secret is never kept for a retry. When the answer to `create()` or `rotateSecret()` is lost, the library sends the request again under the same idempotency key, and the API answers it with a `ConflictException` whose `wasReplayed()` is `true`: the endpoint was added, or its secret replaced, but the secret is gone. Find the endpoint with `list()` if it was new, and rotate its secret to get one you can read; after a lost rotation, give your receiver the new secret straight away, since the one it has stops signing:

```php
use VeliraPay\Exceptions\ConflictException;

try {
    $secret = $velirapay->webhookEndpoints->rotateSecret('12')->secret;
} catch (ConflictException $exception) {
    if (! $exception->wasReplayed()) {
        throw $exception;
    }

    $secret = $velirapay->webhookEndpoints->rotateSecret('12')->secret; // the lost one is replaced again, under a new key
}
```

## Your account

```php
$account = $velirapay->account->retrieve();

$account->displayName;
$account->supportEmail;
$account->supportPhone;   // "+442071234567", shown to customers next to the support email
$account->logoDarkUrl;    // the logo for a dark background, or null
$account->termsUrl;
$account->refundPolicyUrl;
$account->acceptedAssets; // ['BTC', 'ETH', …], the coins it takes right now, in the key's mode
$account->mode;           // "live" or "test"
$account->checkout;      // appearance, coin_order, default_coin, success_url, cancel_url, success_message, hide_powered_by
$account->invoices;      // number_prefix, footer, show_business_details
$account->checkoutLocale;
$account->invoiceReminders;
$account->apiPayoutAddressChanges; // whether the owner allows remote payout address changes

$business = $account->business; // as entered under Business details in the dashboard's settings
$business->type;                // a VeliraPay\Enums\BusinessType, such as BusinessType::Company
$business->legalName;
$business->registrationNumber;
$business->taxId;
$business->address->line1;      // also line2, city, postalCode, state and country (ISO 3166 two-letter code)
$business->industry;            // a VeliraPay\Enums\Industry, such as Industry::Software
$business->productDescription;
$business->monthlyVolume;       // a VeliraPay\Enums\MonthlyVolume, such as MonthlyVolume::Under50k ("10k_50k")
$business->phone;               // in international format
```

Every business field is `null` until it is filled in. `type`, `industry` and `monthlyVolume` are also `null` for a value the API adds after this version of the library; `$business->get('industry')` still returns it as sent.

The business details are only sent to a key with the sensitive `account:write` permission, which full access does not include. For any other key every business field is `null`, and so is `$account->get('business')`, while details that were never filled in come as an object of `null` fields.

The plan is on the account too: `$account->plan` ("free" or "pro"), `$account->feePercent` and `$account->proEndsAt`, with `$account->isPro()`.

A key with the sensitive `account:write` or `payment_settings:write` permission can change the account, in the shape it is read in, with `null` to clear a field:

```php
use VeliraPay\Enums\CheckoutAppearance;
use VeliraPay\Enums\CheckoutLocale;
use VeliraPay\Enums\Currency;

$velirapay->account->update(['display_name' => 'Acme', 'checkout' => ['appearance' => CheckoutAppearance::Dark]]);
$velirapay->account->updatePaymentSettings([
    'default_currency' => Currency::EUR,
    'underpayment_tolerance' => '2',
    'checkout_locale' => CheckoutLocale::French,
    'invoice_reminders' => true,
]);
```

### Overview and activity

```php
$overview = $velirapay->overview->retrieve(); // the dashboard's last 30 days; needs overview:read
$overview->volume;                             // in $overview->currency
$overview->volumeChangePercent;                // against the 30 days before; null when nothing was paid then
$overview->sentVolume;                         // what the payouts confirmed in the period came to
$overview->paidCharges;
$overview->conversionRatePercent;              // the share of paid, expired and canceled charges paid; null when none
$overview->period->from;                       // the first day the figures cover, and ->to the last
$overview->funnel->abandoned;                  // attempts to pay that ended before anything arrived, beside started, paid, waiting, incomplete and ->assets per coin
$overview->attention->underpaidCharges;        // what needs a person now, beside latePayments, payoutsWaitingForAddress and failingWebhookEndpoints
$overview->assets[0]->sharePercent;            // each coin's share of the paid charges
$overview->topPaymentLinks[0]->title;          // the links paid most, at most four
$overview->setup->webhookEndpoint;             // which steps of setting up the account are done
$overview->daily[0]->volume;                   // every day of the period, oldest first, with sentVolume and paidCharges
$overview->mode;                               // "live" or "test", the key's mode

foreach ($velirapay->activity->list(['action' => 'sensitive']) as $entry) { // needs activity:read
    $entry->action;    // "api_key.created"
    $entry->actor;     // a member's name, or 'API key "Shop server"'
    $entry->ipAddress;
}
```

### Payout addresses

```php
use VeliraPay\Enums\AddressNetwork;

foreach ($velirapay->payoutAddresses->list() as $address) { // needs payout_addresses:read
    $address->network;          // "bitcoin", "evm", …, and $address->addressNetwork() the AddressNetwork case
    $address->address;          // null while there is none: payouts in its coins wait for one
    $address->assets;           // the coins on offer on that network
    $address->payoutsHeldUntil; // until when payouts to a newly saved address wait
}

$velirapay->payoutAddresses->update(AddressNetwork::Bitcoin, ['address' => 'bc1q…']);
$velirapay->payoutAddresses->delete(AddressNetwork::Evm); // its payouts wait for a new address
```

There is one address per network family: `evm` serves Ethereum and its tokens, while BNB Chain, Base, Arbitrum One and Polygon each have their own. With one entry per network, this `list()` returns a plain array rather than a page. Changing one needs the sensitive `payout_addresses:write` permission and an owner switching on "Let API keys and AI apps change payout addresses" under Payment settings, which `$account->apiPayoutAddressChanges` reads. Owners and admins are emailed about every change, and payouts to a new address wait 48 hours once the account has been paid out.

### API keys, members and Pro

```php
use VeliraPay\Enums\ApiKeyPermission;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\MemberRole;

$apiKey = $velirapay->apiKeys->create([
    'name' => 'Shop server',
    'permissions' => [ApiKeyPermission::ChargesRead, ApiKeyPermission::ChargesWrite], // leave it out for full access
    'allowed_ips' => ['203.0.113.7'],                                                  // leave it out for anywhere
]);
$apiKey->key; // "vp_live_…", shown this once

foreach ($velirapay->apiKeys->list() as $key) {
    $key->prefix;      // "vp_live_8Kq2", never the key itself
    $key->lastUsedAt;
}

$velirapay->apiKeys->update($apiKey->id, ['name' => 'Shop server (EU)', 'allowed_ips' => null]);
$velirapay->apiKeys->revoke($apiKey->id); // refused from its next request

foreach ($velirapay->members->list() as $member) {
    $member->email;
    $member->role; // "owner", "admin", "finance", "support" or "viewer"
}

$velirapay->members->update('3', ['role' => MemberRole::Finance]); // admin, finance, support or viewer
$velirapay->members->remove('3');                                  // also revokes the API keys they made

$invitation = $velirapay->invitations->create(['email' => 'grace@example.com', 'role' => MemberRole::Admin]);
$waiting = $velirapay->invitations->list();
$velirapay->invitations->cancel($invitation->id);

$payment = $velirapay->billing->startProPayment(['asset' => Asset::BTC]); // with a live key
$payment->checkoutUrl; // where to pay a month of Pro
```

`permissions` takes `VeliraPay\Enums\ApiKeyPermission` cases, such as `ApiKeyPermission::ChargesRead`, or their values. `api_keys:read`, `api_keys:write`, `members:read` and `members:write` are sensitive. A key can make or change no key with a permission or an IP address it does not have itself, nor touch a key that reaches further. Only a key an owner made changes a role or removes a member, and owners are never changed this way. `billing:write` comes with full access.

A new key is shown once and never kept for a retry. When the answer to `apiKeys->create()` is lost, the library sends the request again under the same idempotency key, and the API answers it with a `ConflictException` whose `wasReplayed()` is `true`: the key was made, but nobody can read it. Find it with `apiKeys->list()`, revoke it, and create another. Two keys may share a name, so give the key one that no other key has, or the search may revoke a key you still use:

```php
use VeliraPay\Exceptions\ConflictException;

$name = 'Shop server '.bin2hex(random_bytes(3)); // the name alone is not unique, so this one tells the key apart

try {
    $apiKey = $velirapay->apiKeys->create(['name' => $name]);
} catch (ConflictException $exception) {
    if (! $exception->wasReplayed()) {
        throw $exception;
    }

    foreach ($velirapay->apiKeys->list() as $key) {
        if ($key->name === $name) {
            $velirapay->apiKeys->revoke($key->id);
        }
    }

    $apiKey = $velirapay->apiKeys->create(['name' => $name]);
}
```

## Lists and pagination

Paged `list()` methods return one page, newest first (members are oldest first). Payout addresses and payout summaries return plain arrays, and `assets->list()` a single page holding every coin. Pass `page` and `per_page` (up to 100, 25 by default) to move through a paged list:

```php
$page = $velirapay->charges->list(['per_page' => 100]);

foreach ($page as $charge) {
    // …
}

$page->total;
$page->hasMore();
$next = $page->nextPage(); // null on the last page
$page->meta;               // the answer's meta as the API sent it
$page->mode;               // "live" or "test" for the lists that say, the coins on offer and the activity log; otherwise null
```

The two lists that are plain arrays carry meta too, which the last response holds: `$velirapay->lastResponse()?->meta()['mode']` after `payouts->summary()`, and `['api_payout_address_changes']` after `payoutAddresses->list()`, whether an owner lets API keys change payout addresses.

`iterator()` walks every page for you, fetching each one as it is reached:

```php
foreach ($velirapay->charges->iterator(['status' => 'paid']) as $charge) {
    // …
}
```

## Constants

Each set of values the API uses is an enum in `VeliraPay\Enums`, and every parameter that takes one of these values takes its case as well as the string:

| Enum | Values |
| --- | --- |
| `Asset` | The coins, each on its network, such as `Asset::BTC` or `Asset::USDT_TRON` ([Supported coins](#supported-coins)) |
| `Currency` | `USD`, `EUR`, `GBP`, `CAD`, `AUD`, `CHF` and `TRY` |
| `ChargeStatus`, `InvoiceStatus`, `PaymentLinkStatus`, `PayoutStatus` | The states of charges, invoices, payment links and payouts |
| `PricingType` | `fixed` and `open` |
| `EventType` | The events webhooks and the events API report, such as `EventType::ChargePaid` |
| `WebhookDeliveryStatus` | `pending`, `succeeded` and `failed` |
| `MemberRole` | `owner`, `admin`, `finance`, `support` and `viewer`; `MemberRole::assignable()` gives the four an invitation or a role change can give |
| `AddressNetwork` | The network families of payout addresses, such as `AddressNetwork::Bitcoin` or `AddressNetwork::Evm` |
| `ApiKeyPermission` | What a restricted API key may do, such as `ApiKeyPermission::ChargesRead` |
| `ActivityAction` | What the activity log records, such as `ActivityAction::ApiKeyCreated` |
| `CheckoutAppearance`, `CheckoutLocale` | `auto`, `light` and `dark`, and the checkout's languages, such as `CheckoutLocale::French` |
| `PlanName`, `AccountType` | `free` and `pro`, and `personal` and `organization` |
| `BusinessType`, `Industry`, `MonthlyVolume` | The business details of an account |
| `Mode` | `live` and `test` |

```php
use VeliraPay\Enums\Currency;
use VeliraPay\Enums\MemberRole;

$velirapay->assets->list(['currency' => Currency::EUR]);
$velirapay->invitations->create(['email' => 'grace@example.com', 'role' => MemberRole::Finance]);
```

`charges->create()` is typed to take its `currency` as a string, so pass `Currency::EUR->value` there.

What the API sends back stays a string, so a value it adds after this version of the library still reads. Compare with the case's value, as in `$member->role === MemberRole::Admin->value`, or use the readers that give the case or `null`, such as `$supportedAsset->asset()` and `$payoutAddress->addressNetwork()`.

## Webhooks

Add an endpoint in the dashboard under **Developers → Webhooks** and copy its signing secret (`whsec_…`). Each delivery is a POST with a JSON body and these headers:

| Header | |
| --- | --- |
| `X-VeliraPay-Signature` | `t={timestamp},v1={signature}` |
| `X-VeliraPay-Event` | The event type, such as `charge.paid` |
| `X-VeliraPay-Delivery` | The delivery's id |

`Webhook::constructEvent()` checks the signature and parses the body. Pass it the **raw** body: a body decoded and encoded again no longer matches its signature.

```php
use VeliraPay\Exceptions\SignatureVerificationException;
use VeliraPay\Webhooks\Webhook;

$secret = getenv('VELIRAPAY_WEBHOOK_SECRET') ?: throw new RuntimeException('Set VELIRAPAY_WEBHOOK_SECRET.');
$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_VELIRAPAY_SIGNATURE'] ?? null;

try {
    $event = Webhook::constructEvent($payload, is_string($signature) ? $signature : null, $secret);
} catch (SignatureVerificationException $exception) {
    http_response_code(400);
    exit;
}

// Sent when the endpoint is added or moved: it receives nothing else until it answers.
if ($event->isVerification()) {
    header('Content-Type: application/json');
    echo $event->challengeResponse();
    exit;
}

// A sample from the dashboard's "Send test event" button or webhookEndpoints->test(), about a made-up charge.
if ($event->test) {
    http_response_code(204);
    exit;
}

// The same event can arrive more than once: remember this key, and skip the events you have handled.
$key = $event->eventId ?? $event->id;

switch ($event->type) {
    case 'charge.payment_detected':
        // Seen on-chain, not confirmed yet: tell the customer, but do not fulfil.
        $txid = $event->transaction?->txid;
        break;
    case 'charge.paid':
        // A charge made without your metadata, such as one through a payment link, has none.
        $orderId = $event->charge?->metadata['order_id'] ?? null;
        // Fulfil the order.
        break;
    case 'charge.expired':
        // Release the stock.
        break;
}

http_response_code(200);
```

In Laravel, the route has to be left out of CSRF protection. The simplest way is to register it in `routes/api.php`, which has none (`php artisan install:api` creates the file), and read the body with `$request->getContent()`:

```php
// routes/api.php, so the endpoint is https://your-app.example/api/webhooks/velirapay
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use VeliraPay\Exceptions\SignatureVerificationException;
use VeliraPay\Webhooks\Webhook;

Route::post('/webhooks/velirapay', function (Request $request) {
    try {
        $event = Webhook::constructEvent(
            $request->getContent(),
            $request->header(Webhook::SIGNATURE_HEADER),
            config('services.velirapay.webhook_secret'),
        );
    } catch (SignatureVerificationException) {
        abort(400);
    }

    if ($event->isVerification()) {
        return response()->json(['challenge' => $event->challenge()]);
    }

    if ($event->test) {
        return response()->noContent();
    }

    if ($event->is('charge.paid') && $event->charge !== null) {
        ProcessPaidCharge::dispatch($event->charge->id);
    }

    return response()->noContent();
});
```

To keep the route in `routes/web.php`, leave its path out of CSRF protection in `bootstrap/app.php` instead:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['webhooks/velirapay']);
})
```

Calling `->withoutMiddleware(ValidateCsrfToken::class)` on the route, as earlier versions of this README suggested, no longer works on Laravel 13: CSRF protection is now the `PreventRequestForgery` middleware, and `ValidateCsrfToken` is only a deprecated subclass of it, so leaving it out leaves nothing out. Laravel 13 also renames `validateCsrfTokens()` to `preventRequestForgery()`, though the old name still works.

With a PSR-7 request, use `Webhook::constructEventFromRequest($request, $secret)`. To check a signature without parsing the body, call `Webhook::verifySignature($payload, $signature, $secret)`, which throws the same `SignatureVerificationException`.

Some things to know:

- **Answer the verification challenge.** When an endpoint is added, or its URL changes, VeliraPay sends it a signed `webhook.verification` delivery and sends it nothing else until it answers with HTTP 200 and the challenge, as `$event->challengeResponse()` gives it. Endpoint URLs must use `https://`.
- **A second signature for a day after a rotation.** For a day after you rotate the signing secret, the `X-VeliraPay-Signature` header carries a `v1` made with the old secret too, so a receiver that still has the old one keeps working. `constructEvent()` accepts a delivery whichever `v1` matches.
- **Answer quickly.** A delivery counts as delivered when your endpoint answers with a 2xx status within 15 seconds. Queue slow work instead of doing it before answering. Redirects are not followed, so a 3xx counts as a failure: give the endpoint's final URL.
- **Failed deliveries are retried.** A delivery is tried 14 times over about 3 days: after 1 minute, 5 minutes, 30 minutes, 1 hour, 2 hours, 4 hours and 8 hours, then every 10 hours. When one runs out of attempts, the account's owners and admins are emailed (at most once a day per endpoint), and an endpoint that fails 15 deliveries in a row is switched off until you switch it back on in the dashboard. While it is off, no new events are queued for it and the deliveries still waiting are marked failed; switching it back on does not send what it missed. The endpoint's delivery log keeps every attempt for 30 days, and can send a delivery again.
- **Skip duplicates by event.** A retried delivery keeps its id (`$event->id`, also the `X-VeliraPay-Delivery` header), but that id belongs to one endpoint: each endpoint gets its own copy of an event, under its own id. `$event->eventId` is the same on every copy and is the event's id in `GET /v1/events`, so `$event->eventId ?? $event->id` is the key to remember.
- **Fulfil once.** A paid invoice sends both `charge.paid`, for the charge that paid it, and `invoice.paid`. Fulfil on one of them, not both.
- **Deliveries can arrive out of order.** When the order matters, fetch the current state with `$velirapay->charges->retrieve($event->charge->id)` rather than trusting the payload.
- **Test deliveries.** The dashboard's "Send test event" button, and `webhookEndpoints->test()`, send a sample `charge.paid` with `$event->test` set to `true` and no `eventId`. Its charge is a made-up sample without many of a real one's fields, such as `transactions` and `customer`, so answer it with a 2xx and fulfil nothing.
- **Modes.** A test-mode charge is announced to the endpoints added in test mode, and a live one to the live ones, so an endpoint hears of one mode only. If one URL serves both, compare `$event->mode` with the mode of the key your shop uses, `$velirapay->mode()?->value`, and leave the other mode's events alone; keep handling test mode while you try your integration with a `vp_test_` key.
- **Signatures expire.** A signature more than 5 minutes from the current time is rejected, which stops an intercepted delivery from being replayed. Change the limit with the non-negative `tolerance` argument; `0` explicitly disables the age check.

`charge.payment_detected` arrives as soon as a transfer to a charge is seen on-chain, before it is confirmed; `charge.late_payment` when one reaches a charge that already expired, was canceled or was paid, or tops up an underpaid charge after its rate lock ended, which is announced once it has confirmed. A late payment to an expired, canceled or underpaid charge waits for you to accept it; one on top of a paid charge is counted automatically. Both carry that transfer as `$event->transaction` (`txid`, `amount`, `confirmations`, `requiredConfirmations`, `credited`, `explorerUrl`, `seenAt`); for every other event it is `null`.

`payout.sent` arrives when your share of a paid charge has been broadcast, and `payout.confirmed` when the chain has confirmed it. Both carry the charge as `$event->charge` and the payout as `$event->payout`, the same object `$velirapay->payouts->retrieve()` returns; for every other event it is `null`. Book the money on `payout.confirmed`: a broadcast payout can still be dropped or fail and go out again, so `payout.sent` can arrive more than once for a charge. `networkFee` on `payout.sent` can be the most the chain may charge, where `payout.confirmed` has what it took, and a USDT or USDC payout left unmined bids again under a new `payoutTxid`, so the one that counts is the one in `payout.confirmed`.

The charge and invoice in a delivery are not quite the ones the API returns:

- A charge's coin amounts and exchange rate carry all 18 decimal places (`"0.002500000000000000"` rather than `"0.0025"`). Compare amounts as numbers, not as strings.
- A charge has no `timeline`, `refunds`, `checkoutUrl`, `receiptUrl`, `successUrl`, `cancelUrl` or `successMessage`. Its `payment_link` is `{code, title}` and its `invoice` is `{code, number}`: `$charge->paymentLink` and `$charge->invoice` hold the code, and `$charge->paymentLinkTitle` and `$charge->invoiceNumber` the rest, which are `null` on the charges the API returns.
- An invoice has no `overdue`, `documentUrl`, `successUrl` or `cancelUrl`; all four are `null`.
- `charge.refunded` carries no refund. Read its amount, txid and reason from the event, with `$velirapay->events->retrieve($event->eventId)->details`, which holds `amount`, and `txid` and `reason` when you gave them.

Fetch the object when you need what a delivery leaves out. The charges that events carry, from `GET /v1/events`, have no `timeline` either.

## Errors

A failed request throws an exception carrying the API's message:

| Exception | When |
| --- | --- |
| `AuthenticationException` | The API key is missing, wrong or revoked (401). |
| `PermissionException` | The API key may not do this (403): a restricted key without the permission the endpoint needs, such as `charges:write`, or a key used from an IP address it does not allow. The message names which. An owner or admin can change the key's permissions and addresses in the dashboard; the key itself stays the same. |
| `NotFoundException` | The object does not exist, or belongs to the other mode (404). |
| `ConflictException` | The object cannot make that change (409), such as canceling a charge once a transfer to it has been seen or it is no longer pending, accepting one that is neither underpaid nor late, paying a live charge by simulation, or sending or voiding an invoice that is no longer open. Also when the idempotency key was used for a different request, or is held by a request still being processed; that one carries a `Retry-After` and is [retried](#retries-and-idempotency) for you. |
| `ValidationException` | The request has invalid fields (422). `errors()` lists them, such as `coupon` for a coupon that cannot be used. In test mode, an invoice emailed to someone outside your account is refused this way, on `customer_email`. |
| `RateLimitException` | Too many requests (429), including an invoice emailed again within 10 minutes, more than 100 emails to customers an hour, and more than 300 charges created in 30 minutes, or 10 emails and 30 charges until one of the owner's accounts has been paid in live mode. `retryAfter()` says how many seconds to wait. |
| `ServerException` | VeliraPay had a problem (5xx), such as no exchange rate being available to create a charge with (503). |
| `InvalidRequestException` | Any other 4xx, such as an `Idempotency-Key` longer than 255 characters (400). |
| `UnexpectedResponseException` | A successful answer the library cannot read, such as a page that is not JSON from something between you and the API. |
| `ConnectionException` | The API could not be reached, or its answer could not be read, after the [retries](#retries-and-idempotency). `idempotencyKey` is the key a write was sent with, to repeat it safely. |
| `SignatureVerificationException` | A [webhook](#webhooks)'s signature is missing, does not match the secret, or is too old. |
| `InvalidArgumentException` | The library was given something it cannot use, before any request is sent, such as a key that does not start with `vp_live_` or `vp_test_`, an empty id, an empty webhook secret or a webhook payload that is not a JSON object. |

They live in `VeliraPay\Exceptions`. All of them implement `VeliraPayException`. All but `ConnectionException`, `SignatureVerificationException` and `InvalidArgumentException` extend `ApiException`, whose `retryAfter()` returns the response's `Retry-After` in seconds, or `null` when it had none.

```php
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ValidationException;

try {
    $charge = $velirapay->charges->create(['amount' => '0', 'currency' => 'EUR', 'asset' => 'BTC']);
} catch (ValidationException $exception) {
    $exception->errors();              // ['amount' => ['Enter at least €0.50.']]
    $exception->firstError('amount');
} catch (ApiException $exception) {
    $exception->status;                // the HTTP status
    $exception->getMessage();
    $exception->body;                  // the decoded response
}
```

## Retries and idempotency

Requests that fail with a network error, an answer cut off or timed out after it began, a rate limit (429) or an outage (502, 503, 504, and Cloudflare's 520 to 524) are tried again up to twice, waiting a little longer each time, or as long as the API's `Retry-After` header asks. A 500 is retried for reads only, since a write may have been carried out. A 409 is retried only when it carries a `Retry-After`: that is the API still working on an earlier request with the same idempotency key, and the retry gets that request's response once it is done.

A `Retry-After` longer than `maxRetryAfter` (10 seconds unless [configured](#configuration) otherwise) is not waited out: the exception is thrown straight away, and its `retryAfter()` says how long to wait.

While retries are on, each write carries an `Idempotency-Key` header, so the API carries out a retried request once and answers the retry with the first response. The library makes up a key per call; pass your own to make a call safe to repeat across processes:

```php
$charge = $velirapay->charges->create($params, idempotencyKey: 'order-1042-attempt-1');

$velirapay->lastResponse()?->wasReplayed(); // true when the API answered from an earlier request
```

What the API does with a key:

- Successful (2xx) responses are replayed for 24 hours to requests repeating the key. A request that failed stores nothing, so repeating it runs it again.
- Reuse a key only to retry the same request, and give a new attempt a new key: a charge replacing one that expired, created under the first one's key, would get the expired charge back for a day. Hence keys such as `order-1042-attempt-1` rather than the bare order number.
- An answer that carries a new API key or webhook signing secret is never stored. Creating a key or endpoint, or rotating an endpoint's secret, succeeds once; repeating its idempotency key throws a `ConflictException` saying the request already succeeded, whose `wasReplayed()` is `true`. The library repeats such a request itself when its answer is lost, so that is how you learn it went through: the key or endpoint was created, or the secret rotated, but the secret is gone. List the keys or endpoints, revoke that key or rotate that endpoint's secret, and try again under a new idempotency key, rather than blindly repeating the request.
- A key is at most 255 characters; a longer one is refused with a 400.
- A key covers one account, one mode, one HTTP method and one path. Every API key of that account and mode shares it, so two servers using different API keys still create one charge for one idempotency key.
- Reusing a key with a different body or query string throws a `ConflictException`.
- A request holds its key for up to 60 seconds while it is being processed. Another one with the same key gets a 409 with `Retry-After: 1` in that time, which the library waits out and retries.

When the API still cannot be reached, or its answer read, after the retries, a `ConnectionException` is thrown. A write may have been carried out all the same, so `$exception->idempotencyKey` holds the key it was sent with, the one the library made up included: repeating the call with that key is safe, and gets the first request's response if it succeeded.

```php
use VeliraPay\Exceptions\ConnectionException;

try {
    $charge = $velirapay->charges->create($params);
} catch (ConnectionException $exception) {
    $charge = $velirapay->charges->create($params, idempotencyKey: $exception->idempotencyKey);
}
```

## Test mode

Keys starting with `vp_test_` work in test mode: charges are paid with test coins on each chain's test network, and nothing touches real funds. Test and live objects are fully separate: a live key cannot see test charges, and each mode has its own webhooks. Invoices are only emailed to members of your account.

```php
$velirapay->mode();       // Mode::Test, read from the key's prefix
$velirapay->isTestMode(); // true
$velirapay->isLiveMode(); // false

// Pay a test charge without any coins: it becomes paid, and the webhooks and emails go out as for a real payment.
$velirapay->charges->simulate('k3v9x2m7q8wz');
$velirapay->charges->simulate('k3v9x2m7q8wz', ['amount' => '0.001']); // less than is due, to try an underpayment
```

BNB Chain, Base, Arbitrum One and Polygon have no test mode, so their coins can only be used with a live key; `assets->list()` with a test key shows what test mode takes.

Every key starts with `vp_live_` or `vp_test_`, and the client throws an `InvalidArgumentException` for any other, which the API would refuse.

## Configuration

```php
$velirapay = new VeliraPayClient(
    apiKey: getenv('VELIRAPAY_API_KEY') ?: throw new RuntimeException('Set VELIRAPAY_API_KEY.'),
    baseUrl: 'https://api.velirapay.com', // the API's address, VeliraPayClient::DEFAULT_BASE_URL
    maxRetries: 2,           // 0 turns retries off
    maxRetryAfter: 10,       // the longest Retry-After, in seconds, waited out before trying again
    timeout: 30.0,           // seconds, for the Guzzle client the library creates
    appInfo: 'MyShop/2.1',   // added to the User-Agent
);
```

To use another HTTP client, or configure its proxy and timeouts yourself, pass any PSR-18 client along with PSR-17 factories if they cannot be discovered:

```php
$velirapay = new VeliraPayClient(
    apiKey: getenv('VELIRAPAY_API_KEY') ?: throw new RuntimeException('Set VELIRAPAY_API_KEY.'),
    httpClient: new Symfony\Component\HttpClient\Psr18Client(),
);
```

For an endpoint the library has no method for yet, send the request yourself:

```php
$response = $velirapay->request('GET', '/v1/charges', ['per_page' => 5]);
$response->json();
```

## Testing your integration

Sign fixtures with `Webhook::signatureHeader()` to test your webhook handler:

```php
$payload = json_encode([
    'id' => 'a5f4b1c2-…',
    'event_id' => null,
    'event' => 'charge.paid',
    'mode' => 'test',
    'created_at' => date(DATE_ATOM),
    'data' => ['charge' => ['code' => 'k3v9x2m7q8wz', 'status' => 'paid', 'metadata' => ['order_id' => '1042']]],
]);

$this->call('POST', '/webhooks/velirapay', server: [
    'HTTP_X_VELIRAPAY_SIGNATURE' => Webhook::signatureHeader($payload, 'whsec_test'),
], content: $payload);
```

To test code that calls the API, pass a mock PSR-18 client to the `VeliraPayClient`, or use a test-mode key.

## Laravel

In a Laravel application, install [velirapay/velirapay-laravel](https://github.com/VeliraPay/velirapay-laravel) instead: it configures the client from your `.env`, adds a facade, and turns webhooks into Laravel events.

## Development

```bash
composer install
composer test        # PHPUnit
composer types       # PHPStan
composer lint        # Pint, fixing what it finds
composer lint:check  # Pint, without changing anything
composer check       # lint:check, types and test, as CI runs them
```

## License

MIT. See [LICENSE](LICENSE).
