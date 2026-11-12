<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\ChargeStatus;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Resources\Charge;

final class ChargeServiceTest extends TestCase
{
    public function test_a_charge_is_created(): void
    {
        $this->http->json(['data' => self::fixture('charge')], 201);

        $charge = $this->client()->charges->create([
            'asset' => Asset::BTC,
            'amount' => '150.00',
            'currency' => 'EUR',
            'metadata' => ['order_id' => '1042'],
        ], 'order-1042');

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/charges', $request->getUri()->getPath());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('order-1042', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(['asset' => 'BTC', 'amount' => '150.00', 'currency' => 'EUR', 'metadata' => ['order_id' => '1042']], self::body($request));
        $this->assertSame('k3v9x2m7q8wz', $charge->id);
    }

    public function test_a_charge_is_created_with_everything_already_known(): void
    {
        $this->http->json(['data' => [...self::fixture('charge'), 'success_url' => 'https://example.com/orders/1042', 'cancel_url' => 'https://example.com/cart', 'success_message' => 'Thank you.']], 201);

        $charge = $this->client()->charges->create([
            'asset' => 'BTC',
            'amount' => '150.00',
            'currency' => 'EUR',
            'custom_fields' => [['label' => 'Company', 'value' => 'Analytical Engines']],
            'success_url' => 'https://example.com/orders/1042',
            'cancel_url' => 'https://example.com/cart',
            'success_message' => 'Thank you.',
            'send_email' => true,
        ]);

        $body = self::body($this->http->lastRequest());
        $this->assertSame([['label' => 'Company', 'value' => 'Analytical Engines']], $body['custom_fields']);
        $this->assertTrue($body['send_email']);
        $this->assertSame('https://example.com/orders/1042', $charge->successUrl);
        $this->assertSame('https://example.com/cart', $charge->cancelUrl);
        $this->assertSame('Thank you.', $charge->successMessage);
    }

