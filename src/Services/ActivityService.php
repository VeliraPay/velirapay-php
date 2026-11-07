<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Enums\Mode;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Activity;

/**
 * The /v1/activity endpoint. Needs activity:read.
 */
final class ActivityService extends Service
{
    /**
     * List the account's activity log, newest first: the API key's mode, and what belongs to none, such as sign-ins. action takes an action or "sensitive", q a subject or the start of an IP address, from and to a YYYY-MM-DD date, and network the key's own mode, to leave out what belongs to none.
     *
     * @param  array{action?: string, person?: string, q?: string, from?: string, to?: string, network?: Mode|string, page?: int, per_page?: int}  $params
     * @return Page<Activity>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/activity', $params, Activity::fromArray(...));
    }

    /**
     * Iterate over the whole activity log, newest first, fetching pages as they are reached.
     *
     * @param  array{action?: string, person?: string, q?: string, from?: string, to?: string, network?: Mode|string, per_page?: int}  $params
     * @return Generator<int, Activity>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }
}
