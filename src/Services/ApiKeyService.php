<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Enums\ApiKeyPermission;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\ApiKey;

/**
 * The /v1/api-keys endpoints. Reading needs api_keys:read and changing api_keys:write, and a key never reaches further than the key that asks.
 */
final class ApiKeyService extends Service
{
    /**
     * List the account's active keys in the API key's mode, newest first, without the keys themselves.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<ApiKey>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/api-keys', $params, ApiKey::fromArray(...));
    }

    /**
     * Iterate over every active key, newest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, ApiKey>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Create a key in the API key's mode. Its key property holds the key itself, shown this once; leaving permissions out gives full access.
     *
     * @param  array{name: string, permissions?: list<ApiKeyPermission|string>|null, allowed_ips?: list<string>|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): ApiKey
    {
        return ApiKey::fromArray($this->request('POST', '/v1/api-keys', $params, $idempotencyKey));
    }

    /**
     * Change a key's name, permissions or allowed_ips, only those given; null permissions gives full access and null allowed_ips anywhere.
     *
     * @param  array{name?: string, permissions?: list<ApiKeyPermission|string>|null, allowed_ips?: list<string>|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(string $id, array $params, ?string $idempotencyKey = null): ApiKey
    {
        return ApiKey::fromArray($this->request('PATCH', self::path('/v1/api-keys/%s', $id), $params, $idempotencyKey));
    }

    /**
     * Revoke a key, which is refused from its next request.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function revoke(string $id, ?string $idempotencyKey = null): void
    {
        $this->transport->request('DELETE', self::path('/v1/api-keys/%s', $id), [], null, $idempotencyKey);
    }
}
