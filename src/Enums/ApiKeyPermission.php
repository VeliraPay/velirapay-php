<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * What a restricted API key may do. Full access gives every permission but the sensitive ones, which have to be ticked.
 */
enum ApiKeyPermission: string
{
    case ChargesRead = 'charges:read';
    case ChargesWrite = 'charges:write';
    case ChargesAccept = 'charges:accept';
    case ChargesRefund = 'charges:refund';
    case InvoicesRead = 'invoices:read';
    case InvoicesWrite = 'invoices:write';
    case PaymentLinksRead = 'payment_links:read';
    case PaymentLinksWrite = 'payment_links:write';
    case CouponsRead = 'coupons:read';
    case CouponsWrite = 'coupons:write';
    case PayoutsRead = 'payouts:read';
    case EventsRead = 'events:read';
    case WebhookEndpointsRead = 'webhook_endpoints:read';
    case OverviewRead = 'overview:read';
    case PayoutAddressesRead = 'payout_addresses:read';
    case ActivityRead = 'activity:read';
    case AccountWrite = 'account:write';
    case PaymentSettingsWrite = 'payment_settings:write';
    case PayoutAddressesWrite = 'payout_addresses:write';
    case WebhookEndpointsWrite = 'webhook_endpoints:write';
    case ApiKeysRead = 'api_keys:read';
    case ApiKeysWrite = 'api_keys:write';
    case MembersRead = 'members:read';
    case MembersWrite = 'members:write';
    case BillingWrite = 'billing:write';
}
