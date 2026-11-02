<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\PayoutStatus;

/**
 * What became of one paid charge: what came in, what was kept, what was sent and the transaction that carried it.
 */
final class Payout extends ApiResource
{
    /**
     * Create a new payout.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The id of the charge the payout came from, which is also its own. */
        public readonly string $id,
        /** The id of the charge the payout came from. */
        public readonly string $charge,
        /** One of the PayoutStatus values: pending, sending, sent, failed or manual. */
        public readonly string $status,
        /** One of the Asset values: the coin it was paid in and goes out in. */
        public readonly string $asset,
        /** What the customer paid, in the coin. */
        public readonly string $grossAmount,
        /** The rate of the platform's fee, in percent. */
        public readonly string $feePercent,
        /** What was kept, in the coin. */
        public readonly string $feeAmount,
        /** What the fee was worth in fiat, at the rate the charge was locked at. */
        public readonly ?string $feeFiatAmount,
        /** The fiat currency of feeFiatAmount and exchangeRate. */
        public readonly ?string $fiatCurrency,
        /** The rate the charge was locked at. */
        public readonly ?string $exchangeRate,
        /** What the merchant is owed, before the chain's own fee. */
        public readonly string $netAmount,
        /** What the chain charged, once it has been broadcast. */
        public readonly ?string $networkFee,
        /** What reached the merchant, once it has been broadcast. */
        public readonly ?string $sentAmount,
        /** Where it goes, or null while it waits for an address. */
        public readonly ?string $payoutAddress,
        /** The transaction that carried it. */
        public readonly ?string $payoutTxid,
        /** The transaction's page on a block explorer. */
        public readonly ?string $explorerUrl,
        /** How many times forwarding has been tried. */
        public readonly int $attempts,
        /** Why the last try did not work, or why it is held. */
        public readonly ?string $lastError,
        /** When it may go out next. */
        public readonly ?DateTimeImmutable $nextAttemptAt,
        /** When it was broadcast. */
        public readonly ?DateTimeImmutable $sentAt,
        /** When it confirmed. */
        public readonly ?DateTimeImmutable $confirmedAt,
        /** When the books were written. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a payout from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            charge: self::string($data, 'charge'),
            status: self::string($data, 'status'),
            asset: self::string($data, 'asset'),
            grossAmount: self::string($data, 'gross_amount', '0'),
            feePercent: self::string($data, 'fee_percent', '0'),
            feeAmount: self::string($data, 'fee_amount', '0'),
            feeFiatAmount: self::nullableString($data, 'fee_fiat_amount'),
            fiatCurrency: self::nullableString($data, 'fiat_currency'),
            exchangeRate: self::nullableString($data, 'exchange_rate'),
            netAmount: self::string($data, 'net_amount', '0'),
            networkFee: self::nullableString($data, 'network_fee'),
            sentAmount: self::nullableString($data, 'sent_amount'),
            payoutAddress: self::nullableString($data, 'payout_address'),
            payoutTxid: self::nullableString($data, 'payout_txid'),
            explorerUrl: self::nullableString($data, 'explorer_url'),
            attempts: self::int($data, 'attempts'),
            lastError: self::nullableString($data, 'last_error'),
            nextAttemptAt: self::date($data, 'next_attempt_at'),
            sentAt: self::date($data, 'sent_at'),
            confirmedAt: self::date($data, 'confirmed_at'),
            createdAt: self::date($data, 'created_at'),
        );
    }

    /**
     * Determine whether the payout has confirmed, so the money is in the merchant's wallet.
     */
    public function isSent(): bool
    {
        return $this->status === PayoutStatus::Sent->value;
    }

    /**
     * Determine whether the payout waits on an address or on a person.
     */
    public function isManual(): bool
    {
        return $this->status === PayoutStatus::Manual->value;
    }
}
