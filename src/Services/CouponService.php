<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use DateTimeInterface;
use Generator;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Coupon;

/**
 * The /v1/coupons endpoints, where a coupon's id is its code.
 */
final class CouponService extends Service
{
    /**
     * Create a coupon taking either percent_off or amount_off; currency is required with amount_off or min_amount.
     *
     * @param  array{code: string, percent_off?: numeric-string|int|float|null, amount_off?: numeric-string|int|float|null, currency?: string|null, min_amount?: numeric-string|int|float|null, max_redemptions?: int|null, once_per_customer?: bool, payment_links?: list<string>|null, active?: bool, expires_at?: DateTimeInterface|string|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): Coupon
    {
        return Coupon::fromArray($this->request('POST', '/v1/coupons', $params, $idempotencyKey));
    }

    /**
     * Retrieve a coupon by its code, in any case.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): Coupon
    {
        return Coupon::fromArray($this->request('GET', self::path('/v1/coupons/%s', $id)));
    }

    /**
     * Change a coupon's limits; its code, discount, currency and minimum price cannot change.
     *
     * @param  array{max_redemptions?: int|null, once_per_customer?: bool, payment_links?: list<string>|null, active?: bool, expires_at?: DateTimeInterface|string|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(string $id, array $params, ?string $idempotencyKey = null): Coupon
    {
        return Coupon::fromArray($this->request('PATCH', self::path('/v1/coupons/%s', $id), $params, $idempotencyKey));
    }

    /**
     * List coupons, newest first.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<Coupon>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/coupons', $params, Coupon::fromArray(...));
    }

    /**
     * Iterate over every coupon, newest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, Coupon>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Delete a coupon; charges that used it keep its code and their discount.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $this->transport->request('DELETE', self::path('/v1/coupons/%s', $id), [], null, $idempotencyKey);
    }
}
