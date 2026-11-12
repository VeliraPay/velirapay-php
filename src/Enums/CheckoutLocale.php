<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The languages the checkout can be shown in, as the checkout language of the payment settings.
 */
enum CheckoutLocale: string
{
    case English = 'en';
    case French = 'fr';
    case German = 'de';
    case Spanish = 'es';
    case Turkish = 'tr';
    case Portuguese = 'pt';
    case Russian = 'ru';
    case Arabic = 'ar';
}
