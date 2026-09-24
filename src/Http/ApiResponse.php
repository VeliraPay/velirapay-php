<?php

declare(strict_types=1);

namespace VeliraPay\Http;

use DateTimeImmutable;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use VeliraPay\Exceptions\UnexpectedResponseException;

/**
 * A response from the VeliraPay API.
 */
final class ApiResponse
{
    /**
     * Create a new response.
     *
     * @param  array<string, list<string>>  $headers  Header values keyed by lowercase name.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
        //
    }

    /**
     * Create a response from a PSR-7 response.
     */
    public static function fromPsr(ResponseInterface $response): self
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values($values);
        }

        return new self($response->getStatusCode(), $headers, (string) $response->getBody());
    }

    /**
     * Determine whether the request succeeded.
     */
    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * Get the first value of a header.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * Determine whether the API replayed the response to an earlier request with the same idempotency key.
     */
    public function wasReplayed(): bool
    {
        return strtolower((string) $this->header('Idempotent-Replayed')) === 'true';
    }

    /**
     * Get how many seconds the API asked to wait before trying again.
     */
    public function retryAfter(): ?int
    {
        $value = $this->header('Retry-After');

        if ($value === null) {
            return null;
        }

        if (ctype_digit(trim($value))) {
            return (int) trim($value);
        }

        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s T', trim($value));

        return $date === false ? null : max(0, $date->getTimestamp() - time());
    }

    /**
     * Get the body as decoded JSON.
     *
     * @return array<string, mixed>
     *
     * @throws UnexpectedResponseException
     */
    public function json(): array
    {
        $decoded = $this->decode();

        if ($decoded === null) {
            throw new UnexpectedResponseException('The VeliraPay API answered with a body that is not a JSON object.', $this);
        }

        return $decoded;
    }

    /**
     * Get the body as decoded JSON, or null when it is not a JSON object.
     *
     * @return array<string, mixed>|null
     */
    public function decode(): ?array
    {
        if (trim($this->body) === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
