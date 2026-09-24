<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * How a payment link is priced: at a set amount, or at one the customer names.
 */
enum PricingType: string
{
    case Fixed = 'fixed';
    case Open = 'open';
}
