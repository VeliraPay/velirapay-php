<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * The API key is missing, revoked or unknown (401).
 */
class AuthenticationException extends ApiException {}
