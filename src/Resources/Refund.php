<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * A refund recorded against a charge. The transfer itself is sent by the merchant.
 */
final class Refund extends ApiResource
{
    /**
     * Create a new refund.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The amount sent back, in the charge's coin. */
        public readonly string $amount,
        /** The transaction that carried the refund, when it was recorded. */
        public readonly ?string $txid,
        /** Why the refund was made. */
        public readonly ?string $reason,
        /** A link to the refund transaction on a block explorer. */
        public readonly ?string $explorerUrl,
        /** When the refund was recorded. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a refund from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            amount: self::string($data, 'amount', '0'),
            txid: self::nullableString($data, 'txid'),
            reason: self::nullableString($data, 'reason'),
            explorerUrl: self::nullableString($data, 'explorer_url'),
            createdAt: self::date($data, 'created_at'),
        );
    }
}
