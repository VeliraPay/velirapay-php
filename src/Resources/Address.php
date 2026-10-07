<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * A postal address.
 */
final class Address extends ApiResource
{
    /**
     * Create a new address.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The street address. */
        public readonly ?string $line1,
        /** The apartment, suite or building. */
        public readonly ?string $line2,
        /** The city or town. */
        public readonly ?string $city,
        /** The postal code. */
        public readonly ?string $postalCode,
        /** The state, county or region. */
        public readonly ?string $state,
        /** The country, as an ISO 3166 two-letter code. */
        public readonly ?string $country,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an address from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            line1: self::nullableString($data, 'line1'),
            line2: self::nullableString($data, 'line2'),
            city: self::nullableString($data, 'city'),
            postalCode: self::nullableString($data, 'postal_code'),
            state: self::nullableString($data, 'state'),
            country: self::nullableString($data, 'country'),
        );
    }
}
