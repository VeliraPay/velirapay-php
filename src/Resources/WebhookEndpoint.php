<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * Where webhooks are delivered, and whether it can receive them. Its signing secret is only returned when it is added or rotated.
 */
final class WebhookEndpoint extends ApiResource
{
    /**
     * Create a new webhook endpoint.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $events
     */
    public function __construct(
        array $attributes,
        /** The endpoint's id. */
        public readonly string $id,
        /** Where deliveries go. */
        public readonly string $url,
        /** What the merchant noted about it. */
        public readonly ?string $description,
        /** The EventType values it subscribes to. */
        public readonly array $events,
        /** Whether it is switched on. */
        public readonly bool $active,
        /** Whether it has answered the verification challenge; it receives nothing until it has. */
        public readonly bool $verified,
        /** How many deliveries are kept for it, from the last 30 days. */
        public readonly int $deliveriesCount,
        /** How many of those failed. */
        public readonly int $failedDeliveriesCount,
        /** When it last answered the verification challenge. */
        public readonly ?DateTimeImmutable $verifiedAt,
        /** When it was added. */
        public readonly ?DateTimeImmutable $createdAt,
        /** The signing secret, only in the answers that add the endpoint or rotate it. */
        public readonly ?string $secret = null,
        /** Why the endpoint did not answer the verification challenge, in the answers that sent it one. */
        public readonly ?string $verificationError = null,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a webhook endpoint from its API attributes and the response's meta.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            url: self::string($data, 'url'),
            description: self::nullableString($data, 'description'),
            events: self::strings($data, 'events'),
            active: self::bool($data, 'active'),
            verified: self::bool($data, 'verified'),
            deliveriesCount: self::int($data, 'deliveries_count'),
            failedDeliveriesCount: self::int($data, 'failed_deliveries_count'),
            verifiedAt: self::date($data, 'verified_at'),
            createdAt: self::date($data, 'created_at'),
            secret: self::nullableString($data, 'secret'),
            verificationError: self::nullableString($meta, 'verification_error'),
        );
    }

    /**
     * Determine whether the endpoint receives deliveries right now.
     */
    public function isReceiving(): bool
    {
        return $this->active && $this->verified;
    }
}
