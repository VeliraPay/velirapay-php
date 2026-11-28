<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\RefundRequestStatus;

/**
 * A refund started for a charge: what it sets aside, where it goes and how far it has got. VeliraPay never sends it; you do, from your own wallet.
 */
final class RefundRequest extends ApiResource
{
    /**
     * Create a new refund request.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The refund request's id. */
        public readonly string $id,
        /** The id of the charge being refunded. */
        public readonly string $charge,
        /** One of the RefundRequestStatus values. */
        public readonly string $status,
        /** What the refund sets aside, in the charge's coin. */
        public readonly string $amount,
        /** Why the refund is made. */
        public readonly ?string $reason,
        /** Where the refund goes, once the customer or you gave it. */
        public readonly ?string $address,
        /** Who gave the address: customer or merchant. */
        public readonly ?string $addressGivenBy,
        /** The signed page the customer gives the address on, while the refund still takes one. */
        public readonly ?string $addressUrl,
        /** When the address URL stops working. */
        public readonly ?DateTimeImmutable $linkExpiresAt,
        /** When the link was last emailed to the customer. */
        public readonly ?DateTimeImmutable $emailedAt,
        /** The transaction you said carried the refund. */
        public readonly ?string $txid,
        /** What the transfer in detectedTxid carried. */
        public readonly ?string $detectedAmount,
        /** How many confirmations that transfer has. */
        public readonly ?int $confirmations,
        /** The refund it was recorded as, once it was. */
        public readonly ?Refund $refund,
        /** When the refund was started. */
        public readonly ?DateTimeImmutable $createdAt,
        /** When it was recorded. */
        public readonly ?DateTimeImmutable $completedAt,
        /** When it was canceled. */
        public readonly ?DateTimeImmutable $canceledAt,
        /** The transfer the chain shows at the address: the one with your hash, or, while txid is null, one that fits the refund and waits for you to give it to markRefundSent(). */
        public readonly ?string $detectedTxid = null,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a refund request from its API attributes.
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
            amount: self::string($data, 'amount', '0'),
            reason: self::nullableString($data, 'reason'),
            address: self::nullableString($data, 'address'),
            addressGivenBy: self::nullableString($data, 'address_given_by'),
            addressUrl: self::nullableString($data, 'address_url'),
            linkExpiresAt: self::date($data, 'link_expires_at'),
            emailedAt: self::date($data, 'emailed_at'),
            txid: self::nullableString($data, 'txid'),
            detectedAmount: self::nullableString($data, 'detected_amount'),
            confirmations: self::nullableInt($data, 'confirmations'),
            refund: self::object($data, 'refund', Refund::fromArray(...)),
            createdAt: self::date($data, 'created_at'),
            completedAt: self::date($data, 'completed_at'),
            canceledAt: self::date($data, 'canceled_at'),
            detectedTxid: self::nullableString($data, 'detected_txid'),
        );
    }

    /**
     * Determine whether the refund still sets money aside: it waits for the address, for your transfer or for the chain to confirm it.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [RefundRequestStatus::AwaitingAddress->value, RefundRequestStatus::AwaitingTransfer->value, RefundRequestStatus::Confirming->value], true);
    }

    /**
     * Determine whether the refund was recorded.
     */
    public function isRefunded(): bool
    {
        return $this->status === RefundRequestStatus::Refunded->value;
    }
}
