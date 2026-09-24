<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\PaymentLinkStatus;
use VeliraPay\Enums\PricingType;

/**
 * A shareable checkout page that creates a charge for each customer who pays through it.
 */
final class PaymentLink extends ApiResource
{
    /**
     * Create a new payment link.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $suggestedAmounts
     * @param  list<string>|null  $acceptedAssets
     * @param  list<array{label: string, required: bool}>  $customFields
     */
    public function __construct(
        array $attributes,
        /** The link's public code, also the last segment of its checkout URL. */
        public readonly string $id,
        /** The title shown on the checkout. */
        public readonly string $title,
        /** The description shown on the checkout. */
        public readonly ?string $description,
        /** One of the PricingType values: fixed or open. */
        public readonly string $pricingType,
        /** The price of a fixed-price link. */
        public readonly ?string $amount,
        /** Amounts offered as shortcuts on an open-price link. */
        public readonly array $suggestedAmounts,
        /** The least a customer may pay on an open-price link. */
        public readonly ?string $minAmount,
        /** The most a customer may pay on an open-price link. */
        public readonly ?string $maxAmount,
        /** The fiat currency the link is priced in. */
        public readonly string $currency,
        /** The coins the link takes, or null for every coin the account can receive. */
        public readonly ?array $acceptedAssets,
        /** One of the PaymentLinkStatus values: active or archived. */
        public readonly string $status,
        /** Where the customer is sent after paying. */
        public readonly ?string $successUrl,
        /** Where the customer is sent when they give up. */
        public readonly ?string $cancelUrl,
        /** Whether the checkout asks for the customer's name. */
        public readonly bool $collectName,
        /** Whether the checkout requires the customer's email address. */
        public readonly bool $requireEmail,
        /** The questions the checkout asks. */
        public readonly array $customFields,
        /** The message shown once the payment lands. */
        public readonly ?string $successMessage,
        /** When the link stops taking payments. */
        public readonly ?DateTimeImmutable $expiresAt,
        /** How many payments the link takes before it closes. */
        public readonly ?int $maxPayments,
        /** Whether the link takes payments right now. */
        public readonly bool $acceptingPayments,
        /** How many times the checkout was opened. */
        public readonly int $viewsCount,
        /** The checkout page to share. */
        public readonly string $checkoutUrl,
        /** When the link was created. */
        public readonly ?DateTimeImmutable $createdAt,
        /** When the link was last changed. */
        public readonly ?DateTimeImmutable $updatedAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a payment link from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id', self::string($data, 'code')),
            title: self::string($data, 'title'),
            description: self::nullableString($data, 'description'),
            pricingType: self::string($data, 'pricing_type'),
            amount: self::nullableString($data, 'amount'),
            suggestedAmounts: self::strings($data, 'suggested_amounts'),
            minAmount: self::nullableString($data, 'min_amount'),
            maxAmount: self::nullableString($data, 'max_amount'),
            currency: self::string($data, 'currency'),
            acceptedAssets: self::nullableStrings($data, 'accepted_assets'),
            status: self::string($data, 'status'),
            successUrl: self::nullableString($data, 'success_url'),
            cancelUrl: self::nullableString($data, 'cancel_url'),
            collectName: self::bool($data, 'collect_name'),
            requireEmail: self::bool($data, 'require_email'),
            customFields: array_map(static fn (array $field): array => [
                'label' => self::string($field, 'label'),
                'required' => self::bool($field, 'required'),
            ], self::records($data, 'custom_fields')),
            successMessage: self::nullableString($data, 'success_message'),
            expiresAt: self::date($data, 'expires_at'),
            maxPayments: self::nullableInt($data, 'max_payments'),
            acceptingPayments: self::bool($data, 'accepting_payments'),
            viewsCount: self::int($data, 'views_count'),
            checkoutUrl: self::string($data, 'checkout_url'),
            createdAt: self::date($data, 'created_at'),
            updatedAt: self::date($data, 'updated_at'),
        );
    }

    /**
     * Determine whether the link is active rather than archived.
     */
    public function isActive(): bool
    {
        return $this->status === PaymentLinkStatus::Active->value;
    }

    /**
     * Determine whether the link was archived.
     */
    public function isArchived(): bool
    {
        return $this->status === PaymentLinkStatus::Archived->value;
    }

    /**
     * Determine whether the link has a set price rather than one the customer names.
     */
    public function hasFixedAmount(): bool
    {
        return $this->pricingType === PricingType::Fixed->value;
    }
}
