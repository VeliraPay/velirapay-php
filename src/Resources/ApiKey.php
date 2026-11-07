<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * An API key of the account. The key itself is only in the answer that creates it.
 */
final class ApiKey extends ApiResource
{
    /**
     * Create a new API key.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $permissions
     * @param  list<string>|null  $allowedIps
     */
    public function __construct(
        array $attributes,
        /** The key's id, which is not the key itself. */
        public readonly string $id,
        /** What the key is for. */
        public readonly string $name,
        /** The start of the key, such as "vp_live_a1b2", to tell keys apart. */
        public readonly string $prefix,
        /** The key itself, only in the answer that created it. */
        public readonly ?string $key,
        /** "live" or "test". */
        public readonly string $mode,
        /** The ApiKeyPermission values the key has, null for full access. */
        public readonly ?array $permissions,
        /** The addresses and ranges it may be used from, null for anywhere. */
        public readonly ?array $allowedIps,
        /** The name of the member who made it. */
        public readonly ?string $createdBy,
        /** When it last made a request. */
        public readonly ?DateTimeImmutable $lastUsedAt,
        /** When it was made. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an API key from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            name: self::string($data, 'name'),
            prefix: self::string($data, 'prefix'),
            key: self::nullableString($data, 'key'),
            mode: self::string($data, 'mode'),
            permissions: self::nullableStrings($data, 'permissions'),
            allowedIps: self::nullableStrings($data, 'allowed_ips'),
            createdBy: self::nullableString($data, 'created_by'),
            lastUsedAt: self::date($data, 'last_used_at'),
            createdAt: self::date($data, 'created_at'),
        );
    }

    /**
     * Determine whether the key has every permission that full access gives.
     */
    public function hasFullAccess(): bool
    {
        return $this->permissions === null;
    }
}
