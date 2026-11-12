<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * The dashboard's figures for the last 30 days, and what needs attention now.
 */
final class Overview extends ApiResource
{
    /**
     * The days the figures cover.
     */
    public readonly OverviewPeriod $period;

    /**
     * How the period's attempts to pay ended.
     */
    public readonly OverviewFunnel $funnel;

    /**
     * What waits on a person in the account now.
     */
    public readonly OverviewAttention $attention;

    /**
     * Which of the steps of setting up the account are done.
     */
    public readonly OverviewSetup $setup;

    /**
     * Create a new overview.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<OverviewAsset>  $assets
     * @param  list<OverviewPaymentLink>  $topPaymentLinks
     * @param  list<OverviewDay>  $daily
     */
    public function __construct(
        array $attributes,
        /** The Currency value the amounts are in. */
        public readonly string $currency,
        /** What was paid in the period. */
        public readonly string $volume,
        /** How many charges were paid. */
        public readonly int $paidCharges,
        /** How many charges are waiting to be paid. */
        public readonly int $pendingCharges,
        /** How many payment links take payments. */
        public readonly int $activePaymentLinks,
        /** How many invoices are open. */
        public readonly int $openInvoices,
        /** How many open invoices are past due. */
        public readonly int $overdueInvoices,
        /** How the volume changed against the 30 days before, in percent; null when nothing was paid then. */
        public readonly ?float $volumeChangePercent = null,
        /** What the payouts confirmed in the period came to, at their charges' locked rates. */
        public readonly string $sentVolume = '0',
        /** Of the period's charges that were paid, expired or canceled, the share paid, in percent; null when there were none. */
        public readonly ?float $conversionRatePercent = null,
        ?OverviewPeriod $period = null,
        ?OverviewFunnel $funnel = null,
        ?OverviewAttention $attention = null,
        /** The share of the period's paid charges each coin took, most first. */
        public readonly array $assets = [],
        /** The payment links paid most in the period, at most four. */
        public readonly array $topPaymentLinks = [],
        ?OverviewSetup $setup = null,
        /** Every day of the period, oldest first. */
        public readonly array $daily = [],
        /** The mode of the API key that fetched the overview: "live" or "test". */
        public readonly ?string $mode = null,
    ) {
        parent::__construct($attributes);

        $this->period = $period ?? OverviewPeriod::fromArray([]);
        $this->funnel = $funnel ?? OverviewFunnel::fromArray([]);
        $this->attention = $attention ?? OverviewAttention::fromArray([]);
        $this->setup = $setup ?? OverviewSetup::fromArray([]);
    }

    /**
     * Create an overview from its API attributes and the response's meta.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        return new self(
            attributes: $data,
            currency: self::string($data, 'currency'),
            volume: self::string($data, 'volume', '0'),
            paidCharges: self::int($data, 'paid_charges'),
            pendingCharges: self::int($data, 'pending_charges'),
            activePaymentLinks: self::int($data, 'active_payment_links'),
            openInvoices: self::int($data, 'open_invoices'),
            overdueInvoices: self::int($data, 'overdue_invoices'),
            volumeChangePercent: self::nullableFloat($data, 'volume_change_percent'),
            sentVolume: self::string($data, 'sent_volume', '0'),
            conversionRatePercent: self::nullableFloat($data, 'conversion_rate_percent'),
            period: OverviewPeriod::fromArray(self::map($data, 'period')),
            funnel: OverviewFunnel::fromArray(self::map($data, 'funnel')),
            attention: OverviewAttention::fromArray(self::map($data, 'attention')),
            assets: self::objects($data, 'assets', OverviewAsset::fromArray(...)),
            topPaymentLinks: self::objects($data, 'top_payment_links', OverviewPaymentLink::fromArray(...)),
            setup: OverviewSetup::fromArray(self::map($data, 'setup')),
            daily: self::objects($data, 'daily', OverviewDay::fromArray(...)),
            mode: self::nullableString($meta, 'mode'),
        );
    }
}
