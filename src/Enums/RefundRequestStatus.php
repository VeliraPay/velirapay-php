<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The states a refund request moves through, from the customer's address to the refund landing.
 */
enum RefundRequestStatus: string
{
    case AwaitingAddress = 'awaiting_address';
    case AwaitingTransfer = 'awaiting_transfer';
    case Confirming = 'confirming';
    case Refunded = 'refunded';
    case Canceled = 'canceled';
    case Expired = 'expired';
}
