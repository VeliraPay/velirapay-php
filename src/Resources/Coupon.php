<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * A discount code customers enter at checkout, or that is applied for them through a payment link's URL or the API.
 */
final class Coupon extends ApiResource
{
    /**
     * Create a new coupon.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $paymentLinks
     */
    public function __construct(
        array $attributes,
        /** The coupon's id, which is its code. */
        public readonly string $id,
        /** The code customers type, in capitals; it is matched in any case. */
        public readonly string $code,
        /** The share taken off, such as "10.00", or null for an amount off. */
        public readonly ?string $percentOff,
        /** The amount taken off, or null for a percentage. */
        public readonly ?string $amountOff,
        /** The only fiat currency the coupon works in, or null for a percentage that works in every one. */
        public readonly ?string $currency,
        /** The least the price before the discount must be. */
        public readonly ?string $minAmount,
        /** How many payments may use the coupon, or null for no limit. */
        public readonly ?int $maxRedemptions,
        /** How many paid charges used the coupon. */
        public readonly int $timesRedeemed,
        /** Whether each customer email can use the coupon only once. */
        public readonly bool $oncePerCustomer,
        /** The codes of the payment links the coupon is limited to; empty for every link, and charges without one. */
        public readonly array $paymentLinks,
        /** Whether customers can use the coupon, rather than it being paused. */
        public readonly bool $active,
        /** When the coupon stops working. */
        public readonly ?DateTimeImmutable $expiresAt,
        /** When the coupon was created. */
        public readonly ?DateTimeImmutable $createdAt,
        /** When the coupon was last changed. */
        public readonly ?DateTimeImmutable $updatedAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a coupon from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $code = self::string($data, 'code', self::string($data, 'id'));

        return new self(
            attributes: $data,
            id: self::string($data, 'id', $code),
            code: $code,
            percentOff: self::nullableString($data, 'percent_off'),
            amountOff: self::nullableString($data, 'amount_off'),
            currency: self::nullableString($data, 'currency'),
            minAmount: self::nullableString($data, 'min_amount'),
            maxRedemptions: self::nullableInt($data, 'max_redemptions'),
            timesRedeemed: self::int($data, 'times_redeemed'),
            oncePerCustomer: self::bool($data, 'once_per_customer'),
            paymentLinks: self::strings($data, 'payment_links'),
            active: self::bool($data, 'active'),
            expiresAt: self::date($data, 'expires_at'),
            createdAt: self::date($data, 'created_at'),
            updatedAt: self::date($data, 'updated_at'),
        );
    }

    /**
     * Determine whether the coupon takes a percentage off rather than a fixed amount.
     */
    public function isPercentage(): bool
    {
        return $this->percentOff !== null;
    }
}
