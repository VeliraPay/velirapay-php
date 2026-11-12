<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Enums\MemberRole;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Member;

/**
 * The /v1/members endpoints. Reading needs members:read; changing a role or removing someone needs members:write on a key an owner made.
 */
final class MemberService extends Service
{
    /**
     * List the account's members, oldest first.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<Member>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/members', $params, Member::fromArray(...));
    }

    /**
     * Iterate over every member, oldest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, Member>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Give a member the role admin, finance, support or viewer, on a key an owner made. An owner is never changed this way.
     *
     * @param  array{role: MemberRole|string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(string $id, array $params, ?string $idempotencyKey = null): Member
    {
        return Member::fromArray($this->request('PATCH', self::path('/v1/members/%s', $id), $params, $idempotencyKey));
    }

    /**
     * Remove a member, which also revokes the API keys they made. An owner is never removed this way.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function remove(string $id, ?string $idempotencyKey = null): void
    {
        $this->transport->request('DELETE', self::path('/v1/members/%s', $id), [], null, $idempotencyKey);
    }
}
