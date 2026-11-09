<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Invitation;

/**
 * The /v1/invitations endpoints. Reading needs members:read and changing members:write.
 */
final class InvitationService extends Service
{
    /**
     * List the invitations nobody has accepted yet, newest first, expired ones included.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<Invitation>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/invitations', $params, Invitation::fromArray(...));
    }

    /**
     * Iterate over every waiting invitation, newest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, Invitation>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Email someone an invitation to join as an "admin", "finance", "support" or "viewer", in the name of whoever made the API key.
     *
     * @param  array{email: string, role: string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): Invitation
    {
        return Invitation::fromArray($this->request('POST', '/v1/invitations', $params, $idempotencyKey));
    }

    /**
     * Withdraw an invitation, so its link stops working.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function cancel(string $id, ?string $idempotencyKey = null): void
    {
        $this->transport->request('DELETE', self::path('/v1/invitations/%s', $id), [], null, $idempotencyKey);
    }
}
