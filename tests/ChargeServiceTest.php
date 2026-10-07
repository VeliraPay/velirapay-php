<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
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
        $this->assertSame('BTC', $charge->asset);
        $this->assertSame('0.0025', $charge->assetAmount);
        $this->assertSame('0.0025', $charge->receivedAmount);
        $this->assertSame('0', $charge->remainingAmount);
        $this->assertFalse($charge->overpaid);
        $this->assertSame('0.0005', $charge->refundedAmount);
        $this->assertCount(1, $charge->refunds);
        $this->assertSame('Partial refund', $charge->refunds[0]->reason);
        $this->assertEquals(new DateTimeImmutable('2026-09-21T14:30:00Z'), $charge->refunds[0]->createdAt);
        $this->assertSame('60000', $charge->exchangeRate);
        $this->assertSame('bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh', $charge->depositAddress);
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
