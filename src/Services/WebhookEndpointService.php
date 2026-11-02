<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\WebhookEndpoint;

/**
 * The /v1/webhook-endpoints endpoint.
 */
final class WebhookEndpointService extends Service
{
    /**
     * List the account's webhook endpoints in the API key's mode, newest first.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<WebhookEndpoint>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/webhook-endpoints', $params, WebhookEndpoint::fromArray(...));
    }

    /**
     * Iterate over every webhook endpoint, newest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, WebhookEndpoint>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }
}
