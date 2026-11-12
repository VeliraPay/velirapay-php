<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * What waits on a person in the account: short and late payments to accept or refund, payouts with no address to go to, and endpoints that stopped answering.
 */
final class OverviewAttention extends ApiResource
{
    /**
     * Create a new overview attention.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<OverviewChargeAmount>  $underpaidCharges
     * @param  list<OverviewChargeAmount>  $latePayments
     * @param  list<OverviewWaitingPayouts>  $payoutsWaitingForAddress
     */
    public function __construct(
        array $attributes,
        /** The latest underpaid charges that received something, at most five. */
        public readonly array $underpaidCharges,
        /** The latest expired or canceled charges with a late payment waiting to be accepted, at most five. */
        public readonly array $latePayments,
        /** How many payouts wait for a payout address, per coin. */
        public readonly array $payoutsWaitingForAddress,
        /** How many active webhook endpoints had a delivery fail in the last 7 days. */
        public readonly int $failingWebhookEndpoints,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an overview attention from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            underpaidCharges: self::objects($data, 'underpaid_charges', OverviewChargeAmount::fromArray(...)),
            latePayments: self::objects($data, 'late_payments', OverviewChargeAmount::fromArray(...)),
            payoutsWaitingForAddress: self::objects($data, 'payouts_waiting_for_address', OverviewWaitingPayouts::fromArray(...)),
            failingWebhookEndpoints: self::int($data, 'failing_webhook_endpoints'),
        );
    }
}
