<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * A crypto payment for a month of Pro: where to pay it and what to send.
 */
final class ProPayment extends ApiResource
{
    /**
     * Create a new Pro payment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** How many months it pays for. */
        public readonly int $months,
        /** What it costs, in currency. */
        public readonly string $price,
        /** The currency of the price. */
        public readonly string $currency,
        /** The Asset value it is paid in. */
        public readonly string $asset,
        /** What to send, in the asset. */
        public readonly string $assetAmount,
        /** The page to pay it on. */
        public readonly string $checkoutUrl,
        /** When its rate stops holding. */
        public readonly ?DateTimeImmutable $expiresAt,
        /** Whether this request started it, rather than answering with the one already waiting. */
        public readonly bool $started,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a Pro payment from its API attributes and the response's meta.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function fromArray(array $data, array $meta = []): self
    {
        return new self(
            attributes: $data,
            months: self::int($data, 'months', 1),
            price: self::string($data, 'price'),
            currency: self::string($data, 'currency'),
            asset: self::string($data, 'asset'),
            assetAmount: self::string($data, 'asset_amount'),
            checkoutUrl: self::string($data, 'checkout_url'),
            expiresAt: self::date($data, 'expires_at'),
            started: self::bool($meta, 'started'),
        );
    }
}
