<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * Whether an account is someone's personal account or an organization.
 */
enum AccountType: string
{
    case Personal = 'personal';
    case Organization = 'organization';
}
