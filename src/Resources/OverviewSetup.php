<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * Which of the steps of setting up the account, in the API key's mode, are done.
 */
final class OverviewSetup extends ApiResource
{
    /**
     * Create a new overview setup.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** Whether a payout address is saved. */
        public readonly bool $payoutAddress,
        /** Whether a payment link exists. */
        public readonly bool $paymentLink,
        /** Whether a charge was ever paid. */
        public readonly bool $paidCharge,
        /** Whether a webhook endpoint exists. */
        public readonly bool $webhookEndpoint,
        /** Whether the public profile has a logo, a display name or a website. */
        public readonly bool $publicProfile,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview setup from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            payoutAddress: self::bool($data, 'payout_address'),
            paymentLink: self::bool($data, 'payment_link'),
            paidCharge: self::bool($data, 'paid_charge'),
            webhookEndpoint: self::bool($data, 'webhook_endpoint'),
            publicProfile: self::bool($data, 'public_profile'),
        );
    }
}
