<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;
use VeliraPay\Resources\Coupon;

final class CouponServiceTest extends TestCase
{
    public function test_a_coupon_is_created_with_dates_converted(): void
    {
        $this->http->json(['data' => self::fixture('coupon')], 201);

        $coupon = $this->client()->coupons->create([
            'code' => 'spring10',
            'percent_off' => '10',
            'max_redemptions' => 100,
            'once_per_customer' => true,
            'payment_links' => ['p4n8r2t6y1ua'],
            'expires_at' => new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
        ], 'spring-sale');

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/coupons', $request->getUri()->getPath());
        $this->assertSame('spring-sale', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame([
            'code' => 'spring10',
            'percent_off' => '10',
            'max_redemptions' => 100,
            'once_per_customer' => true,
            'payment_links' => ['p4n8r2t6y1ua'],
            'expires_at' => '2026-12-31T23:59:59+00:00',
        ], self::body($request));
        $this->assertSame('SPRING10', $coupon->code);
    }

    public function test_a_coupon_is_read_into_typed_properties(): void
    {
        $this->http->json(['data' => self::fixture('coupon')]);

        $coupon = $this->client()->coupons->retrieve('spring10');

        $this->assertSame('/v1/coupons/spring10', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('SPRING10', $coupon->id);
        $this->assertSame('SPRING10', $coupon->code);
        $this->assertTrue($coupon->isPercentage());
        $this->assertSame('10.00', $coupon->percentOff);
        $this->assertNull($coupon->amountOff);
        $this->assertNull($coupon->currency);
        $this->assertNull($coupon->minAmount);
        $this->assertSame(100, $coupon->maxRedemptions);
        $this->assertSame(4, $coupon->timesRedeemed);
        $this->assertTrue($coupon->oncePerCustomer);
        $this->assertSame(['p4n8r2t6y1ua'], $coupon->paymentLinks);
        $this->assertTrue($coupon->active);
        $this->assertEquals(new DateTimeImmutable('2026-12-31T23:59:59Z'), $coupon->expiresAt);
        $this->assertEquals(new DateTimeImmutable('2026-10-15T09:30:00Z'), $coupon->updatedAt);
    }

    public function test_an_amount_off_coupon_names_its_currency(): void
    {
        $this->http->json(['data' => ['percent_off' => null, 'amount_off' => '5.00', 'currency' => 'EUR', 'min_amount' => '20.00'] + self::fixture('coupon')]);

        $coupon = $this->client()->coupons->retrieve('SPRING10');

        $this->assertFalse($coupon->isPercentage());
        $this->assertSame('5.00', $coupon->amountOff);
        $this->assertSame('EUR', $coupon->currency);
        $this->assertSame('20.00', $coupon->minAmount);
    }

    public function test_a_coupons_limits_are_updated(): void
    {
        $this->http->json(['data' => ['active' => false, 'payment_links' => []] + self::fixture('coupon')]);

        $coupon = $this->client()->coupons->update('SPRING10', ['active' => false, 'payment_links' => [], 'expires_at' => null]);

        $request = $this->http->lastRequest();
        $this->assertSame('PATCH', $request->getMethod());
        $this->assertSame('/v1/coupons/SPRING10', $request->getUri()->getPath());
        $this->assertSame(['active' => false, 'payment_links' => [], 'expires_at' => null], self::body($request));
        $this->assertFalse($coupon->active);
        $this->assertSame([], $coupon->paymentLinks);
    }

    public function test_coupons_are_listed(): void
    {
        $this->http->json(self::listResponse([self::fixture('coupon')]));

        $page = $this->client()->coupons->list(['per_page' => 10]);

        $this->assertSame('/v1/coupons', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['per_page' => '10'], self::query($this->http->lastRequest()));
        $this->assertInstanceOf(Coupon::class, $page->data[0]);
    }

    public function test_a_coupon_is_deleted(): void
    {
        $this->http->queue(new Response(204));

        $this->client()->coupons->delete('SPRING10');

        $request = $this->http->lastRequest();
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('/v1/coupons/SPRING10', $request->getUri()->getPath());
    }
}
