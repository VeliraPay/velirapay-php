<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The states a charge moves through.
 */
enum ChargeStatus: string
{
    case Pending = 'pending';
    case Underpaid = 'underpaid';
    case Paid = 'paid';
    case Expired = 'expired';
    case Canceled = 'canceled';
}
