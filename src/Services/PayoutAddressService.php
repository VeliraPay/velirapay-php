<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use VeliraPay\Enums\AddressNetwork;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Resources\PayoutAddress;

/**
 * The /v1/payout-addresses endpoints. Reading needs payout_addresses:read; changing needs payout_addresses:write and an owner's switch under Payment settings.
 */
final class PayoutAddressService extends Service
{
    /**
     * List where payouts go in the API key's mode: one entry per network with coins on offer, its address null while there is none.
     * Whether an owner lets API keys change them is in the answer's meta, read as lastResponse()?->meta()['api_payout_address_changes'].
     *
     * @return list<PayoutAddress>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(): array
    {
        return self::items($this->respond('GET', '/v1/payout-addresses'), PayoutAddress::fromArray(...));
    }

    /**
     * Point the payouts of one network, such as AddressNetwork::Bitcoin or "evm", at an address.
     *
     * @param  array{address: string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(AddressNetwork|string $network, array $params, ?string $idempotencyKey = null): PayoutAddress
    {
        return PayoutAddress::fromArray($this->request('PUT', self::path('/v1/payout-addresses/%s', self::network($network)), $params, $idempotencyKey));
    }

    /**
     * Remove the address of one network, so its payouts wait for a new one.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function delete(AddressNetwork|string $network, ?string $idempotencyKey = null): PayoutAddress
    {
        return PayoutAddress::fromArray($this->request('DELETE', self::path('/v1/payout-addresses/%s', self::network($network)), null, $idempotencyKey));
    }

    /**
     * Get the name of a network as the paths take it.
     */
    private static function network(AddressNetwork|string $network): string
    {
        return $network instanceof AddressNetwork ? $network->value : $network;
    }
}
