<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * The object's state does not allow the action, or an idempotency key was reused for a different request or is held by one still being processed (409).
 */
class ConflictException extends ApiException
{
    /**
     * Determine whether the API answered from an earlier request with the same idempotency key: that request succeeded, but its answer carried an API key or a signing secret, which is shown once and not kept.
     */
    public function wasReplayed(): bool
    {
        return $this->response->wasReplayed();
    }
}
