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

- PHP 8.1 or later
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client. Laravel, Symfony and most frameworks already have one.

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
    'customer_email' => 'ada@example.com',
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
use VeliraPay\Enums\ChargeStatus;

// For an amount you set.
$charge = $velirapay->charges->create([
    'amount' => '150.00',
    'currency' => 'USD',
    'asset' => 'USDC_BASE',
    'description' => 'Pro plan, 1 year',
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

$velirapay->charges->cancel('k3v9x2m7q8wz');

// Record a refund you sent from your own wallet.
$velirapay->charges->recordRefund('k3v9x2m7q8wz', [
    'amount' => '0.0005',
    'txid' => '7d1c5e9a…',
    'reason' => 'Returned item',
]);
```

The coins are `BTC`, `LTC`, `DOGE`, `BCH`, `ETH`, `XMR`, `SOL`, `USDT`, `USDC`, `USDT_TRON`, `USDC_BASE`, `USDC_POLYGON` and `USDT_BSC`; the ones your account accepts are listed by [`$velirapay->account->retrieve()`](#your-account).

## Payment links

A payment link is a reusable checkout page, for a fixed price or one the customer chooses.

```php
use VeliraPay\Enums\PricingType;

$link = $velirapay->paymentLinks->create([
    'title' => 'Pro plan',
    'pricing_type' => PricingType::Fixed,
    'amount' => '150.00',
    'currency' => 'EUR',
    'accepted_assets' => ['BTC', 'ETH', 'USDC_BASE'], // null accepts every coin
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

## Events

Every change to a charge or an invoice is recorded as an event, such as `charge.paid` or `invoice.viewed`. Webhooks carry the same events.

```php
use VeliraPay\Enums\EventType;

$events = $velirapay->events->list(['charge' => 'k3v9x2m7q8wz']);
$payments = $velirapay->events->list(['type' => EventType::ChargePaymentDetected]);

$event = $velirapay->events->retrieve('9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b');
$event->is(EventType::ChargePaid);
$event->charge; // the charge as it is now
```

## Your account

```php
$account = $velirapay->account->retrieve();

$account->displayName;
$account->acceptedAssets; // ['BTC', 'ETH', …], for the key's mode
$account->mode;           // "live" or "test"
```

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

switch ($event->type) {
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

In Laravel, exclude the route from CSRF protection and read the body with `$request->getContent()`:

```php
use Illuminate\Http\Request;
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
})->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
```

With a PSR-7 request, use `Webhook::constructEventFromRequest($request, $secret)`.

Some things to know:

- **Answer quickly.** A delivery counts as delivered when your endpoint answers with a 2xx status within 15 seconds. Queue slow work instead of doing it before answering.
- **Deliveries are retried.** A failed delivery is tried again after 1 minute, 5 minutes, 30 minutes and 2 hours. A retry keeps the delivery's id (`$event->id`), so store the ids you have handled and skip repeats.
- **Deliveries can arrive out of order.** When the order matters, fetch the current state with `$velirapay->charges->retrieve($event->charge->id)` rather than trusting the payload.
- **Test deliveries.** The dashboard's "Send test event" button sends a sample `charge.paid` with `$event->test` set to `true`.
- **Signatures expire.** A signature older than 5 minutes is rejected, which stops an intercepted delivery from being replayed. Change the limit with the `tolerance` argument.

The payload's charge and invoice have fewer fields than the API returns: webhook charges have no `checkoutUrl`, `receiptUrl`, `refunds` or `timeline`, and their amounts carry every decimal place (`"0.002500000000000000"`).

## Errors

A failed request throws an exception carrying the API's message:

| Exception | When |
| --- | --- |
| `AuthenticationException` | The API key is missing, wrong or revoked (401). |
| `NotFoundException` | The object does not exist, or belongs to the other mode (404). |
| `ConflictException` | The object cannot make that change, such as canceling a paid charge, or the idempotency key was used for a different request (409). |
| `ValidationException` | The request has invalid fields (422). `errors()` lists them. |
| `RateLimitException` | Too many requests (429). `retryAfter()` says how long to wait. |
| `ServerException` | VeliraPay had a problem (5xx). |
| `InvalidRequestException` | Any other 4xx. |
| `ConnectionException` | The API could not be reached. |

They live in `VeliraPay\Exceptions`. All of them implement `VeliraPayException`, and all but `ConnectionException` extend `ApiException`.

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

Requests that fail with a network error, a rate limit (429) or an outage (502, 503, 504) are tried again up to twice, waiting a little longer each time. A 500 is retried for reads only, since a write may have been carried out. A rate limit asking to wait more than 10 seconds is thrown as a `RateLimitException` straight away.

While retries are on, each write carries an `Idempotency-Key` header, so the API carries out a retried request once and answers the retry with the first response. The library makes up a key per call; pass your own, such as an order number, to make a call safe to repeat across processes:

```php
$charge = $velirapay->charges->create($params, idempotencyKey: 'order-1042');

$velirapay->lastResponse()?->wasReplayed(); // true when the API answered from an earlier request
```

Keys are kept for 24 hours. Reusing one with different parameters throws a `ConflictException`.

## Test mode

Keys starting with `vp_test_` work in test mode: charges are paid with test coins on each chain's test network, and nothing touches real funds. Test and live objects are fully separate: a live key cannot see test charges, and each mode has its own webhooks.

```php
$velirapay->isTestMode(); // read from the key's prefix
```

## Configuration

```php
$velirapay = new VeliraPayClient(
    apiKey: getenv('VELIRAPAY_API_KEY'),
    maxRetries: 2,           // 0 turns retries off
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
