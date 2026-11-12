<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use VeliraPay\Enums\Asset;

/**
 * A coin that can be paid in the API key's mode, with the lowest price a charge in it can have.
 */
final class SupportedAsset extends ApiResource
{
    /**
     * Create a new supported asset.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** What the API calls the coin, such as "BTC" or "USDT_BSC": pass it as asset when you create a charge. */
        public readonly string $id,
        /** The coin's name, such as "Tether USD". */
        public readonly string $name,
        /** The coin's symbol, such as "USDT". */
        public readonly string $ticker,
        /** The network the customer has to send it on. */
        public readonly string $network,
        /** The token's contract on that network, or null for a network's own coin. */
        public readonly ?string $contractAddress,
        /** The decimal places the coin has on its network. */
        public readonly int $decimals,
        /** How many confirmations a payment needs before it counts. */
        public readonly int $requiredConfirmations,
        /** About how long those confirmations take, in words. */
        public readonly string $confirmationWait,
        /** Whether the account takes it right now, which needs a payout address for that network. */
        public readonly bool $accepted,
        /** The Currency value exchangeRate and minimumPrice are in. */
        public readonly string $currency,
        /** What one unit of the coin is worth in that currency right now, or null while no rate is available. */
        public readonly ?string $exchangeRate,
        /** The least a charge in it can come to, in the coin itself, or null when it has no minimum of its own. */
        public readonly ?string $minimumAmount,
        /** The lowest price a charge in it can have right now, in that currency, or null while a coin with a minimum has no rate. */
        public readonly ?string $minimumPrice,
        /** What a charge in it asks the customer for on top of the price, in the coin, for the network; null for a coin without one. */
        public readonly ?string $networkSurcharge = null,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a supported asset from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            name: self::string($data, 'name'),
            ticker: self::string($data, 'ticker'),
            network: self::string($data, 'network'),
            contractAddress: self::nullableString($data, 'contract_address'),
            decimals: self::int($data, 'decimals'),
            requiredConfirmations: self::int($data, 'required_confirmations'),
            confirmationWait: self::string($data, 'confirmation_wait'),
            accepted: self::bool($data, 'accepted'),
            currency: self::string($data, 'currency'),
            exchangeRate: self::nullableString($data, 'exchange_rate'),
            minimumAmount: self::nullableString($data, 'minimum_amount'),
            minimumPrice: self::nullableString($data, 'minimum_price'),
            networkSurcharge: self::nullableString($data, 'network_surcharge'),
        );
    }

    /**
     * Get the Asset case for the coin, or null for one the API added after this version of the library.
     */
    public function asset(): ?Asset
    {
        return Asset::tryFrom($this->id);
    }
}
