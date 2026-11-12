<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * The payouts in one coin waiting for a payout address.
 */
final class OverviewWaitingPayouts extends ApiResource
{
    /**
     * Create a new overview waiting payouts.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** One of the Asset values: the coin. */
        public readonly string $asset,
        /** How many payouts wait. */
        public readonly int $count,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview waiting payouts from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            asset: self::string($data, 'asset'),
            count: self::int($data, 'count'),
        );
    }
}
