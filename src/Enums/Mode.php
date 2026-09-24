<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The mode an API key, object or webhook belongs to.
 */
enum Mode: string
{
    case Live = 'live';
    case Test = 'test';
}
