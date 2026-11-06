<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * Where the account's payouts on one network go.
 */
final class PayoutAddress extends ApiResource
{
    /**
     * Create a new payout address.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $assets
     */
    public function __construct(
        array $attributes,
        /** The network, such as "bitcoin" or "evm", which is also its id in the paths. */
        public readonly string $network,
        /** The network's name. */
        public readonly string $name,
        /** Where payouts go, null while there is no address: payouts in its coins then wait for one. */
        public readonly ?string $address,
        /** The Asset values on offer on this network. */
        public readonly array $assets,
        /** Until when payouts to a newly saved address wait. */
        public readonly ?DateTimeImmutable $payoutsHeldUntil,
        /** When the address was last saved. */
        public readonly ?DateTimeImmutable $updatedAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a payout address from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            network: self::string($data, 'network'),
            name: self::string($data, 'name'),
            address: self::nullableString($data, 'address'),
            assets: self::strings($data, 'assets'),
            payoutsHeldUntil: self::date($data, 'payouts_held_until'),
            updatedAt: self::date($data, 'updated_at'),
        );
    }
}
