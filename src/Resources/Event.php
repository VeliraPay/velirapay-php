<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\EventType;

/**
 * Something that happened to a charge or an invoice, as the events API lists it.
 */
final class Event extends ApiResource
{
    /**
     * Create a new event.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        array $attributes,
        /** The event's id, as a webhook's eventId refers to it. */
        public readonly string $id,
        /** One of the EventType values, such as "charge.paid". */
        public readonly string $type,
        /** What the event recorded, such as the amount and transaction of a detected payment. */
        public readonly array $details,
        /** When it happened. */
        public readonly ?DateTimeImmutable $createdAt,
        /** The charge the event is about. */
        public readonly ?Charge $charge,
        /** The invoice the event is about. */
        public readonly ?Invoice $invoice,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an event from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            type: self::string($data, 'type'),
            details: self::map($data, 'details'),
            createdAt: self::date($data, 'created_at'),
            charge: self::object($data, 'charge', Charge::fromArray(...)),
            invoice: self::object($data, 'invoice', Invoice::fromArray(...)),
        );
    }

    /**
     * Determine whether the event is of any of the given types.
     */
    public function is(EventType|string ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->type === ($type instanceof EventType ? $type->value : $type)) {
                return true;
            }
        }

        return false;
    }
}
