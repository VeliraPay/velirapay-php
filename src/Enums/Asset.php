<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * A coin on one particular network, such as USDT on Ethereum or USDT_TRON on Tron.
 *
 * Not every coin can be paid in at any time: the account lists the ones it accepts right now.
 */
enum Asset: string
{
    case BTC = 'BTC';
    case LTC = 'LTC';
    case DOGE = 'DOGE';
    case BCH = 'BCH';
    case ETH = 'ETH';
    case XMR = 'XMR';
    case SOL = 'SOL';
    case BNB = 'BNB';
    case TRX = 'TRX';
    case USDT = 'USDT';
    case USDC = 'USDC';
    case USDT_TRON = 'USDT_TRON';
    case USDC_BASE = 'USDC_BASE';
    case USDC_POLYGON = 'USDC_POLYGON';
    case USDT_BSC = 'USDT_BSC';
    case USDC_BSC = 'USDC_BSC';
    case USDT_POLYGON = 'USDT_POLYGON';
    case USDT_ARBITRUM = 'USDT_ARBITRUM';
    case USDC_ARBITRUM = 'USDC_ARBITRUM';
}