    public function test_a_charge_can_be_emailed_accepted_and_paid_by_simulation(): void
    {
        $this->http->json(['data' => self::fixture('charge')]);
        $this->client()->charges->send('k3v9x2m7q8wz');
        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/charges/k3v9x2m7q8wz/send', $this->http->lastRequest()->getUri()->getPath());

        $this->http->json(['data' => self::fixture('charge')]);
        $this->client()->charges->accept('k3v9x2m7q8wz');
        $this->assertSame('/v1/charges/k3v9x2m7q8wz/accept', $this->http->lastRequest()->getUri()->getPath());

        $this->http->json(['data' => self::fixture('charge')]);
        $this->client()->charges->simulate('k3v9x2m7q8wz');
        $this->assertSame('/v1/charges/k3v9x2m7q8wz/simulate', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('', (string) $this->http->lastRequest()->getBody());

        $this->http->json(['data' => self::fixture('charge')]);
        $this->client()->charges->simulate('k3v9x2m7q8wz', ['amount' => '0.001']);
        $this->assertSame(['amount' => '0.001'], self::body($this->http->lastRequest()));
    }

    public function test_a_charge_is_read_into_typed_properties(): void
    {
        $this->http->json(['data' => self::fixture('charge')]);

        $charge = $this->client()->charges->retrieve('k3v9x2m7q8wz');

        $this->assertSame('/v1/charges/k3v9x2m7q8wz', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(ChargeStatus::Paid->value, $charge->status);
        $this->assertTrue($charge->isPaid());
        $this->assertFalse($charge->isOpen());
        $this->assertSame('p4n8r2t6y1ua', $charge->paymentLink);
        $this->assertNull($charge->invoice);
        $this->assertSame('150.00', $charge->fiatAmount);
        $this->assertSame('EUR', $charge->fiatCurrency);
        $this->assertNull($charge->coupon);
        $this->assertNull($charge->discountAmount);
        $this->assertSame('BTC', $charge->asset);
        $this->assertSame('0.0025', $charge->assetAmount);
        $this->assertNull($charge->networkSurcharge);
        $this->assertSame('0.0025', $charge->receivedAmount);
        $this->assertSame('0', $charge->remainingAmount);
        $this->assertFalse($charge->overpaid);
        $this->assertSame('0.0005', $charge->refundedAmount);
        $this->assertCount(1, $charge->refunds);
        $this->assertSame('Partial refund', $charge->refunds[0]->reason);
        $this->assertEquals(new DateTimeImmutable('2026-09-21T14:30:00Z'), $charge->refunds[0]->createdAt);
        $this->assertSame('60000', $charge->exchangeRate);
        $this->assertSame('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', $charge->depositAddress);
        $this->assertNull($charge->paymentRequest);
        $this->assertCount(1, $charge->transactions);
        $this->assertSame(3, $charge->transactions[0]->confirmations);
        $this->assertSame(2, $charge->transactions[0]->requiredConfirmations);
        $this->assertTrue($charge->transactions[0]->credited);
        $this->assertStringStartsWith('https://mempool.space/tx/', (string) $charge->transactions[0]->explorerUrl);
        $this->assertSame(['charge.created', 'charge.payment_detected', 'charge.paid'], array_column($charge->timeline, 'event'));
        $this->assertEquals(new DateTimeImmutable('2026-09-20T10:05:41Z'), $charge->timeline[2]['time']);
        $this->assertSame('ada@example.com', $charge->customerEmail);
        $this->assertSame('Ada Lovelace', $charge->customerName);
        $this->assertSame('ada@example.com', $charge->customer->email);
        $this->assertSame('Ada Lovelace', $charge->customer->name);
        $this->assertSame('203.0.113.7', $charge->customer->ipAddress);
        $this->assertStringStartsWith('Mozilla/5.0 (Macintosh;', (string) $charge->customer->userAgent);
        $this->assertSame('cus_1042', $charge->customer->reference);
        $this->assertSame('+44 20 7946 0958', $charge->customer->phone);
        $this->assertSame('GB', $charge->customer->country);
        $this->assertSame(['orders' => 7, 'verified' => true], $charge->customer->metadata);
        $this->assertSame([['label' => 'Company', 'value' => 'Analytical Engines Ltd']], $charge->customFields);
        $this->assertSame(['order_id' => '1042'], $charge->metadata);
        $this->assertSame('https://velirapay.com/c/k3v9x2m7q8wz', $charge->checkoutUrl);
        $this->assertSame('https://velirapay.com/c/k3v9x2m7q8wz/receipt', $charge->receiptUrl);
        $this->assertEquals(new DateTimeImmutable('2026-09-20T10:25:00Z'), $charge->expiresAt);
        $this->assertEquals(new DateTimeImmutable('2026-09-20T10:05:41Z'), $charge->paidAt);
        $this->assertEquals(new DateTimeImmutable('2026-09-20T09:55:00Z'), $charge->createdAt);
    }

    public function test_the_customers_details_are_sent_with_a_new_charge(): void
    {
        $this->http->json(['data' => self::fixture('charge')], 201);

        $customer = ['email' => 'ada@example.com', 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0', 'reference' => 'cus_1042', 'country' => 'GB', 'metadata' => ['orders' => 7]];

        $this->client()->charges->create(['asset' => 'BTC', 'amount' => '150.00', 'currency' => 'EUR', 'customer' => $customer]);

        $this->assertSame($customer, self::body($this->http->lastRequest())['customer']);
    }

    public function test_a_coupon_is_applied_to_a_new_charge(): void
    {
        $this->http->json(['data' => ['fiat_amount' => '135.00', 'coupon' => 'SPRING10', 'discount_amount' => '15.00'] + self::fixture('charge')], 201);

        $charge = $this->client()->charges->create(['payment_link' => 'p4n8r2t6y1ua', 'asset' => 'BTC', 'coupon' => 'spring10']);

        $this->assertSame('spring10', self::body($this->http->lastRequest())['coupon']);
        $this->assertSame('135.00', $charge->fiatAmount);
        $this->assertSame('SPRING10', $charge->coupon);
        $this->assertSame('15.00', $charge->discountAmount);
    }

    public function test_a_charge_reads_the_network_surcharge_the_customer_pays_on_top_of_the_price(): void
    {
        $this->http->json(['data' => [
            'asset' => 'USDT_TRON',
            'fiat_currency' => 'USD',
            'exchange_rate' => '1',
            'asset_amount' => '152.5',
            'network_surcharge' => '2.5',
            'deposit_address' => 'TJCnKsPa7y5okkXvQAidZBzqx3QyQ6sxMW',
        ] + self::fixture('charge')]);

        $charge = $this->client()->charges->retrieve('k3v9x2m7q8wz');

        $this->assertSame(Asset::USDT_TRON->value, $charge->asset);
        $this->assertSame('152.5', $charge->assetAmount);
        $this->assertSame('2.5', $charge->networkSurcharge);
        $this->assertNull($charge->paymentRequest);
    }

    public function test_a_lightning_charge_reads_its_invoice(): void
    {
        $invoice = 'lnbc2500u1pjq8w4xpp5qy9nkv7r0v4e5n2s7a3xg6m8d0l2c4f6h8j0k2m4n6p8r0t2v4x6z8sdqqcqzzsxqyz5vq';
        $hash = 'a3f1c9e2b7d4058f6e1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f70';
        $this->http->json(['data' => ['asset' => 'BTC_LIGHTNING', 'deposit_address' => $hash, 'payment_request' => $invoice] + self::fixture('charge')]);

        $charge = $this->client()->charges->retrieve('k3v9x2m7q8wz');

        $this->assertSame(Asset::BTC_LIGHTNING->value, $charge->asset);
        $this->assertSame($hash, $charge->depositAddress);
        $this->assertSame($invoice, $charge->paymentRequest);
        $this->assertNull($charge->networkSurcharge);
    }

    public function test_a_charge_sent_without_a_customer_object_takes_it_from_the_older_fields(): void
    {
        $attributes = self::fixture('charge');
        unset($attributes['customer']);
        $this->http->json(['data' => $attributes]);

        $charge = $this->client()->charges->retrieve('k3v9x2m7q8wz');

        $this->assertSame('ada@example.com', $charge->customer->email);
        $this->assertSame('Ada Lovelace', $charge->customer->name);
        $this->assertNull($charge->customer->ipAddress);
        $this->assertSame([], $charge->customer->metadata);
    }

    public function test_a_charge_built_without_a_customer_takes_it_from_the_older_fields(): void
    {
        $charge = new Charge(
            attributes: [],
            id: 'k3v9x2m7q8wz',
            status: ChargeStatus::Pending->value,
            paymentLink: null,
            invoice: null,
            description: null,
            fiatAmount: '150.00',
            fiatCurrency: 'EUR',
            asset: 'BTC',
            assetAmount: '0.0025',
            receivedAmount: null,
            remainingAmount: '0.0025',
            overpaid: false,
            refundedAmount: '0',
            refunds: [],
            exchangeRate: '60000',
            depositAddress: 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            transactions: [],
            timeline: [],
            customerEmail: 'ada@example.com',
            customerName: 'Ada Lovelace',
            customFields: [],
            metadata: [],
            checkoutUrl: null,
            receiptUrl: null,
            expiresAt: null,
            paidAt: null,
            createdAt: null,
        );

        $this->assertSame('ada@example.com', $charge->customer->email);
        $this->assertSame('Ada Lovelace', $charge->customer->name);
        $this->assertNull($charge->customer->ipAddress);
    }

    public function test_attributes_the_library_does_not_know_are_kept(): void
    {
        $attributes = ['status' => 'refunding', 'network_fee' => '0.00001'] + self::fixture('charge');
        $this->http->json(['data' => $attributes]);

        $charge = $this->client()->charges->retrieve('k3v9x2m7q8wz');

        $this->assertSame('refunding', $charge->status);
        $this->assertFalse($charge->isPaid());
        $this->assertSame('0.00001', $charge->get('network_fee'));
        $this->assertSame($attributes, $charge->toArray());
        $this->assertSame(json_encode($attributes), json_encode($charge));
    }

    public function test_ids_are_escaped_into_paths(): void
    {
        $this->http->json(['data' => self::fixture('charge')]);

        $this->client()->charges->retrieve('../account');

        $this->assertSame('/v1/charges/..%2Faccount', $this->http->lastRequest()->getUri()->getPath());
    }

    public function test_an_empty_id_is_refused_before_a_request_is_sent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The id in /v1/charges/{id}/cancel cannot be empty.');

        try {
            $this->client()->charges->cancel('');
        } finally {
            $this->assertSame([], $this->http->requests);
        }
    }

    public function test_charges_are_listed_with_filters(): void
    {
        $this->http->json(self::listResponse([self::fixture('charge')], currentPage: 2, lastPage: 3, perPage: 1, total: 3));

        $page = $this->client()->charges->list(['status' => ChargeStatus::Paid, 'payment_link' => 'p4n8r2t6y1ua', 'page' => 2, 'per_page' => 1]);

        $this->assertSame('/v1/charges', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['status' => 'paid', 'payment_link' => 'p4n8r2t6y1ua', 'page' => '2', 'per_page' => '1'], self::query($this->http->lastRequest()));
        $this->assertCount(1, $page);
        $this->assertInstanceOf(Charge::class, $page->data[0]);
        $this->assertSame(2, $page->currentPage);
        $this->assertSame(3, $page->lastPage);
        $this->assertSame(1, $page->perPage);
        $this->assertSame(3, $page->total);
        $this->assertTrue($page->hasMore());
    }

    public function test_the_dates_charges_are_listed_between_are_sent_in_utc(): void
    {
        $this->http->json(self::listResponse([]));

        $this->client()->charges->list([
            'created_from' => new DateTimeImmutable('2026-12-01 18:00:00', new DateTimeZone('Europe/Paris')),
            'created_to' => new DateTimeImmutable('2026-12-31 23:59:59', new DateTimeZone('America/New_York')),
        ]);

        $this->assertSame([
            'created_from' => '2026-12-01T17:00:00+00:00',
            'created_to' => '2027-01-01T04:59:59+00:00',
        ], self::query($this->http->lastRequest()));
    }

    public function test_a_charge_is_canceled(): void
    {
        $this->http->json(['data' => ['status' => 'canceled'] + self::fixture('charge')]);

        $charge = $this->client()->charges->cancel('k3v9x2m7q8wz', 'cancel-1');

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/charges/k3v9x2m7q8wz/cancel', $request->getUri()->getPath());
        $this->assertSame('', (string) $request->getBody());
        $this->assertFalse($request->hasHeader('Content-Type'));
        $this->assertTrue($charge->isCanceled());
    }

    /**
     * @return iterable<string, array{float, string}>
     */
    public static function floatAmounts(): iterable
    {
        yield 'a small coin amount' => [0.00005, '0.00005'];
        yield 'a tenth' => [0.1, '0.1'];
        yield 'a satoshi-sized amount' => [1.0e-7, '0.0000001'];
        yield 'a whole number' => [25.0, '25'];
        yield 'below a microcoin' => [1.5e-7, '0.00000015'];
        yield 'a large amount' => [1.0e20, '100000000000000000000'];
    }

    #[DataProvider('floatAmounts')]
    public function test_float_amounts_are_sent_as_plain_decimal_strings(float $amount, string $sent): void
    {
        $this->http->json(['data' => self::fixture('charge')], 201)->json(['data' => self::fixture('charge')]);

        $this->client()->charges->recordRefund('k3v9x2m7q8wz', ['amount' => $amount]);
        $this->client()->charges->simulate('k3v9x2m7q8wz', ['amount' => $amount]);

        [$refund, $simulation] = $this->http->requests;
        $this->assertSame($sent, self::body($refund)['amount']);
        $this->assertSame($sent, self::body($simulation)['amount']);
    }

    public function test_a_charges_float_amount_is_sent_as_a_string_and_its_metadata_as_it_is(): void
    {
        $this->http->json(['data' => self::fixture('charge')], 201);

        $this->client()->charges->create(['asset' => 'BTC', 'amount' => 49.9, 'currency' => 'EUR', 'metadata' => ['ratio' => 0.00005], 'customer' => ['metadata' => ['score' => 0.5]]]);

        $body = self::body($this->http->lastRequest());
        $this->assertSame('49.9', $body['amount']);
        $this->assertSame(['ratio' => 5.0E-5], $body['metadata']);
        $this->assertSame(['metadata' => ['score' => 0.5]], $body['customer']);
        $this->assertStringContainsString('"metadata":{"ratio":5.0e-5}', (string) $this->http->lastRequest()->getBody());
    }

    public function test_an_amount_that_is_not_a_finite_number_is_refused_before_a_request_is_sent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->client()->charges->recordRefund('k3v9x2m7q8wz', ['amount' => INF]);
        } finally {
            $this->assertSame([], $this->http->requests);
        }
    }

    public function test_a_refund_is_recorded(): void
    {
        $this->http->json(['data' => self::fixture('charge')], 201);

        $this->client()->charges->recordRefund('k3v9x2m7q8wz', ['amount' => '0.0005', 'txid' => 'c1f0e2d3', 'reason' => 'Partial refund']);

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/charges/k3v9x2m7q8wz/refunds', $request->getUri()->getPath());
        $this->assertSame(['amount' => '0.0005', 'txid' => 'c1f0e2d3', 'reason' => 'Partial refund'], self::body($request));
    }
}
