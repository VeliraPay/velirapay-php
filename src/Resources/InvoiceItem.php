<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * A line on an itemised invoice.
 */
final class InvoiceItem extends ApiResource
{
    /**
     * Create a new invoice item.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** What the line is for. */
        public readonly string $description,
        /** How many units the line bills. */
        public readonly string $quantity,
        /** The price of one unit, in the invoice's currency. */
        public readonly string $unitAmount,
        /** The quantity times the unit amount. */
        public readonly string $total,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an invoice item from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            description: self::string($data, 'description'),
            quantity: self::string($data, 'quantity', '1'),
            unitAmount: self::string($data, 'unit_amount', '0'),
            total: self::string($data, 'total', '0'),
        );
    }
}
