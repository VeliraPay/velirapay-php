<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * What is known about the customer paying a charge, including the signals that help spot fraud.
 */
final class Customer extends ApiResource
{
    /**
     * Create a new customer.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        array $attributes,
        /** The customer's email address. */
        public readonly ?string $email,
        /** The customer's name. */
        public readonly ?string $name,
        /** The IP address the customer paid from, recorded by the checkout or passed when the charge was created. */
        public readonly ?string $ipAddress,
        /** The user agent of the browser the customer paid from. */
        public readonly ?string $userAgent,
        /** Your own id for the customer. */
        public readonly ?string $reference,
        /** The customer's phone number. */
        public readonly ?string $phone,
        /** The customer's country, as an ISO 3166 two-letter code. */
        public readonly ?string $country,
        /** Anything else you passed about the customer. */
        public readonly array $metadata,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a customer from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            email: self::nullableString($data, 'email'),
            name: self::nullableString($data, 'name'),
            ipAddress: self::nullableString($data, 'ip_address'),
            userAgent: self::nullableString($data, 'user_agent'),
            reference: self::nullableString($data, 'reference'),
            phone: self::nullableString($data, 'phone'),
            country: self::nullableString($data, 'country'),
            metadata: self::map($data, 'metadata'),
        );
    }
}
