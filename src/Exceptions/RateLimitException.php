<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * The API key made too many requests, or an invoice was emailed too often (429).
 */
class RateLimitException extends ApiException {}
