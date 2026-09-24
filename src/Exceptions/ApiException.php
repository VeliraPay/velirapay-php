<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

use RuntimeException;
use VeliraPay\Http\ApiResponse;

/**
 * Thrown when the API answers with an error.
 */
class ApiException extends RuntimeException implements VeliraPayException
{
    /**
     * The HTTP status the API answered with.
     */
    public readonly int $status;

    /**
     * The decoded JSON body of the response, or an empty array when it had none.
     *
     * @var array<string, mixed>
     */
    public readonly array $body;

    /**
     * Create a new exception.
     */
    final public function __construct(string $message, public readonly ApiResponse $response)
    {
        parent::__construct($message, $response->status);

        $this->status = $response->status;
        $this->body = $response->decode() ?? [];
    }

    /**
     * Create the exception that matches the response's status.
     */
    public static function fromResponse(ApiResponse $response): self
    {
        $decoded = $response->decode() ?? [];
        $message = is_string($decoded['message'] ?? null) && $decoded['message'] !== ''
            ? $decoded['message']
            : "The VeliraPay API answered with HTTP {$response->status}.";

        return match (true) {
            $response->status === 401 => new AuthenticationException($message, $response),
            $response->status === 403 => new PermissionException($message, $response),
            $response->status === 404 => new NotFoundException($message, $response),
            $response->status === 409 => new ConflictException($message, $response),
            $response->status === 422 => new ValidationException($message, $response),
            $response->status === 429 => new RateLimitException($message, $response),
            $response->status >= 500 => new ServerException($message, $response),
            default => new InvalidRequestException($message, $response),
        };
    }

    /**
     * Get the value of a response header.
     */
    public function header(string $name): ?string
    {
        return $this->response->header($name);
    }
}
