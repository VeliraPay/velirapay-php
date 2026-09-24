<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

use RuntimeException;

/**
 * Thrown when a webhook delivery's signature is missing, invalid or too old.
 */
class SignatureVerificationException extends RuntimeException implements VeliraPayException {}
