<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Resources\Account;

/**
 * The /v1/account endpoint.
 */
final class AccountService extends Service
{
    /**
     * Retrieve the account the API key belongs to, with the coins it accepts in the key's mode.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(): Account
    {
        $response = $this->transport->request('GET', '/v1/account');
        $meta = $response->json()['meta'] ?? null;

        /** @var array<string, mixed> $meta */
        $meta = is_array($meta) ? $meta : [];

        return Account::fromArray(self::data($response), $meta);
    }
}
