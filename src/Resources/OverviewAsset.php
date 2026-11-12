<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * One coin's share of the period's paid charges.
 */
final class OverviewAsset extends ApiResource
{
    /**
     * Create a new overview asset.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** One of the Asset values: the coin. */
        public readonly string $asset,
        /** How many charges were paid in it. */
        public readonly int $paidCharges,
        /** Its share of the paid charges, in percent. */
        public readonly float $sharePercent,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview asset from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            asset: self::string($data, 'asset'),
            paidCharges: self::int($data, 'paid_charges'),
            sharePercent: self::nullableFloat($data, 'share_percent') ?? 0.0,
        );
    }
}
