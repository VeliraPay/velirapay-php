<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * The object's state does not allow the action, or an idempotency key was reused for a different request (409).
 */
class ConflictException extends ApiException {}
