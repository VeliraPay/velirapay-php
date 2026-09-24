<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The states an invoice moves through.
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
}
