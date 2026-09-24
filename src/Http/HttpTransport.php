<?php

declare(strict_types=1);

namespace VeliraPay\Http;

use BackedEnum;
use Closure;
use DateTimeInterface;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Exceptions\InvalidArgumentException;

/**
 * Sends requests to the VeliraPay API and turns its answers into responses or exceptions.
 */
final class HttpTransport
{
    /**
     * The statuses worth another attempt for any request.
     */
    private const RETRYABLE_STATUSES = [429, 502, 503, 504];

    /**
     * The longest Retry-After, in seconds, that is waited out rather than reported.
     */
    private const MAX_RETRY_AFTER = 10;

    /**
     * The response to the most recent request.
     */
    private ?ApiResponse $lastResponse = null;

    /**
     * Waits the given number of seconds between attempts.
     *
     * @var Closure(float): void
     */
    private readonly Closure $sleep;

    /**
     * Create a new transport.
     *
     * @param  (Closure(float): void)|null  $sleep
     */
    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $baseUrl,
        private readonly int $maxRetries,
        private readonly string $userAgent,
        ?Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * Send a request and return the successful response.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, ?string $idempotencyKey = null): ApiResponse
    {
        $method = strtoupper($method);

        // A retried write must not be carried out twice, so every attempt carries the same key.
        if ($method !== 'GET' && $idempotencyKey === null && $this->maxRetries > 0) {
            $idempotencyKey = self::uuid();
        }

        $attempt = 0;

        while (true) {
            try {
                $response = $this->send($method, $path, $query, $body, $idempotencyKey);
            } catch (NetworkExceptionInterface $exception) {
                if ($attempt >= $this->maxRetries) {
                    throw new ConnectionException('Could not reach the VeliraPay API: '.$exception->getMessage(), 0, $exception);
                }

                ($this->sleep)($this->backoff($attempt++));

                continue;
            } catch (ClientExceptionInterface $exception) {
                throw new ConnectionException('Could not send the request to the VeliraPay API: '.$exception->getMessage(), 0, $exception);
            }

            $this->lastResponse = $response;

            if ($response->isSuccessful()) {
                return $response;
            }

            $delay = $this->retryDelay($method, $response, $attempt);

            if ($delay === null) {
                throw ApiException::fromResponse($response);
            }

            ($this->sleep)($delay);
            $attempt++;
        }
    }

    /**
     * Get the response to the most recent request.
     */
    public function lastResponse(): ?ApiResponse
    {
        return $this->lastResponse;
    }

    /**
     * Send one attempt of a request.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     *
     * @throws ClientExceptionInterface
     */
    private function send(string $method, string $path, array $query, ?array $body, ?string $idempotencyKey): ApiResponse
    {
        $uri = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        $query = array_filter(self::normalize($query), static fn (mixed $value): bool => $value !== null);

        if ($query !== []) {
            $uri .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Authorization', 'Bearer '.$this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(self::encode($body)));
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        return ApiResponse::fromPsr($this->client->sendRequest($request));
    }

    /**
     * Get how long to wait before trying a failed request again, or null when it should not be retried.
     */
    private function retryDelay(string $method, ApiResponse $response, int $attempt): ?float
    {
        if ($attempt >= $this->maxRetries) {
            return null;
        }

        $retryable = in_array($response->status, self::RETRYABLE_STATUSES, true)
            || ($response->status === 500 && $method === 'GET');

        if (! $retryable) {
            return null;
        }

        $retryAfter = $response->retryAfter();

        if ($retryAfter === null) {
            return $this->backoff($attempt);
        }

        return $retryAfter <= self::MAX_RETRY_AFTER ? (float) $retryAfter : null;
    }

    /**
     * Get the wait before an attempt: half a second, doubling each time, with some jitter.
     */
    private function backoff(int $attempt): float
    {
        return min(8.0, 0.5 * (2 ** $attempt)) * (0.75 + random_int(0, 500) / 1000);
    }

    /**
     * Encode a request body as JSON.
     *
     * @param  array<string, mixed>  $body
     */
    private static function encode(array $body): string
    {
        try {
            return json_encode(self::normalize($body), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The request could not be encoded as JSON: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Replace enums and dates in request parameters with the values the API expects.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function normalize(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                is_array($value) => self::normalize($value),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Generate a random UUID for an idempotency key.
     */
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
