<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Enums\EventType;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Event;

/**
 * The /v1/events endpoints.
 */
final class EventService extends Service
{
    /**
     * Retrieve an event by its id.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): Event
    {
        return Event::fromArray($this->request('GET', self::path('/v1/events/%s', $id)));
    }

    /**
     * List events, newest first.
     *
     * @param  array{type?: EventType|string, charge?: string, invoice?: string, page?: int, per_page?: int}  $params
     * @return Page<Event>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/events', $params, Event::fromArray(...));
    }

    /**
     * Iterate over every event, newest first, fetching pages as they are reached.
     *
     * @param  array{type?: EventType|string, charge?: string, invoice?: string, per_page?: int}  $params
     * @return Generator<int, Event>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }
}
