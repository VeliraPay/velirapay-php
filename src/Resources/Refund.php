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
        /** The credit note's number, such as CN-0001, counted per account and mode. */
        public readonly ?string $number = null,
        /** What the refund is worth in the charge's fiat currency, at the rate the charge was locked at. */
        public readonly ?string $fiatAmount = null,
        /** The printable credit note, which the customer can open too. */
        public readonly ?string $creditNoteUrl = null,
        /** When the chain showed the transfer landing at the customer's address, or null when it was recorded on your word. */
        public readonly ?DateTimeImmutable $confirmedOnChainAt = null,
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
            number: self::nullableString($data, 'number'),
            fiatAmount: self::nullableString($data, 'fiat_amount'),
            creditNoteUrl: self::nullableString($data, 'credit_note_url'),
            confirmedOnChainAt: self::date($data, 'confirmed_on_chain_at'),
        );
    }
}
