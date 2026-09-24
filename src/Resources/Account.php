<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\Mode;

/**
 * The account an API key belongs to: a personal account or an organization.
 */
final class Account extends ApiResource
{
    /**
     * Create a new account.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $acceptedAssets
     */
    public function __construct(
        array $attributes,
        /** The account's slug. */
        public readonly string $id,
        /** Either "personal" or "organization". */
        public readonly string $type,
        /** The account's name. */
        public readonly string $name,
        /** The name customers see. */
        public readonly string $displayName,
        /** The logo customers see. */
        public readonly ?string $logoUrl,
        /** The merchant's website. */
        public readonly ?string $websiteUrl,
        /** Where customers can reach the merchant. */
        public readonly ?string $supportEmail,
        /** The checkout's accent colour, as "#rrggbb". */
        public readonly ?string $brandColor,
        /** The currency new links and reports default to. */
        public readonly string $defaultCurrency,
        /** How far short of the amount due, in percent, still counts as paid. */
        public readonly string $underpaymentTolerance,
        /** How much surplus, in percent, is ignored before a charge is flagged as overpaid. */
        public readonly string $overpaymentThreshold,
        /** When the account was created. */
        public readonly ?DateTimeImmutable $createdAt,
        /** The mode of the API key that fetched the account: "live" or "test". */
        public readonly ?string $mode,
        /** The coins the account can be paid in, in that mode. */
        public readonly array $acceptedAssets,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an account from its API attributes and the response's meta.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            type: self::string($data, 'type'),
            name: self::string($data, 'name'),
            displayName: self::string($data, 'display_name', self::string($data, 'name')),
            logoUrl: self::nullableString($data, 'logo_url'),
            websiteUrl: self::nullableString($data, 'website_url'),
            supportEmail: self::nullableString($data, 'support_email'),
            brandColor: self::nullableString($data, 'brand_color'),
            defaultCurrency: self::string($data, 'default_currency'),
            underpaymentTolerance: self::string($data, 'underpayment_tolerance', '0'),
            overpaymentThreshold: self::string($data, 'overpayment_threshold', '0'),
            createdAt: self::date($data, 'created_at'),
            mode: self::nullableString($meta, 'mode'),
            acceptedAssets: self::strings($meta, 'accepted_assets'),
        );
    }

    /**
     * Determine whether this is an organization rather than a personal account.
     */
    public function isOrganization(): bool
    {
        return $this->type === 'organization';
    }

    /**
     * Determine whether the key that fetched the account works in test mode.
     */
    public function isTestMode(): bool
    {
        return $this->mode === Mode::Test->value;
    }
}
