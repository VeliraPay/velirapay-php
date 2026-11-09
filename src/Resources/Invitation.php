<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * An invitation to the account nobody has accepted yet.
 */
final class Invitation extends ApiResource
{
    /**
     * Create a new invitation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The invitation's id, which is not the code in the email's link. */
        public readonly string $id,
        /** Where it was sent. */
        public readonly string $email,
        /** The role it gives once accepted: "admin", "finance", "support" or "viewer". */
        public readonly string $role,
        /** The name of the member who sent it. */
        public readonly ?string $invitedBy,
        /** Whether it can no longer be accepted. */
        public readonly bool $expired,
        /** When it stops working. */
        public readonly ?DateTimeImmutable $expiresAt,
        /** When it was sent. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an invitation from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            email: self::string($data, 'email'),
            role: self::string($data, 'role'),
            invitedBy: self::nullableString($data, 'invited_by'),
            expired: self::bool($data, 'expired'),
            expiresAt: self::date($data, 'expires_at'),
            createdAt: self::date($data, 'created_at'),
        );
    }
}
