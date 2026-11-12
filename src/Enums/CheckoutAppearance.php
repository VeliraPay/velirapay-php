<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * How the checkout looks: following the customer's device, light or dark.
 */
enum CheckoutAppearance: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';
}
