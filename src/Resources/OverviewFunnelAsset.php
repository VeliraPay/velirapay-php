<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * How the period's attempts to pay in one coin ended.
 */
final class OverviewFunnelAsset extends ApiResource
{
    /**
     * Create a new overview funnel asset.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** One of the Asset values: the coin. */
        public readonly string $asset,
        /** How many attempts to pay in it began. */
        public readonly int $started,
        /** How many were paid. */
        public readonly int $paid,
        /** How many expired or were canceled before any transfer arrived. */
        public readonly int $abandoned,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview funnel asset from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            asset: self::string($data, 'asset'),
            started: self::int($data, 'started'),
            paid: self::int($data, 'paid'),
            abandoned: self::int($data, 'abandoned'),
        );
    }
}
