<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;
use VeliraPay\Enums\InvoiceStatus;

/**
 * A bill sent to one customer, paid through a hosted page in the coin they choose.
 */
final class Invoice extends ApiResource
{
    /**
     * Create a new invoice.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<InvoiceItem>  $items
     * @param  list<array{id: string, status: string, asset: string}>  $charges
     */
    public function __construct(
        array $attributes,
        /** The invoice's public code, also the last segment of its hosted URL. */
        public readonly string $id,
        /** The invoice number shown to the customer. */
        public readonly string $number,
        /** One of the InvoiceStatus values: open, paid or void. */
        public readonly string $status,
        /** Whether the invoice is open past its due date; absent from webhooks. */
        public readonly ?bool $overdue,
        /** The customer's name. */
        public readonly string $customerName,
        /** The customer's email address. */
        public readonly string $customerEmail,
        /** A note shown on the invoice. */
        public readonly ?string $memo,
        /** The total due. */
        public readonly string $amount,
        /** The fiat currency, such as "USD". */
        public readonly string $currency,
        /** The lines of an itemised invoice. */
        public readonly array $items,
        /** The day the invoice is due. */
        public readonly ?DateTimeImmutable $dueAt,
        /** The page where the customer pays. */
        public readonly string $hostedUrl,
        /** The printable invoice; absent from webhooks. */
        public readonly ?string $documentUrl,
        /** The charges made to pay the invoice, one per coin the customer tried. */
        public readonly array $charges,
        /** When the invoice was emailed. */
        public readonly ?DateTimeImmutable $sentAt,
        /** When the customer first opened it. */
        public readonly ?DateTimeImmutable $viewedAt,
        /** When it was paid. */
        public readonly ?DateTimeImmutable $paidAt,
        /** When it was voided. */
        public readonly ?DateTimeImmutable $voidedAt,
        /** When it was created. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create an invoice from the API or from a webhook payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id', self::string($data, 'code')),
            number: self::string($data, 'number'),
            status: self::string($data, 'status'),
            overdue: self::nullableBool($data, 'overdue'),
            customerName: self::string($data, 'customer_name'),
            customerEmail: self::string($data, 'customer_email'),
            memo: self::nullableString($data, 'memo'),
            amount: self::string($data, 'amount', '0'),
            currency: self::string($data, 'currency'),
            items: self::objects($data, 'items', InvoiceItem::fromArray(...)),
            dueAt: self::date($data, 'due_at'),
            hostedUrl: self::string($data, 'hosted_url'),
            documentUrl: self::nullableString($data, 'document_url'),
            charges: array_map(static fn (array $charge): array => [
                'id' => self::string($charge, 'id', self::string($charge, 'code')),
                'status' => self::string($charge, 'status'),
                'asset' => self::string($charge, 'asset'),
            ], self::records($data, 'charges')),
            sentAt: self::date($data, 'sent_at'),
            viewedAt: self::date($data, 'viewed_at'),
            paidAt: self::date($data, 'paid_at'),
            voidedAt: self::date($data, 'voided_at'),
            createdAt: self::date($data, 'created_at'),
        );
    }

    /**
     * Determine whether the invoice can still be paid.
     */
    public function isOpen(): bool
    {
        return $this->status === InvoiceStatus::Open->value;
    }

    /**
     * Determine whether the invoice was paid.
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid->value;
    }

    /**
     * Determine whether the invoice was voided.
     */
    public function isVoid(): bool
    {
        return $this->status === InvoiceStatus::Void->value;
    }
}
