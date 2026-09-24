<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use VeliraPay\Enums\PricingType;
use VeliraPay\Resources\PaymentLink;

final class PaymentLinkServiceTest extends TestCase
{
    public function test_a_payment_link_is_created_with_enums_and_dates_converted(): void
    {
        $this->http->json(['data' => self::fixture('payment_link')], 201);

        $link = $this->client()->paymentLinks->create([
            'title' => 'Tip jar',
            'pricing_type' => PricingType::Open,
            'currency' => 'USD',
            'suggested_amounts' => ['5.00', '10.00', '25.00'],
            'custom_fields' => [['label' => 'Message', 'required' => false]],
            'expires_at' => new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
        ]);

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/payment-links', $request->getUri()->getPath());
        $this->assertSame([
            'title' => 'Tip jar',
            'pricing_type' => 'open',
            'currency' => 'USD',
            'suggested_amounts' => ['5.00', '10.00', '25.00'],
            'custom_fields' => [['label' => 'Message', 'required' => false]],
            'expires_at' => '2026-12-31T23:59:59+00:00',
        ], self::body($request));
        $this->assertSame('p4n8r2t6y1ua', $link->id);
    }

    public function test_a_payment_link_is_read_into_typed_properties(): void
    {
        $this->http->json(['data' => self::fixture('payment_link')]);

        $link = $this->client()->paymentLinks->retrieve('p4n8r2t6y1ua');

        $this->assertSame('/v1/payment-links/p4n8r2t6y1ua', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('Tip jar', $link->title);
        $this->assertSame(PricingType::Open->value, $link->pricingType);
        $this->assertFalse($link->hasFixedAmount());
        $this->assertNull($link->amount);
        $this->assertSame(['5.00', '10.00', '25.00'], $link->suggestedAmounts);
        $this->assertSame('1.00', $link->minAmount);
        $this->assertSame('500.00', $link->maxAmount);
        $this->assertNull($link->acceptedAssets);
        $this->assertTrue($link->isActive());
        $this->assertTrue($link->collectName);
        $this->assertFalse($link->requireEmail);
        $this->assertSame([['label' => 'Message', 'required' => false]], $link->customFields);
        $this->assertNull($link->expiresAt);
        $this->assertSame(100, $link->maxPayments);
        $this->assertTrue($link->acceptingPayments);
        $this->assertSame(12, $link->viewsCount);
        $this->assertSame('https://velirapay.com/pay/p4n8r2t6y1ua', $link->checkoutUrl);
        $this->assertEquals(new DateTimeImmutable('2026-09-02T08:30:00Z'), $link->updatedAt);
    }

    public function test_a_payment_link_is_updated(): void
    {
        $this->http->json(['data' => ['title' => 'Coffee'] + self::fixture('payment_link')]);

        $link = $this->client()->paymentLinks->update('p4n8r2t6y1ua', ['title' => 'Coffee', 'accepted_assets' => null]);

        $request = $this->http->lastRequest();
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/v1/payment-links/p4n8r2t6y1ua', $request->getUri()->getPath());
        $this->assertSame(['title' => 'Coffee', 'accepted_assets' => null], self::body($request));
        $this->assertSame('Coffee', $link->title);
    }

    public function test_a_payment_link_is_archived_and_restored(): void
    {
        $this->http
            ->json(['data' => ['status' => 'archived'] + self::fixture('payment_link')])
            ->json(['data' => self::fixture('payment_link')]);

        $archived = $this->client()->paymentLinks->archive('p4n8r2t6y1ua');

        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/payment-links/p4n8r2t6y1ua/archive', $this->http->lastRequest()->getUri()->getPath());
        $this->assertTrue($archived->isArchived());

        $restored = $this->client()->paymentLinks->restore('p4n8r2t6y1ua');

        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/payment-links/p4n8r2t6y1ua/archive', $this->http->lastRequest()->getUri()->getPath());
        $this->assertTrue($restored->isActive());
    }

    public function test_payment_links_are_listed(): void
    {
        $this->http->json(self::listResponse([self::fixture('payment_link')]));

        $page = $this->client()->paymentLinks->list(['per_page' => 10]);

        $this->assertSame(['per_page' => '10'], self::query($this->http->lastRequest()));
        $this->assertInstanceOf(PaymentLink::class, $page->data[0]);
    }
}
