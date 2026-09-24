<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The events a charge or an invoice reports, through webhooks and the events API.
 */
enum EventType: string
{
    case ChargeCreated = 'charge.created';
    case ChargePaymentDetected = 'charge.payment_detected';
    case ChargePaid = 'charge.paid';
    case ChargeUnderpaid = 'charge.underpaid';
    case ChargeLatePayment = 'charge.late_payment';
    case ChargeRefunded = 'charge.refunded';
    case ChargeExpired = 'charge.expired';
    case ChargeCanceled = 'charge.canceled';
    case InvoiceCreated = 'invoice.created';
    case InvoiceSent = 'invoice.sent';
    case InvoiceViewed = 'invoice.viewed';
    case InvoicePaid = 'invoice.paid';
    case InvoiceVoided = 'invoice.voided';
}
