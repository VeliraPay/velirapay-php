<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * A person in the account, with their role.
 */
final class Member extends ApiResource
{
    /**
     * Create a new member.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The member's id. */
        public readonly string $id,
        /** Their name. */
        public readonly string $name,
        /** The address they sign in with. */
        public readonly string $email,
        /** "owner", "admin", "finance", "support" or "viewer". */
        public readonly string $role,
        /** When they joined. */
        public readonly ?DateTimeImmutable $joinedAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a member from their API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            name: self::string($data, 'name'),
            email: self::string($data, 'email'),
            role: self::string($data, 'role'),
            joinedAt: self::date($data, 'joined_at'),
        );
    }
}
