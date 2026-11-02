<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * What one coin adds up to across the account's payouts.
 */
final class PayoutSummary extends ApiResource
{
    /**
     * Create a new payout summary.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** One of the Asset values: the coin this row adds up. */
        public readonly string $asset,
        /** How many paid charges it covers. */
        public readonly int $payouts,
        /** What came in. */
        public readonly string $grossAmount,
        /** What was kept in fees. */
        public readonly string $feeAmount,
        /** What has reached the merchant. */
        public readonly string $paidOutAmount,
        /** What is on its way and waiting to confirm. */
        public readonly string $inFlightAmount,
        /** What is still owed and has not left yet. */
        public readonly string $owedAmount,
        /** What the chains charged to forward it. */
        public readonly string $networkFeeAmount,
        /** How many payouts wait for an address for this coin. */
        public readonly int $waitingForAddress,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a payout summary from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            asset: self::string($data, 'asset'),
            payouts: self::int($data, 'payouts'),
            grossAmount: self::string($data, 'gross_amount', '0'),
            feeAmount: self::string($data, 'fee_amount', '0'),
            paidOutAmount: self::string($data, 'paid_out_amount', '0'),
            inFlightAmount: self::string($data, 'in_flight_amount', '0'),
            owedAmount: self::string($data, 'owed_amount', '0'),
            networkFeeAmount: self::string($data, 'network_fee_amount', '0'),
            waitingForAddress: self::int($data, 'waiting_for_address'),
        );
    }
}
