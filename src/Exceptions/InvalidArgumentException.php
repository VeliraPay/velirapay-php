<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * Thrown when the library is given something it cannot use.
 */
class InvalidArgumentException extends \InvalidArgumentException implements VeliraPayException {}
