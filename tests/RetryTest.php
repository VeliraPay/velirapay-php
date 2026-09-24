<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Exceptions\RateLimitException;
use VeliraPay\Exceptions\ServerException;
use VeliraPay\Exceptions\ValidationException;
use VeliraPay\Http\HttpTransport;

final class RetryTest extends TestCase
{
    /**
     * The waits between attempts, in seconds.
     *
     * @var list<float>
     */
    private array $sleeps = [];

    public function test_outages_and_rate_limits_are_retried_with_the_same_idempotency_key(): void
    {
        $this->http
            ->json(['message' => 'Service Unavailable'], 503)
            ->json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '2'])
            ->json(['data' => self::fixture('charge')], 201);

        $response = $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC']);

        $this->assertSame(201, $response->status);
        $this->assertCount(3, $this->http->requests);

        $keys = array_unique(array_map(static fn ($request): string => $request->getHeaderLine('Idempotency-Key'), $this->http->requests));
        $this->assertCount(1, $keys);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $keys[0]);

        $this->assertCount(2, $this->sleeps);
        $this->assertGreaterThanOrEqual(0.375, $this->sleeps[0]);
        $this->assertLessThanOrEqual(0.625, $this->sleeps[0]);
        $this->assertSame(2.0, $this->sleeps[1]);
    }

    public function test_a_given_idempotency_key_is_kept(): void
    {
        $this->http->json(['message' => 'Bad Gateway'], 502)->json(['data' => self::fixture('charge')], 201);

        $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC'], 'order-1042');

        $this->assertSame('order-1042', $this->http->requests[0]->getHeaderLine('Idempotency-Key'));
        $this->assertSame('order-1042', $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function test_a_server_error_is_retried_for_reads_only(): void
    {
        $this->http
            ->json(['message' => 'Server Error'], 500)
            ->json(['data' => self::fixture('charge')])
            ->json(['message' => 'Server Error'], 500);

        $this->transport()->request('GET', '/v1/charges/k3v9x2m7q8wz');

        $this->assertCount(2, $this->http->requests);

        try {
            $this->transport()->request('POST', '/v1/charges/k3v9x2m7q8wz/cancel');
            $this->fail('No exception was thrown.');
        } catch (ServerException) {
            $this->assertCount(3, $this->http->requests);
        }
    }

    public function test_a_long_retry_after_is_reported_rather_than_waited(): void
    {
        $this->http->json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '60']);

        try {
            $this->transport()->request('GET', '/v1/charges');
            $this->fail('No exception was thrown.');
        } catch (RateLimitException $exception) {
            $this->assertSame(60, $exception->retryAfter());
            $this->assertCount(1, $this->http->requests);
            $this->assertSame([], $this->sleeps);
        }
    }

    public function test_network_errors_are_retried_until_the_limit(): void
    {
        $error = new ConnectException('Connection refused', new Request('GET', 'https://api.velirapay.com/v1/charges'));
        $this->http->queue($error, $error, $error);

        try {
            $this->transport(maxRetries: 2)->request('GET', '/v1/charges');
            $this->fail('No exception was thrown.');
        } catch (ConnectionException) {
            $this->assertCount(3, $this->http->requests);
            $this->assertCount(2, $this->sleeps);
        }
    }

    public function test_client_errors_are_not_retried(): void
    {
        $this->http->json(['message' => 'The given data was invalid.', 'errors' => []], 422);

        $this->expectException(ValidationException::class);

        try {
            $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC']);
        } finally {
            $this->assertCount(1, $this->http->requests);
        }
    }

    public function test_no_idempotency_key_is_added_when_retries_are_off(): void
    {
        $this->http->json(['data' => self::fixture('charge')], 201);

        $this->transport(maxRetries: 0)->request('POST', '/v1/charges', [], ['asset' => 'BTC']);

        $this->assertFalse($this->http->lastRequest()->hasHeader('Idempotency-Key'));
    }

    /**
     * Create a transport that records its waits instead of sleeping.
     */
    private function transport(int $maxRetries = 2): HttpTransport
    {
        $factory = new HttpFactory;

        return new HttpTransport(
            apiKey: 'vp_test_secret',
            client: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            baseUrl: 'https://api.velirapay.com',
            maxRetries: $maxRetries,
            userAgent: 'VeliraPay-PHP/test',
            sleep: function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }
}
