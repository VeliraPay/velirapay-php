<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * One thing done in the account, from its activity log.
 */
final class Activity extends ApiResource
{
    /**
     * Create a new activity.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        array $attributes,
        /** The entry's id. */
        public readonly string $id,
        /** What was done, one of the ActivityAction values, such as "api_key.created". */
        public readonly string $action,
        /** What was done, in words. */
        public readonly string $description,
        /** Who did it: a member's name or an API key's. */
        public readonly ?string $actor,
        /** What it was done to. */
        public readonly ?string $subject,
        /** The details kept with it. */
        public readonly array $properties,
        /** The address it came from. */
        public readonly ?string $ipAddress,
        /** "live" or "test", or null for what belongs to neither, such as a sign-in. */
        public readonly ?string $mode,
        /** When it was done. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an activity from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            action: self::string($data, 'action'),
            description: self::string($data, 'description'),
            actor: self::nullableString($data, 'actor'),
            subject: self::nullableString($data, 'subject'),
            properties: self::map($data, 'properties'),
            ipAddress: self::nullableString($data, 'ip_address'),
            mode: self::nullableString($data, 'mode'),
            createdAt: self::date($data, 'created_at'),
        );
    }
}
