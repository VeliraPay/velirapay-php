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
     * Who runs the account and what it sells; every field is null until it is filled in. The API only sends it to a key with the sensitive account:write permission: for any other key every field is null, and get('business') is null too.
     */
    public readonly AccountBusiness $business;

    /**
     * Create a new account.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $acceptedAssets
     * @param  array{appearance?: string, coin_order?: list<string>, default_coin?: string|null, success_url?: string|null, cancel_url?: string|null, success_message?: string|null, hide_powered_by?: bool}  $checkout
     * @param  array{number_prefix?: string|null, footer?: string|null, show_business_details?: bool}  $invoices
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
        /** The Asset values the account can be paid in right now, in that mode. */
        public readonly array $acceptedAssets,
        /** The phone number customers see next to the support email. */
        public readonly ?string $supportPhone = null,
        ?AccountBusiness $business = null,
        /** "free" or "pro". */
        public readonly string $plan = 'free',
        /** The share of each payment kept as the platform's fee, in percent. */
        public readonly string $feePercent = '0',
        /** When Pro ends, or null while it renews, has no end or the account is on the free plan. */
        public readonly ?DateTimeImmutable $proEndsAt = null,
        /** The logo customers see on a dark background. */
        public readonly ?string $logoDarkUrl = null,
        /** The merchant's terms and conditions. */
        public readonly ?string $termsUrl = null,
        /** The merchant's refund policy. */
        public readonly ?string $refundPolicyUrl = null,
        /** The checkout's appearance, coin order, default coin and defaults. */
        public readonly array $checkout = [],
        /** The invoice numbering, footer and business details settings. */
        public readonly array $invoices = [],
        /** The fallback checkout language, or null for English. */
        public readonly ?string $checkoutLocale = null,
        /** Whether automatic invoice reminders are enabled. */
        public readonly bool $invoiceReminders = false,
        /** Whether the owner allows API keys and AI apps to change payout addresses. */
        public readonly bool $apiPayoutAddressChanges = false,
    ) {
        parent::__construct($attributes);

        $this->business = $business ?? AccountBusiness::fromArray([]);
    }

    /**
     * Create an account from its API attributes and the response's meta.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        $checkout = self::map($data, 'checkout');
        $invoices = self::map($data, 'invoices');

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
            supportPhone: self::nullableString($data, 'support_phone'),
            business: AccountBusiness::fromArray(self::map($data, 'business')),
            plan: self::string(self::map($data, 'plan'), 'name', 'free'),
            feePercent: self::string(self::map($data, 'plan'), 'fee_percent', '0'),
            proEndsAt: self::date(self::map($data, 'plan'), 'pro_ends_at'),
            logoDarkUrl: self::nullableString($data, 'logo_dark_url'),
            termsUrl: self::nullableString($data, 'terms_url'),
            refundPolicyUrl: self::nullableString($data, 'refund_policy_url'),
            checkout: $checkout === [] ? [] : [
                'appearance' => self::string($checkout, 'appearance', 'auto'),
                'coin_order' => self::strings($checkout, 'coin_order'),
                'default_coin' => self::nullableString($checkout, 'default_coin'),
                'success_url' => self::nullableString($checkout, 'success_url'),
                'cancel_url' => self::nullableString($checkout, 'cancel_url'),
                'success_message' => self::nullableString($checkout, 'success_message'),
                'hide_powered_by' => self::bool($checkout, 'hide_powered_by'),
            ],
            invoices: $invoices === [] ? [] : [
                'number_prefix' => self::nullableString($invoices, 'number_prefix'),
                'footer' => self::nullableString($invoices, 'footer'),
                'show_business_details' => self::bool($invoices, 'show_business_details'),
            ],
            checkoutLocale: self::nullableString($data, 'checkout_locale'),
            invoiceReminders: self::bool($data, 'invoice_reminders'),
            apiPayoutAddressChanges: self::bool($data, 'api_payout_address_changes'),
        );
    }

    /**
     * Determine whether the account is on Pro, which keeps no fee.
     */
    public function isPro(): bool
    {
        return $this->plan === 'pro';
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
