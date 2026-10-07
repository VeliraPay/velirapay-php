<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use VeliraPay\Enums\BusinessType;
use VeliraPay\Enums\Industry;
use VeliraPay\Enums\MonthlyVolume;

/**
 * Who runs an account and what it sells, as entered under Business details in its settings.
 */
final class AccountBusiness extends ApiResource
{
    /**
     * Create a new account business.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The kind of legal entity, or null when unset or a value this library does not know yet, which get('type') still returns. */
        public readonly ?BusinessType $type,
        /** The registered name of the business. */
        public readonly ?string $legalName,
        /** The company registration number. */
        public readonly ?string $registrationNumber,
        /** The tax or VAT number. */
        public readonly ?string $taxId,
        /** The business's address. */
        public readonly Address $address,
        /** What the account sells, or null when unset or a value this library does not know yet, which get('industry') still returns. */
        public readonly ?Industry $industry,
        /** What the account sells, in its own words. */
        public readonly ?string $productDescription,
        /** The expected monthly takings, or null when unset or a value this library does not know yet, which get('monthly_volume') still returns. */
        public readonly ?MonthlyVolume $monthlyVolume,
        /** The business's phone number, in international format. */
        public readonly ?string $phone,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an account business from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            type: BusinessType::tryFrom(self::string($data, 'type')),
            legalName: self::nullableString($data, 'legal_name'),
            registrationNumber: self::nullableString($data, 'registration_number'),
            taxId: self::nullableString($data, 'tax_id'),
            address: Address::fromArray(self::map($data, 'address')),
            industry: Industry::tryFrom(self::string($data, 'industry')),
            productDescription: self::nullableString($data, 'product_description'),
            monthlyVolume: MonthlyVolume::tryFrom(self::string($data, 'monthly_volume')),
            phone: self::nullableString($data, 'phone'),
        );
    }
}
