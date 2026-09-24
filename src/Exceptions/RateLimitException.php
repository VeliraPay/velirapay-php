<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * The API key made too many requests (429).
 */
class RateLimitException extends ApiException
{
    /**
     * Get how many seconds to wait before trying again.
     */
    public function retryAfter(): ?int
    {
        return $this->response->retryAfter();
    }
}
