<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * How much an account expects to take in a month, in US dollars.
 */
enum MonthlyVolume: string
{
    case Under1k = 'under_1k';
    case Under10k = '1k_10k';
    case Under50k = '10k_50k';
    case Under250k = '50k_250k';
    case Over250k = 'over_250k';
}
