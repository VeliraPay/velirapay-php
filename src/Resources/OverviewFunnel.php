<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * How the period's attempts to pay ended, each in one bucket: paid, waiting, abandoned or incomplete.
 */
final class OverviewFunnel extends ApiResource
{
    /**
     * Create a new overview funnel.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<OverviewFunnelAsset>  $assets
     */
    public function __construct(
        array $attributes,
        /** How many attempts to pay began, where a new quote or a switch of coin carries an attempt on. */
        public readonly int $started,
        /** How many were paid. */
        public readonly int $paid,
        /** How many are still pending or underpaid. */
        public readonly int $waiting,
        /** How many expired or were canceled before any transfer arrived. */
        public readonly int $abandoned,
        /** How many closed unpaid after a transfer arrived. */
        public readonly int $incomplete,
        /** The same per coin, most started first. */
        public readonly array $assets,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview funnel from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            started: self::int($data, 'started'),
            paid: self::int($data, 'paid'),
            waiting: self::int($data, 'waiting'),
            abandoned: self::int($data, 'abandoned'),
            incomplete: self::int($data, 'incomplete'),
            assets: self::objects($data, 'assets', OverviewFunnelAsset::fromArray(...)),
        );
    }
}
