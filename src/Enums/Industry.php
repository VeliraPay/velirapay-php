<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * What an account sells, broadly.
 */
enum Industry: string
{
    case DigitalProducts = 'digital_products';
    case Software = 'software';
    case Hosting = 'hosting';
    case Gaming = 'gaming';
    case Retail = 'retail';
    case ProfessionalServices = 'professional_services';
    case Education = 'education';
    case Media = 'media';
    case Marketplace = 'marketplace';
    case Travel = 'travel';
    case Donations = 'donations';
    case Other = 'other';
}
