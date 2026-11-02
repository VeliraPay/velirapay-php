<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\PayoutStatus;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Payout;
use VeliraPay\Resources\PayoutSummary;

/**
 * The /v1/payouts endpoints.
 */
final class PayoutService extends Service
{
    /**
     * Retrieve the payout of a charge, by the charge's id.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $charge): Payout
    {
        return Payout::fromArray($this->request('GET', self::path('/v1/payouts/%s', $charge)));
    }

    /**
     * List payouts, newest first.
     *
     * @param  array{status?: PayoutStatus|string, asset?: Asset|string, search?: string, page?: int, per_page?: int}  $params
     * @return Page<Payout>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/payouts', $params, Payout::fromArray(...));
    }

    /**
     * Iterate over every payout, newest first, fetching pages as they are reached.
     *
     * @param  array{status?: PayoutStatus|string, asset?: Asset|string, search?: string, per_page?: int}  $params
     * @return Generator<int, Payout>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Add up, per coin, what has been paid out, what is on its way and what is still owed.
     *
     * @return list<PayoutSummary>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function summary(): array
    {
        return $this->page('/v1/payouts/summary', [], PayoutSummary::fromArray(...))->data;
    }
}
