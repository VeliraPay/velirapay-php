<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the API could not be reached, or its answer not read, after any retries.
 */
class ConnectionException extends RuntimeException implements VeliraPayException
{
    /**
     * Create a new exception.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        /** The Idempotency-Key the request was sent with, under which repeating it is safe; null for a read, or with retries off and no key given. */
        public readonly ?string $idempotencyKey = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
