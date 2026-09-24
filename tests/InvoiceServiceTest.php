<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use DateTimeZone;
use VeliraPay\Enums\InvoiceStatus;
use VeliraPay\Resources\Invoice;

final class InvoiceServiceTest extends TestCase
{
    public function test_an_invoice_is_created_with_its_due_date_as_a_day(): void
    {
        $this->http->json(['data' => self::fixture('invoice')], 201);

        $invoice = $this->client()->invoices->create([
            'customer_name' => 'Grace Hopper',
            'customer_email' => 'grace@example.com',
            'currency' => 'USD',
            'items' => [
                ['description' => 'Consulting', 'quantity' => 10, 'unit_amount' => '100.00'],
                ['description' => 'Hosting', 'quantity' => '2.5', 'unit_amount' => '100.00'],
            ],
            'due_at' => new DateTimeImmutable('2026-10-15 18:30:00', new DateTimeZone('America/New_York')),
            'send_email' => true,
        ]);

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/invoices', $request->getUri()->getPath());
        $this->assertSame('2026-10-15', self::body($request)['due_at']);
        $this->assertTrue(self::body($request)['send_email']);
        $this->assertSame('w7c2h5j9d3kx', $invoice->id);
    }

    public function test_an_invoice_is_read_into_typed_properties(): void
    {
        $this->http->json(['data' => self::fixture('invoice')]);

        $invoice = $this->client()->invoices->retrieve('w7c2h5j9d3kx');

        $this->assertSame('/v1/invoices/w7c2h5j9d3kx', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('INV-0042', $invoice->number);
        $this->assertSame(InvoiceStatus::Open->value, $invoice->status);
        $this->assertTrue($invoice->isOpen());
        $this->assertFalse($invoice->overdue);
        $this->assertSame('Grace Hopper', $invoice->customerName);
        $this->assertSame('1250.00', $invoice->amount);
        $this->assertCount(2, $invoice->items);
        $this->assertSame('2.5', $invoice->items[1]->quantity);
        $this->assertSame('250.00', $invoice->items[1]->total);
        $this->assertEquals(new DateTimeImmutable('2026-10-15T00:00:00Z'), $invoice->dueAt);
        $this->assertSame('https://velirapay.com/i/w7c2h5j9d3kx', $invoice->hostedUrl);
        $this->assertSame('https://velirapay.com/i/w7c2h5j9d3kx/document', $invoice->documentUrl);
        $this->assertSame([['id' => 'm2q7w4e9r1ty', 'status' => 'expired', 'asset' => 'ETH']], $invoice->charges);
        $this->assertEquals(new DateTimeImmutable('2026-09-21T08:00:00Z'), $invoice->sentAt);
        $this->assertNull($invoice->paidAt);
    }

    public function test_an_invoice_is_sent_and_voided(): void
    {
        $this->http
            ->json(['data' => self::fixture('invoice')])
            ->json(['data' => ['status' => 'void'] + self::fixture('invoice')]);

        $this->client()->invoices->send('w7c2h5j9d3kx');

        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/invoices/w7c2h5j9d3kx/send', $this->http->lastRequest()->getUri()->getPath());

        $invoice = $this->client()->invoices->void('w7c2h5j9d3kx');

        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/invoices/w7c2h5j9d3kx/void', $this->http->lastRequest()->getUri()->getPath());
        $this->assertTrue($invoice->isVoid());
    }

    public function test_invoices_are_listed_by_status(): void
    {
        $this->http->json(self::listResponse([self::fixture('invoice')]));

        $page = $this->client()->invoices->list(['status' => InvoiceStatus::Open]);

        $this->assertSame(['status' => 'open'], self::query($this->http->lastRequest()));
        $this->assertInstanceOf(Invoice::class, $page->data[0]);
    }
}
