<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * A blockchain transfer into a charge's deposit address.
 */
final class Transaction extends ApiResource
{
    /**
     * Create a new transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The transaction id on its chain. */
        public readonly string $txid,
        /** The amount received, in the charge's coin. */
        public readonly string $amount,
        /** How many confirmations the transaction has. */
        public readonly int $confirmations,
        /** How many confirmations the coin needs before the transfer counts. */
        public readonly int $requiredConfirmations,
        /** Whether the transfer has been counted towards the charge. */
        public readonly bool $credited,
        /** A link to the transaction on a block explorer. */
        public readonly ?string $explorerUrl,
        /** When the transfer was first seen. */
        public readonly ?DateTimeImmutable $seenAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a transaction from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            txid: self::string($data, 'txid'),
            amount: self::string($data, 'amount', '0'),
            confirmations: self::int($data, 'confirmations'),
            requiredConfirmations: self::int($data, 'required_confirmations'),
            credited: self::bool($data, 'credited'),
            explorerUrl: self::nullableString($data, 'explorer_url'),
            seenAt: self::date($data, 'seen_at'),
        );
    }
}
