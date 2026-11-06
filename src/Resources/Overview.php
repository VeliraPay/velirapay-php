<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * The dashboard's figures for the last 30 days. Everything else it carries, such as the funnel and what needs attention, is read with get().
 */
final class Overview extends ApiResource
{
    /**
     * Create a new overview.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The currency the amounts are in. */
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
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
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
        );
    }
}
