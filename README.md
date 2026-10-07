# VeliraPay PHP

[![Tests](https://github.com/VeliraPay/velirapay-php/actions/workflows/tests.yml/badge.svg)](https://github.com/VeliraPay/velirapay-php/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/velirapay/velirapay-php)](https://packagist.org/packages/velirapay/velirapay-php)
[![License](https://img.shields.io/packagist/l/velirapay/velirapay-php)](LICENSE)

The official PHP library for [VeliraPay](https://velirapay.com), the crypto payment processor. It covers the whole v1 API (charges, payment links, invoices, events and your account) and verifies the webhooks VeliraPay sends you.

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Charges](#charges)
- [Payment links](#payment-links)
- [Invoices](#invoices)
- [Events](#events)
- [Your account](#your-account)
- [Lists and pagination](#lists-and-pagination)
- [Webhooks](#webhooks)
- [Errors](#errors)
- [Retries and idempotency](#retries-and-idempotency)
- [Test mode](#test-mode)
- [Configuration](#configuration)
- [Testing your integration](#testing-your-integration)
- [Laravel](#laravel)

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

$velirapay = new VeliraPayClient(getenv('VELIRAPAY_API_KEY'));

$charge = $velirapay->charges->create([
    'amount' => '49.90',
    'currency' => 'EUR',
    'asset' => 'BTC',
    'customer' => ['email' => 'ada@example.com'],
    'metadata' => ['order_id' => '1042'],
]);

header('Location: '.$charge->checkoutUrl);
```

The customer pays on the hosted checkout page. VeliraPay then tells your server about it with a [webhook](#webhooks).

Amounts are always strings, such as `'49.90'`, never floats, so no precision is lost. Use [bcmath](https://www.php.net/manual/en/book.bc.php) or a money library to do arithmetic on them.

Each group of endpoints is a property, such as `$velirapay->charges`, and also a method, `$velirapay->charges()`, which facades need.

## Charges

A charge is one payment in one coin. The exchange rate is locked when it is created, and the customer has 30 minutes to pay.

```php
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\ChargeStatus;

// For an amount you set.
$charge = $velirapay->charges->create([
    'amount' => '150.00',
    'currency' => 'USD',
    'asset' => Asset::USDC, // USD Coin on Ethereum
    'description' => 'Pro plan, 1 year',
    'metadata' => ['order_id' => '1042'],
]);

// For one of your payment links: the currency comes from the link, and so does the price when it is fixed.
$charge = $velirapay->charges->create(['payment_link' => 'p4n8r2t6y1ua', 'asset' => 'ETH']);

$charge = $velirapay->charges->retrieve('k3v9x2m7q8wz');

$charge->status;          // "pending", "underpaid", "paid", "expired" or "canceled"
$charge->isPaid();
$charge->assetAmount;     // "0.0025", what the customer has to send
$charge->receivedAmount;  // what arrived so far
$charge->depositAddress;
$charge->transactions;    // list of VeliraPay\Resources\Transaction
$charge->metadata;        // ['order_id' => '1042']

$paid = $velirapay->charges->list(['status' => ChargeStatus::Paid]);

// Only while the charge is pending and no transfer to it has been seen.
$velirapay->charges->cancel('k3v9x2m7q8wz');

// Record a refund you sent from your own wallet.
$velirapay->charges->recordRefund('k3v9x2m7q8wz', [
    'amount' => '0.0005',
    'txid' => '7d1c5e9a…',
    'reason' => 'Returned item',
]);
```

`asset` takes a `VeliraPay\Enums\Asset` case or its value, such as `'BTC'`. The enum lists every coin the API knows, but not all of them can be paid in at any given time: a coin is only offered while VeliraPay can also pay it out, and a network can be switched off. `$account->acceptedAssets` lists the coins your account takes right now ([Your account](#your-account)); a charge in any other coin throws a `ValidationException`.

`metadata` holds up to 20 keys of at most 40 characters each. Values are strings, numbers, booleans or `null`, and a string is at most 500 characters.

### Customer details

`$charge->customer` holds what is known about who pays, to help you spot fraud before you ship anything. When the customer starts the payment on the hosted checkout, VeliraPay records the IP address and browser they did it from. A charge you create from your server comes from your server rather than the customer, so pass what you know:

```php
$charge = $velirapay->charges->create([
    'amount' => '150.00',
    'currency' => 'USD',
    'asset' => 'BTC',
    'customer' => [
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
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

A payment link is a reusable checkout page, for a fixed price or one the customer chooses.

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

$velirapay->paymentLinks->update($link->id, ['title' => 'Pro plan (yearly)']);
$velirapay->paymentLinks->archive($link->id); // stops taking payments
$velirapay->paymentLinks->restore($link->id);
```

## Invoices

An invoice is a bill with a hosted page, where the customer pays in the coin of their choice.

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
    'send_email' => true,
]);

echo $invoice->number;    // INV-0042
echo $invoice->hostedUrl;

$velirapay->invoices->send($invoice->id); // email it, or send a reminder
$velirapay->invoices->void($invoice->id);
```

Pass `amount` instead of `items` to bill a single total.

An invoice can be emailed once every 10 minutes, and an account can send 100 invoice emails an hour. Past either limit, `send()` throws a `RateLimitException` whose `retryAfter()` says how many seconds to wait. In test mode, invoices are only emailed to members of your account: anyone else is refused with a `ValidationException` on `customer_email`, by `send()` as by `create()` with `send_email`.

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

## Your account

```php
$account = $velirapay->account->retrieve();

$account->displayName;
$account->supportEmail;
$account->supportPhone;   // "+442071234567", shown to customers next to the support email
$account->acceptedAssets; // ['BTC', 'ETH', …], the coins it takes right now, in the key's mode
$account->mode;           // "live" or "test"

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

## Lists and pagination

`list()` returns one page, newest first. Pass `page` and `per_page` (up to 100, 25 by default) to move through it:

```php
$page = $velirapay->charges->list(['per_page' => 100]);

foreach ($page as $charge) {
    // …
}

$page->total;
$page->hasMore();
$next = $page->nextPage(); // null on the last page
```

`iterator()` walks every page for you, fetching each one as it is reached:

```php
foreach ($velirapay->charges->iterator(['status' => 'paid']) as $charge) {
    // …
}
```

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

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_VELIRAPAY_SIGNATURE'] ?? null;

try {
    $event = Webhook::constructEvent($payload, $signature, getenv('VELIRAPAY_WEBHOOK_SECRET'));
} catch (SignatureVerificationException $exception) {
    http_response_code(400);
    exit;
}

// The same event can arrive more than once: remember this key, and skip the events you have handled.
$key = $event->eventId ?? $event->id;

switch ($event->type) {
    case 'charge.payment_detected':
        // Seen on-chain, not confirmed yet: tell the customer, but do not fulfil.
        $txid = $event->transaction->txid;
        break;
    case 'charge.paid':
        $orderId = $event->charge->metadata['order_id'];
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

    if ($event->is('charge.paid')) {
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

With a PSR-7 request, use `Webhook::constructEventFromRequest($request, $secret)`.

Some things to know:

- **Answer quickly.** A delivery counts as delivered when your endpoint answers with a 2xx status within 15 seconds. Queue slow work instead of doing it before answering. Redirects are not followed, so a 3xx counts as a failure: give the endpoint's final URL.
- **Failed deliveries are retried.** A delivery is tried 14 times over about 3 days: after 1 minute, 5 minutes, 30 minutes, 1 hour, 2 hours, 4 hours and 8 hours, then every 10 hours. When one runs out of attempts, the account's owners and admins are emailed (at most once a day per endpoint), and an endpoint that fails 15 deliveries in a row is switched off until you switch it back on in the dashboard. The endpoint's delivery log there keeps every attempt for 30 days, and can send a delivery again.
- **Skip duplicates by event.** A retried delivery keeps its id (`$event->id`, also the `X-VeliraPay-Delivery` header), but that id belongs to one endpoint: each endpoint gets its own copy of an event, under its own id. `$event->eventId` is the same on every copy and is the event's id in `GET /v1/events`, so `$event->eventId ?? $event->id` is the key to remember.
- **Fulfil once.** A paid invoice sends both `charge.paid`, for the charge that paid it, and `invoice.paid`. Fulfil on one of them, not both.
- **Deliveries can arrive out of order.** When the order matters, fetch the current state with `$velirapay->charges->retrieve($event->charge->id)` rather than trusting the payload.
- **Test deliveries.** The dashboard's "Send test event" button sends a sample `charge.paid` with `$event->test` set to `true` and no `eventId`. Its charge is a made-up sample without many of a real one's fields, such as `transactions` and `customer`.
- **Signatures expire.** A signature older than 5 minutes is rejected, which stops an intercepted delivery from being replayed. Change the limit with the `tolerance` argument.

`charge.payment_detected` arrives as soon as a transfer to a charge is seen on-chain, before it is confirmed; `charge.late_payment` when one reaches a charge that already expired, was canceled or was paid. Both carry that transfer as `$event->transaction` (`txid`, `amount`, `confirmations`, `requiredConfirmations`, `credited`, `explorerUrl`, `seenAt`); for every other event it is `null`.

The charge and invoice in a delivery are not quite the ones the API returns:

- A charge's coin amounts and exchange rate carry all 18 decimal places (`"0.002500000000000000"` rather than `"0.0025"`). Compare amounts as numbers, not as strings.
- A charge has no `timeline`, `refunds`, `checkoutUrl` or `receiptUrl`. Its `payment_link` is `{code, title}` and its `invoice` is `{code, number}`: `$charge->paymentLink` and `$charge->invoice` hold the code, and `$charge->get('payment_link')` has the rest.
- An invoice has no `overdue` or `documentUrl`; both are `null`.
- `charge.refunded` carries no refund. Read its amount, txid and reason from the event, with `$velirapay->events->retrieve($event->eventId)->details`, which holds `amount`, and `txid` and `reason` when you gave them.

Fetch the object when you need what a delivery leaves out. The charges that events carry, from `GET /v1/events`, have no `timeline` either.

## Errors

A failed request throws an exception carrying the API's message:

| Exception | When |
| --- | --- |
| `AuthenticationException` | The API key is missing, wrong or revoked (401). |
| `NotFoundException` | The object does not exist, or belongs to the other mode (404). |
| `ConflictException` | The object cannot make that change (409), such as canceling a charge once a transfer to it has been seen or it is no longer pending, or sending or voiding an invoice that is no longer open. Also when the idempotency key was used for a different request, or is held by a request still being processed; that one carries a `Retry-After` and is [retried](#retries-and-idempotency) for you. |
| `ValidationException` | The request has invalid fields (422). `errors()` lists them. In test mode, an invoice emailed to someone outside your account is refused this way, on `customer_email`. |
| `RateLimitException` | Too many requests (429), including an invoice emailed again within 10 minutes and more than 100 invoice emails an hour. `retryAfter()` says how many seconds to wait. |
| `ServerException` | VeliraPay had a problem (5xx), such as no exchange rate being available to create a charge with (503). |
| `InvalidRequestException` | Any other 4xx, such as an `Idempotency-Key` longer than 255 characters (400). |
| `ConnectionException` | The API could not be reached. |

They live in `VeliraPay\Exceptions`. All of them implement `VeliraPayException`, and all but `ConnectionException` extend `ApiException`, whose `retryAfter()` returns the response's `Retry-After` in seconds, or `null` when it had none.

```php
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ValidationException;

try {
    $charge = $velirapay->charges->create(['amount' => '0', 'currency' => 'EUR', 'asset' => 'BTC']);
} catch (ValidationException $exception) {
    $exception->errors();              // ['amount' => ['The amount field must be at least 0.01.']]
    $exception->firstError('amount');
} catch (ApiException $exception) {
    $exception->status;                // the HTTP status
    $exception->getMessage();
    $exception->body;                  // the decoded response
}
```

## Retries and idempotency

Requests that fail with a network error, a rate limit (429) or an outage (502, 503, 504) are tried again up to twice, waiting a little longer each time, or as long as the API's `Retry-After` header asks. A 500 is retried for reads only, since a write may have been carried out. A 409 is retried only when it carries a `Retry-After`: that is the API still working on an earlier request with the same idempotency key, and the retry gets that request's response once it is done.

A `Retry-After` longer than `maxRetryAfter` (10 seconds unless [configured](#configuration) otherwise) is not waited out: the exception is thrown straight away, and its `retryAfter()` says how long to wait.

While retries are on, each write carries an `Idempotency-Key` header, so the API carries out a retried request once and answers the retry with the first response. The library makes up a key per call; pass your own, such as an order number, to make a call safe to repeat across processes:

```php
$charge = $velirapay->charges->create($params, idempotencyKey: 'order-1042');

$velirapay->lastResponse()?->wasReplayed(); // true when the API answered from an earlier request
```

What the API does with a key:

- Only a successful (2xx) response is stored, and it is replayed for 24 hours to any request repeating the key. A request that failed stored nothing, so repeating it runs it again.
- A key is at most 255 characters; a longer one is refused with a 400.
- A key covers one account, one mode, one HTTP method and one path. Every API key of that account and mode shares it, so two servers using different API keys still create one charge for one idempotency key.
- Reusing a key with a different body or query string throws a `ConflictException`.
- A request holds its key for up to 60 seconds while it is being processed. Another one with the same key gets a 409 with `Retry-After: 1` in that time, which the library waits out and retries.

## Test mode

Keys starting with `vp_test_` work in test mode: charges are paid with test coins on each chain's test network, and nothing touches real funds. Test and live objects are fully separate: a live key cannot see test charges, and each mode has its own webhooks. Invoices are only emailed to members of your account.

```php
$velirapay->isTestMode(); // read from the key's prefix
```

Every key starts with `vp_live_` or `vp_test_`, and the client throws an `InvalidArgumentException` for any other, which the API would refuse.

## Configuration

```php
$velirapay = new VeliraPayClient(
    apiKey: getenv('VELIRAPAY_API_KEY'),
    maxRetries: 2,           // 0 turns retries off
    maxRetryAfter: 10,       // the longest Retry-After, in seconds, waited out before trying again
    timeout: 30.0,           // seconds, for the Guzzle client the library creates
    appInfo: 'MyShop/2.1',   // added to the User-Agent
);
```

To use another HTTP client, or configure its proxy and timeouts yourself, pass any PSR-18 client along with PSR-17 factories if they cannot be discovered:

```php
$velirapay = new VeliraPayClient(
    apiKey: getenv('VELIRAPAY_API_KEY'),
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
composer lint        # Pint
```

## License

MIT. See [LICENSE](LICENSE).
