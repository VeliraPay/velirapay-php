<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

/**
 * What was set aside from the merchant's share for the network costs of a payout whose exact cost is unknown when it is sent, and what became of it.
 */
final class NetworkReserve extends ApiResource
{
    /**
     * Create a new network reserve.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** What was first set aside from the merchant's share, in the coin. */
        public readonly string $reserved,
        /** What forwarding the payout cost, or null until it is known. */
        public readonly ?string $payoutCost,
        /** What was allowed for the later sweep of VeliraPay's fee to its cold wallet. */
        public readonly string $sweepBudget,
        /** What that sweep cost, or null until it is known. */
        public readonly ?string $sweepCost,
        /** What remains of the sweep's budget. */
        public readonly string $sweepAvailable,
        /** What is still held for the merchant, counting every cost not yet known at its most. */
        public readonly string $held,
        /** What was left unused once every cost was known, still owed to the merchant and recorded rather than sent back; null while a cost is open. */
        public readonly ?string $residual,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a network reserve from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            reserved: self::string($data, 'reserved', '0'),
            payoutCost: self::nullableString($data, 'payout_cost'),
            sweepBudget: self::string($data, 'sweep_budget', '0'),
            sweepCost: self::nullableString($data, 'sweep_cost'),
            sweepAvailable: self::string($data, 'sweep_available', '0'),
            held: self::string($data, 'held', '0'),
            residual: self::nullableString($data, 'residual'),
        );
    }
}
