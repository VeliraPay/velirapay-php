<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * Where a payout has got to.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Manual = 'manual';
}
