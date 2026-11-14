<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * The processing fee and forwarding limit fixed when a charge was quoted.
 */
final class ProcessingTerms extends ApiResource
{
    /**
     * Create new processing terms.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The processing fee, as a percentage of what the charge takes in, such as "1.75". */
        public readonly string $percent,
        /** The most managed forwarding may cost, as a percentage of the merchant's share, such as 10. */
        public readonly int $maxForwardingPercent,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create processing terms from their API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            percent: self::string($data, 'percent', '0'),
            maxForwardingPercent: self::int($data, 'max_forwarding_percent'),
        );
    }
}
