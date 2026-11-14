<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * The quote a charge first had, before VeliraPay quoted it again in the coin that arrived at its address.
 */
final class OriginalQuote extends ApiResource
{
    /**
     * Create a new original quote.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** One of the Asset values: the coin the charge was first quoted in, such as "TRX". */
        public readonly string $asset,
        /** The amount of that coin the first quote asked for. */
        public readonly string $assetAmount,
        /** Fiat per one unit of that coin, as the first quote locked it. */
        public readonly string $exchangeRate,
        /** When the charge was quoted again in the coin that arrived. */
        public readonly ?DateTimeImmutable $switchedAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an original quote from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            asset: self::string($data, 'asset'),
            assetAmount: self::string($data, 'asset_amount', '0'),
            exchangeRate: self::string($data, 'exchange_rate', '0'),
            switchedAt: self::date($data, 'switched_at'),
        );
    }
}
