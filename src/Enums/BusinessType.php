<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * What kind of legal entity runs an account.
 */
enum BusinessType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case NonProfit = 'non_profit';
}
