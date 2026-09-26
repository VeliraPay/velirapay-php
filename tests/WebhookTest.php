<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use VeliraPay\Enums\EventType;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Exceptions\SignatureVerificationException;
use VeliraPay\Webhooks\Webhook;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_2b7Qf9LkXw3NcR8vT1mZ5pYs0HdJ4gUe6AoKiVbE';

    public function test_a_signed_charge_delivery_is_verified_and_parsed(): void
    {
        $payload = self::payload('webhook_charge');

        $event = Webhook::constructEvent($payload, Webhook::signatureHeader($payload, self::SECRET), self::SECRET);

        $this->assertSame('5f0c6f5e-8d1b-4a53-9a57-3c2b1d0e9f87', $event->id);
        $this->assertSame('9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b', $event->eventId);
        $this->assertSame('charge.paid', $event->type);
        $this->assertTrue($event->is(EventType::ChargePaid));
        $this->assertTrue($event->isLiveMode());
        $this->assertFalse($event->test);
        $this->assertEquals(new DateTimeImmutable('2026-09-20T10:05:42Z'), $event->createdAt);
        $this->assertNull($event->invoice);
        $this->assertNull($event->transaction);

        $charge = $event->charge;
        $this->assertNotNull($charge);
        $this->assertSame('k3v9x2m7q8wz', $charge->id);
        $this->assertTrue($charge->isPaid());
        $this->assertSame('p4n8r2t6y1ua', $charge->paymentLink);
        $this->assertSame('0.002500000000000000', $charge->assetAmount);
        $this->assertSame(2, $charge->transactions[0]->confirmations);
        $this->assertSame(2, $charge->transactions[0]->requiredConfirmations);
        $this->assertSame('203.0.113.7', $charge->customer->ipAddress);
        $this->assertSame('ada@example.com', $charge->customer->email);
        $this->assertSame(['order_id' => '1042'], $charge->metadata);
        $this->assertSame([], $charge->refunds);
        $this->assertNull($charge->checkoutUrl);
    }

    public function test_a_payment_detected_delivery_carries_the_transfer_that_was_seen(): void
    {
        $payload = self::payload('webhook_payment_detected');

        $event = Webhook::constructEvent($payload, Webhook::signatureHeader($payload, self::SECRET), self::SECRET);

        $this->assertTrue($event->is(EventType::ChargePaymentDetected));
        $this->assertTrue($event->charge?->isPending());

        $transaction = $event->transaction;
        $this->assertNotNull($transaction);
        $this->assertSame('7d1c5e9a3b2f8e6d4c0a9b8e7f6d5c4b3a2918f7e6d5c4b3a29180f7e6d5c4b3', $transaction->txid);
        $this->assertSame('0.002500000000000000', $transaction->amount);
        $this->assertSame(0, $transaction->confirmations);
        $this->assertSame(2, $transaction->requiredConfirmations);
        $this->assertFalse($transaction->credited);
        $this->assertStringStartsWith('https://mempool.space/tx/', (string) $transaction->explorerUrl);
        $this->assertEquals(new DateTimeImmutable('2026-09-20T09:58:12Z'), $transaction->seenAt);
    }

    public function test_a_signed_invoice_delivery_is_parsed(): void
    {
        $payload = self::payload('webhook_invoice');

        $event = Webhook::constructEvent($payload, Webhook::signatureHeader($payload, self::SECRET), self::SECRET);

        $this->assertSame('invoice.paid', $event->type);
        $this->assertFalse($event->isLiveMode());
        $this->assertNull($event->charge);

        $invoice = $event->invoice;
        $this->assertNotNull($invoice);
        $this->assertSame('w7c2h5j9d3kx', $invoice->id);
        $this->assertTrue($invoice->isPaid());
        $this->assertNull($invoice->overdue);
        $this->assertNull($invoice->documentUrl);
        $this->assertSame(['m2q7w4e9r1ty', 'z8x6c4v2b0nm'], array_column($invoice->charges, 'id'));
        $this->assertEquals(new DateTimeImmutable('2026-10-15T00:00:00Z'), $invoice->dueAt);
    }

    public function test_signatures_made_by_velirapay_are_accepted(): void
    {
        $payload = '{"id":"5f0c6f5e-8d1b-4a53-9a57-3c2b1d0e9f87","event_id":"9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b","event":"charge.paid","mode":"live","created_at":"2026-09-20T10:05:42.000000Z","data":{"charge":{"code":"k3v9x2m7q8wz","status":"paid"}}}';
        $header = 't=1790000000,v1=a7a0dd3b10e4302cf840472806bb1c3a75453b7f8a6975c6475f92d58826df35';

        Webhook::verifySignature($payload, $header, self::SECRET, now: 1790000060);

        $this->assertSame($header, Webhook::signatureHeader($payload, self::SECRET, 1790000000));
    }

    public function test_a_changed_payload_is_rejected(): void
    {
        $payload = self::payload('webhook_charge');
        $header = Webhook::signatureHeader($payload, self::SECRET);

        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent(str_replace('150.00', '1.00', $payload), $header, self::SECRET);
    }

    public function test_a_signature_made_with_another_secret_is_rejected(): void
    {
        $payload = self::payload('webhook_charge');

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('does not match');

        Webhook::constructEvent($payload, Webhook::signatureHeader($payload, 'whsec_other'), self::SECRET);
    }

    public function test_an_old_signature_is_rejected_unless_the_tolerance_is_off(): void
    {
        $payload = self::payload('webhook_charge');
        $header = Webhook::signatureHeader($payload, self::SECRET, time() - 301);

        Webhook::verifySignature($payload, $header, self::SECRET, tolerance: 0);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('more than the 300 allowed');

        Webhook::verifySignature($payload, $header, self::SECRET);
    }

    public function test_one_matching_signature_among_several_is_enough(): void
    {
        $payload = self::payload('webhook_charge');
        $valid = Webhook::signatureHeader($payload, self::SECRET);
        [$timestamp, $signature] = explode(',', $valid);

        $event = Webhook::constructEvent($payload, "{$timestamp},v1=".str_repeat('0', 64).",{$signature}", self::SECRET);

        $this->assertSame('charge.paid', $event->type);
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function malformedHeaders(): iterable
    {
        yield 'missing' => [null, 'header is missing'];
        yield 'empty' => ['', 'header is missing'];
        yield 'garbage' => ['garbage', 'has no timestamp'];
        yield 'timestamp that is not a number' => ['t=yesterday,v1=abc', 'has no timestamp'];
        yield 'no signature' => ['t=1790000000', 'has no v1 signature'];
        yield 'another scheme only' => ['t=1790000000,v0=abc', 'has no v1 signature'];
    }

    #[DataProvider('malformedHeaders')]
    public function test_a_malformed_header_is_rejected(?string $header, string $message): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage($message);

        Webhook::constructEvent(self::payload('webhook_charge'), $header, self::SECRET);
    }

    public function test_an_empty_secret_is_a_configuration_error(): void
    {
        $payload = self::payload('webhook_charge');

        $this->expectException(InvalidArgumentException::class);

        Webhook::constructEvent($payload, Webhook::signatureHeader($payload, ''), '');
    }

    public function test_a_psr7_request_is_verified_and_parsed(): void
    {
        $payload = self::payload('webhook_charge');
        $request = new ServerRequest('POST', 'https://shop.example/webhooks/velirapay', [
            Webhook::SIGNATURE_HEADER => Webhook::signatureHeader($payload, self::SECRET),
            Webhook::EVENT_HEADER => 'charge.paid',
            Webhook::DELIVERY_HEADER => '5f0c6f5e-8d1b-4a53-9a57-3c2b1d0e9f87',
        ], $payload);

        $event = Webhook::constructEventFromRequest($request, self::SECRET);

        $this->assertSame($request->getHeaderLine(Webhook::DELIVERY_HEADER), $event->id);
    }

    public function test_test_deliveries_from_the_dashboard_are_flagged(): void
    {
        $payload = '{"id":"3c9e1f7a-5b2d-4e8c-9a1f-7b3d5e9c1a2f","event":"charge.paid","test":true,"mode":"test","created_at":"2026-09-24T09:00:00.000000Z","data":{"charge":{"code":"testab12cd34","status":"paid"}}}';

        $event = Webhook::constructEvent($payload, Webhook::signatureHeader($payload, self::SECRET), self::SECRET);

        $this->assertTrue($event->test);
        $this->assertNull($event->eventId);
        $this->assertSame('testab12cd34', $event->charge?->id);
    }

    public function test_a_signed_payload_that_is_not_a_json_object_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Webhook::constructEvent('["charge.paid"]', Webhook::signatureHeader('["charge.paid"]', self::SECRET), self::SECRET);
    }

    /**
     * Read a webhook payload fixture as raw JSON.
     */
    private static function payload(string $name): string
    {
        return (string) file_get_contents(__DIR__."/Fixtures/{$name}.json");
    }
}
