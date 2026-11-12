<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * One day of an overview's period.
 */
final class OverviewDay extends ApiResource
{
    /**
     * Create a new overview day.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The day, as midnight UTC. */
        public readonly ?DateTimeImmutable $date,
        /** What was paid that day, in the overview's currency. */
        public readonly string $volume,
        /** What the payouts confirmed that day came to, at their charges' locked rates. */
        public readonly string $sentVolume,
        /** How many charges were paid that day. */
        public readonly int $paidCharges,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview day from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            date: self::date($data, 'date'),
            volume: self::string($data, 'volume', '0'),
            sentVolume: self::string($data, 'sent_volume', '0'),
            paidCharges: self::int($data, 'paid_charges'),
        );
    }
}
