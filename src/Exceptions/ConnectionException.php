<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

use RuntimeException;

/**
 * Thrown when the API could not be reached, after any retries.
 */
class ConnectionException extends RuntimeException implements VeliraPayException {}
