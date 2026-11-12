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
     * @param  array{asset: Asset|string, amount?: numeric-string|int|float, currency?: string, payment_link?: string|null, customer?: array{email?: string|null, name?: string|null, ip_address?: string|null, user_agent?: string|null, reference?: string|null, phone?: string|null, country?: string|null, metadata?: array<string, scalar|null>|null}|null, customer_email?: string|null, coupon?: string|null, description?: string|null, metadata?: array<string, scalar|null>|null, custom_fields?: list<array{label: string, value: string}>|null, success_url?: string|null, cancel_url?: string|null, success_message?: string|null, send_email?: bool}  $params
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
     * List charges, newest first. A DateTimeInterface given as created_from or created_to is sent as the same moment in UTC.
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
     * Record a refund you sent the customer from your own wallet. An underpaid charge or a late payment is accepted first,
     * and the network surcharge is never refundable; the API answers 422 otherwise.
     *
     * @param  array{amount: numeric-string|int|float, txid?: string|null, reason?: string|null}  $params
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
     * Accept an underpaid charge, or a late payment on an expired or canceled one, as paid for what arrived and confirmed.
     * The API answers 409 for any other charge, and for an underpaid one while a payment to it is still confirming.
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
     * @param  array{amount?: numeric-string|int|float|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function simulate(string $id, array $params = [], ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/simulate', $id), $params === [] ? null : $params, $idempotencyKey));
    }
}
