<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * The days an overview's figures cover.
 */
final class OverviewPeriod extends ApiResource
{
    /**
     * Create a new overview period.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The first day, as midnight UTC. */
        public readonly ?DateTimeImmutable $from,
        /** The last day, today, as midnight UTC. */
        public readonly ?DateTimeImmutable $to,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview period from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            from: self::date($data, 'from'),
            to: self::date($data, 'to'),
        );
    }
}
