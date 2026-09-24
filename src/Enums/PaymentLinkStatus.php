<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * Whether a payment link takes payments.
 */
enum PaymentLinkStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
