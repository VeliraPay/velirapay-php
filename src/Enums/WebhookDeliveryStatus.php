<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * Where a webhook delivery has got to.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
