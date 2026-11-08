<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use DateTimeImmutable;

/**
 * One attempt to send a webhook delivery: the request it made and what the endpoint answered.
 */
final class WebhookDeliveryAttempt extends ApiResource
{
    /**
     * Create a new webhook delivery attempt.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>|null  $requestHeaders
     * @param  array<string, string>|null  $responseHeaders
     */
    public function __construct(
        array $attributes,
        /** Which attempt it was, counted from 1. */
        public readonly int $attempt,
        /** Where it was sent, null when nothing was sent. */
        public readonly ?string $url,
        /** The headers sent, the signatures cut out but the timestamp they signed kept; null when nothing was sent. */
        public readonly ?array $requestHeaders,
        /** The HTTP status the endpoint answered with, null when nothing answered. */
        public readonly ?int $responseStatus,
        /** The headers the endpoint answered with, a few dozen at most and never cookies. */
        public readonly ?array $responseHeaders,
        /** The first 2,000 characters of the endpoint's answer. */
        public readonly ?string $responseBody,
        /** Why the attempt failed, such as a timeout or a refused connection. */
        public readonly ?string $error,
        /** How long the endpoint took, in milliseconds. */
        public readonly ?int $durationMs,
        /** When the attempt was made. */
        public readonly ?DateTimeImmutable $createdAt,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a webhook delivery attempt from its API attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attributes: $data,
            attempt: self::int($data, 'attempt'),
            url: self::nullableString($data, 'url'),
            requestHeaders: self::headers($data, 'request_headers'),
            responseStatus: self::nullableInt($data, 'response_status'),
            responseHeaders: self::headers($data, 'response_headers'),
            responseBody: self::nullableString($data, 'response_body'),
            error: self::nullableString($data, 'error'),
            durationMs: self::nullableInt($data, 'duration_ms'),
            createdAt: self::date($data, 'created_at'),
        );
    }

    /**
     * Determine whether the endpoint answered the attempt with a 2xx status.
     */
    public function succeeded(): bool
    {
        return $this->responseStatus !== null && $this->responseStatus >= 200 && $this->responseStatus < 300;
    }

    /**
     * Read a map of header names to values, or null when none were kept.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private static function headers(array $data, string $key): ?array
    {
        if (! is_array($data[$key] ?? null)) {
            return null;
        }

        return array_filter(self::map($data, $key), is_string(...));
    }
}
