<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * A charge waiting on a person, with what it asked for and what arrived.
 */
final class OverviewChargeAmount extends ApiResource
{
    /**
     * Create a new overview charge amount.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The charge's id. */
        public readonly string $id,
        /** One of the Asset values: the coin it is in. */
        public readonly string $asset,
        /** What it asked for, in the coin. */
        public readonly string $amount,
        /** What arrived, in the coin: what an underpaid charge received, or a late payment. */
        public readonly string $receivedAmount,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview charge amount from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            asset: self::string($data, 'asset'),
            amount: self::string($data, 'amount', '0'),
            receivedAmount: self::string($data, 'received_amount', '0'),
        );
    }
}
