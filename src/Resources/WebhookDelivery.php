<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * One event sent to a webhook endpoint, and how it went. What was sent and what came back are not returned.
 */
final class WebhookDelivery extends ApiResource
{
    /**
     * Create a new webhook delivery.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The delivery's id, the X-VeliraPay-Delivery header it was sent with. */
        public readonly string $id,
        /** The EventType value it carried. */
        public readonly string $event,
        /** The id of the event it carried, null for a test delivery. */
        public readonly ?string $eventId,
        /** Whether it was a test event, which describes no real payment. */
        public readonly bool $test,
        /** "pending", "succeeded" or "failed". */
        public readonly string $status,
        /** How many times it was sent. */
        public readonly int $attempts,
        /** The HTTP status the endpoint last answered with. */
        public readonly ?int $responseStatus,
        /** Why the last attempt failed. */
        public readonly ?string $error,
        /** The id of the charge it was about. */
        public readonly ?string $charge,
        /** The id of the invoice it was about. */
        public readonly ?string $invoice,
        /** When it was queued. */
        public readonly ?DateTimeImmutable $createdAt,
        /** When it was last sent. */
        public readonly ?DateTimeImmutable $lastAttemptedAt,
        /** When the endpoint accepted it. */
        public readonly ?DateTimeImmutable $deliveredAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a webhook delivery from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            event: self::string($data, 'event'),
            eventId: self::nullableString($data, 'event_id'),
            test: self::bool($data, 'test'),
            status: self::string($data, 'status'),
            attempts: self::int($data, 'attempts'),
            responseStatus: self::nullableInt($data, 'response_status'),
            error: self::nullableString($data, 'error'),
            charge: self::reference($data, 'charge'),
            invoice: self::reference($data, 'invoice'),
            createdAt: self::date($data, 'created_at'),
            lastAttemptedAt: self::date($data, 'last_attempted_at'),
            deliveredAt: self::date($data, 'delivered_at'),
        );
    }

    /**
     * Determine whether the endpoint accepted the delivery.
     */
    public function succeeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
