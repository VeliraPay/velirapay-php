<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The plans an account can be on: free, which keeps a fee of every payment, or Pro, which keeps none.
 */
enum PlanName: string
{
    case Free = 'free';
    case Pro = 'pro';
}
