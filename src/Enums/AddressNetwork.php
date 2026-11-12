<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The network families payouts go to an address of, as the paths of the payout address endpoints name them.
 */
enum AddressNetwork: string
{
    case Bitcoin = 'bitcoin';
    case Litecoin = 'litecoin';
    case Dogecoin = 'dogecoin';
    case BitcoinCash = 'bitcoincash';
    case Evm = 'evm';
    case Bsc = 'bsc';
    case Base = 'base';
    case Arbitrum = 'arbitrum';
    case Polygon = 'polygon';
    case Tron = 'tron';
    case Monero = 'monero';
    case Solana = 'solana';
    case Lightning = 'lightning';
}
