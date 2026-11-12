<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The fiat currencies prices can be set in.
 */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case CHF = 'CHF';
    case TRY = 'TRY';
}
