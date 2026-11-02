<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use DateTimeInterface;
use Generator;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\ChargeStatus;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Charge;

/**
 * The /v1/charges endpoints.
 */
final class ChargeService extends Service
{
    /**
     * Charge a customer: create a charge with what you already know about them, and send them to its checkout URL.
     *
     * @param  array{asset: Asset|string, amount?: numeric-string|int|float, currency?: string, payment_link?: string, customer?: array{email?: string, name?: string, ip_address?: string, user_agent?: string, reference?: string, phone?: string, country?: string, metadata?: array<string, scalar|null>}, customer_email?: string, coupon?: string, description?: string, metadata?: array<string, scalar|null>, custom_fields?: list<array{label: string, value: string}>, success_url?: string, cancel_url?: string, success_message?: string, send_email?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', '/v1/charges', $params, $idempotencyKey));
    }

    /**
     * Retrieve a charge by its id.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): Charge
    {
        return Charge::fromArray($this->request('GET', self::path('/v1/charges/%s', $id)));
    }

    /**
     * List charges, newest first.
     *
     * @param  array{status?: ChargeStatus|string, payment_link?: string, asset?: Asset|string, search?: string, created_from?: DateTimeInterface|string, created_to?: DateTimeInterface|string, page?: int, per_page?: int}  $params
     * @return Page<Charge>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/charges', $params, Charge::fromArray(...));
    }

    /**
     * Iterate over every charge, newest first, fetching pages as they are reached.
     *
     * @param  array{status?: ChargeStatus|string, payment_link?: string, asset?: Asset|string, search?: string, created_from?: DateTimeInterface|string, created_to?: DateTimeInterface|string, per_page?: int}  $params
     * @return Generator<int, Charge>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Cancel a pending charge so it can no longer be paid.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function cancel(string $id, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/cancel', $id), null, $idempotencyKey));
    }

    /**
     * Record a refund you sent the customer from your own wallet.
     *
     * @param  array{amount: numeric-string|int|float, txid?: string, reason?: string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function recordRefund(string $id, array $params, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/refunds', $id), $params, $idempotencyKey));
    }

    /**
     * Email the customer the payment page of a pending charge.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function send(string $id, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/send', $id), null, $idempotencyKey));
    }

    /**
     * Accept an underpaid charge, or a late payment on an expired or canceled one, as paid for what arrived.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function accept(string $id, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/accept', $id), null, $idempotencyKey));
    }

    /**
     * Pay a test-mode charge without coins, in full or by a chosen amount in the charge's coin.
     *
     * @param  array{amount?: numeric-string|int|float}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function simulate(string $id, array $params = [], ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/simulate', $id), $params === [] ? null : $params, $idempotencyKey));
    }
}
