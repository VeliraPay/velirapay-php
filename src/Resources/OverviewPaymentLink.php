<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * One of the payment links that were paid most in the period.
 */
final class OverviewPaymentLink extends ApiResource
{
    /**
     * Create a new overview payment link.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The payment link's id. */
        public readonly ?string $id,
        /** The payment link's title. */
        public readonly ?string $title,
        /** How many of its charges were paid in the period. */
        public readonly int $paidCharges,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview payment link from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::nullableString($data, 'id'),
            title: self::nullableString($data, 'title'),
            paidCharges: self::int($data, 'paid_charges'),
        );
    }
}
