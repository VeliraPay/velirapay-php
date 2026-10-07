<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\ChargeStatus;

/**
 * A single payment in one coin, with the rate locked and a deposit address of its own.
 */
final class Charge extends ApiResource
{
    /**
     * Everything known about who pays, including the IP address and browser they paid from.
     */
    public readonly Customer $customer;

    /**
     * Create a new charge.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<Refund>  $refunds
     * @param  list<Transaction>  $transactions
     * @param  list<array{event: string, time: DateTimeImmutable|null}>  $timeline
     * @param  list<array{label: string, value: string}>  $customFields
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        array $attributes,
        /** The charge's public code, also the last segment of its checkout URL. */
        public readonly string $id,
        /** One of the ChargeStatus values: pending, underpaid, paid, expired or canceled. */
        public readonly string $status,
        /** The code of the payment link the charge was made through. */
        public readonly ?string $paymentLink,
        /** The code of the invoice the charge pays. */
        public readonly ?string $invoice,
        /** The merchant's description of the charge. */
        public readonly ?string $description,
        /** What the customer owes, in fiat. */
        public readonly string $fiatAmount,
        /** The fiat currency, such as "USD". */
        public readonly string $fiatCurrency,
        /** One of the Asset values: the coin and network paid with, such as "BTC" or "USDT". */
        public readonly string $asset,
        /** The exact amount of the coin to send. */
        public readonly string $assetAmount,
        /** What has been credited so far, or null before anything arrived. */
        public readonly ?string $receivedAmount,
        /** What is still missing. */
        public readonly string $remainingAmount,
        /** Whether the customer sent more than the overpayment threshold allows. */
        public readonly bool $overpaid,
        /** How much has been recorded as refunded. */
        public readonly string $refundedAmount,
        /** The refunds recorded against the charge; absent from webhooks. */
        public readonly array $refunds,
        /** Fiat per one unit of the coin, locked when the charge was created. */
        public readonly string $exchangeRate,
        /** The address the customer pays into. */
        public readonly string $depositAddress,
        /** The transfers seen into the deposit address. */
        public readonly array $transactions,
        /** The charge's history, oldest first; absent from webhooks and from charges embedded in events. */
        public readonly array $timeline,
        /** The customer's email address. */
        public readonly ?string $customerEmail,
        /** The customer's name. */
        public readonly ?string $customerName,
        /** The customer's answers to the payment link's questions. */
        public readonly array $customFields,
        /** The merchant's own key-value data. */
        public readonly array $metadata,
        /** The hosted checkout page to send the customer to; absent from webhooks. */
        public readonly ?string $checkoutUrl,
        /** The customer's receipt, once the charge is paid; absent from webhooks. */
        public readonly ?string $receiptUrl,
        /** When the locked rate runs out. */
        public readonly ?DateTimeImmutable $expiresAt,
        /** When the charge was paid. */
        public readonly ?DateTimeImmutable $paidAt,
        /** When the charge was created. */
        public readonly ?DateTimeImmutable $createdAt,
        ?Customer $customer = null,
    ) {
        parent::__construct($attributes);

        $this->customer = $customer ?? Customer::fromArray(['email' => $customerEmail, 'name' => $customerName]);
    }

    /**
     * Create a charge from the API or from a webhook payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id', self::string($data, 'code')),
            status: self::string($data, 'status'),
            paymentLink: self::reference($data, 'payment_link'),
            invoice: self::reference($data, 'invoice'),
            description: self::nullableString($data, 'description'),
            fiatAmount: self::string($data, 'fiat_amount', '0'),
            fiatCurrency: self::string($data, 'fiat_currency'),
            asset: self::string($data, 'asset'),
            assetAmount: self::string($data, 'asset_amount', '0'),
            receivedAmount: self::nullableString($data, 'received_amount'),
            remainingAmount: self::string($data, 'remaining_amount', '0'),
            overpaid: self::bool($data, 'overpaid'),
            refundedAmount: self::string($data, 'refunded_amount', '0'),
            refunds: self::objects($data, 'refunds', Refund::fromArray(...)),
            exchangeRate: self::string($data, 'exchange_rate', '0'),
            depositAddress: self::string($data, 'deposit_address'),
            transactions: self::objects($data, 'transactions', Transaction::fromArray(...)),
            timeline: array_map(static fn (array $entry): array => [
                'event' => self::string($entry, 'event'),
                'time' => self::date($entry, 'time'),
            ], self::records($data, 'timeline')),
            customerEmail: self::nullableString($data, 'customer_email'),
            customerName: self::nullableString($data, 'customer_name'),
            customer: Customer::fromArray(self::map($data, 'customer') + [
                'email' => self::nullableString($data, 'customer_email'),
                'name' => self::nullableString($data, 'customer_name'),
            ]),
            customFields: array_map(static fn (array $field): array => [
                'label' => self::string($field, 'label'),
                'value' => self::string($field, 'value'),
            ], self::records($data, 'custom_fields')),
            metadata: self::map($data, 'metadata'),
            checkoutUrl: self::nullableString($data, 'checkout_url'),
            receiptUrl: self::nullableString($data, 'receipt_url'),
            expiresAt: self::date($data, 'expires_at'),
            paidAt: self::date($data, 'paid_at'),
            createdAt: self::date($data, 'created_at'),
        );
    }

    /**
     * Determine whether the charge is waiting for its payment.
     */
    public function isPending(): bool
    {
        return $this->status === ChargeStatus::Pending->value;
    }

    /**
     * Determine whether less than the amount due has arrived.
     */
    public function isUnderpaid(): bool
    {
        return $this->status === ChargeStatus::Underpaid->value;
    }

    /**
     * Determine whether the charge is paid in full and confirmed.
     */
    public function isPaid(): bool
    {
        return $this->status === ChargeStatus::Paid->value;
    }

    /**
     * Determine whether the locked rate ran out before the charge was paid.
     */
    public function isExpired(): bool
    {
        return $this->status === ChargeStatus::Expired->value;
    }

    /**
     * Determine whether the charge was canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === ChargeStatus::Canceled->value;
    }

    /**
     * Determine whether the charge can still be paid.
     */
    public function isOpen(): bool
    {
        return $this->isPending() || $this->isUnderpaid();
    }
}
