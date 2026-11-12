<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\ResponseTransferException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConflictException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Exceptions\RateLimitException;
use VeliraPay\Exceptions\ServerException;
use VeliraPay\Exceptions\ValidationException;
use VeliraPay\Http\HttpTransport;
use VeliraPay\Services\ApiKeyService;

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

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statusesWithARetryAfter(): iterable
    {
        yield 'still being processed' => [409, ConflictException::class];
        yield 'unavailable' => [503, ServerException::class];
    }

    /**
     * @param  class-string<ApiException>  $exception
     */
    #[DataProvider('statusesWithARetryAfter')]
    public function test_any_retry_after_longer_than_the_longest_is_thrown_straight_away(int $status, string $exception): void
    {
        $this->http->json(['message' => 'Try again later.'], $status, ['Retry-After' => '11']);

        try {
            $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC']);
            $this->fail('No exception was thrown.');
        } catch (ApiException $caught) {
            $this->assertInstanceOf($exception, $caught);
            $this->assertSame(11, $caught->retryAfter());
            $this->assertCount(1, $this->http->requests);
            $this->assertSame([], $this->sleeps);
        }
    }

    public function test_the_longest_retry_after_that_is_waited_out_can_be_raised(): void
    {
        $this->http
            ->json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '60'])
            ->json(self::listResponse([]));

        $this->transport(maxRetryAfter: 60)->request('GET', '/v1/charges');

        $this->assertCount(2, $this->http->requests);
        $this->assertSame([60.0], $this->sleeps);
    }

    public function test_a_write_still_being_processed_under_the_same_key_is_waited_for(): void
    {
        $this->http
            ->json(['message' => 'A request with this Idempotency-Key is still being processed.'], 409, ['Retry-After' => '1'])
            ->json(['data' => self::fixture('charge')], 201, ['Idempotent-Replayed' => 'true']);

        $response = $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC'], 'order-1042');

        $this->assertSame(201, $response->status);
        $this->assertTrue($response->wasReplayed());
        $this->assertCount(2, $this->http->requests);
        $this->assertSame('order-1042', $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
        $this->assertSame([1.0], $this->sleeps);
    }

    public function test_a_conflict_without_a_retry_after_is_not_retried(): void
    {
        $this->http->json(['message' => 'This Idempotency-Key was already used for a different request.'], 409);

        try {
            $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC'], 'order-1042');
            $this->fail('No exception was thrown.');
        } catch (ConflictException $exception) {
            $this->assertNull($exception->retryAfter());
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

    public function test_a_network_error_on_the_last_attempt_carries_the_idempotency_key(): void
    {
        $error = new ConnectException('Connection refused', new Request('POST', 'https://api.velirapay.com/v1/charges'));
        $this->http->queue($error, $error, $error);

        try {
            $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC']);
            $this->fail('No exception was thrown.');
        } catch (ConnectionException $exception) {
            $this->assertSame('Could not reach the VeliraPay API: Connection refused', $exception->getMessage());
            $this->assertSame($this->http->requests[0]->getHeaderLine('Idempotency-Key'), $exception->idempotencyKey);
            $this->assertNotNull($exception->idempotencyKey);
        }
    }

    public function test_an_answer_cut_off_after_its_headers_is_retried_with_the_same_idempotency_key(): void
    {
        if (! class_exists(ResponseTransferException::class)) {
            $this->markTestSkipped('Guzzle 8 is not installed.');
        }

        $request = new Request('POST', 'https://api.velirapay.com/v1/charges');
        $this->http
            ->queue(new ResponseTransferException('cURL error 18: transfer closed with 512 bytes remaining to read', $request, new Response(201)))
            ->json(['data' => self::fixture('charge')], 201, ['Idempotent-Replayed' => 'true']);

        $response = $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC'], 'order-1042');

        $this->assertTrue($response->wasReplayed());
        $this->assertCount(2, $this->http->requests);
        $this->assertSame('order-1042', $this->http->requests[0]->getHeaderLine('Idempotency-Key'));
        $this->assertSame('order-1042', $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
        $this->assertCount(1, $this->sleeps);
        $this->assertGreaterThanOrEqual(0.375, $this->sleeps[0]);
        $this->assertLessThanOrEqual(0.625, $this->sleeps[0]);
    }

    public function test_an_answer_that_times_out_after_its_headers_is_retried_for_reads(): void
    {
        if (! class_exists(ResponseTimeoutException::class)) {
            $this->markTestSkipped('Guzzle 8 is not installed.');
        }

        $request = new Request('GET', 'https://api.velirapay.com/v1/charges');
        $this->http
            ->queue(new ResponseTimeoutException('cURL error 28: Operation timed out after 30001 milliseconds', $request, new Response(200)))
            ->json(self::listResponse([]));

        $response = $this->transport()->request('GET', '/v1/charges');

        $this->assertSame(200, $response->status);
        $this->assertCount(2, $this->http->requests);
        $this->assertFalse($this->http->requests[1]->hasHeader('Idempotency-Key'));
    }

    public function test_a_failure_to_send_is_retried_until_the_limit_and_then_carries_the_idempotency_key(): void
    {
        $error = new class('The response could not be read.') extends RuntimeException implements ClientExceptionInterface {};
        $this->http->queue($error, $error, $error);

        try {
            $this->transport(maxRetries: 2)->request('POST', '/v1/api-keys', [], ['name' => 'Shop server'], 'new-key');
            $this->fail('No exception was thrown.');
        } catch (ConnectionException $exception) {
            $this->assertSame('Could not send the request to the VeliraPay API: The response could not be read.', $exception->getMessage());
            $this->assertSame('new-key', $exception->idempotencyKey);
            $this->assertSame($error, $exception->getPrevious());
            $this->assertCount(3, $this->http->requests);
            $this->assertCount(2, $this->sleeps);
        }
    }

    public function test_a_lost_answer_that_carried_a_secret_is_reported_as_a_replayed_conflict(): void
    {
        $this->http
            ->queue(new class('The response could not be read.') extends RuntimeException implements ClientExceptionInterface {})
            ->json(['message' => 'A request with this Idempotency-Key already succeeded. Its answer carried a secret, which is shown once and not kept, so it cannot be replayed.'], 409, ['Idempotent-Replayed' => 'true']);

        try {
            (new ApiKeyService($this->transport()))->create(['name' => 'Shop server'], 'new-key');
            $this->fail('No exception was thrown.');
        } catch (ConflictException $exception) {
            $this->assertTrue($exception->wasReplayed());
            $this->assertStringStartsWith('A request with this Idempotency-Key already succeeded.', $exception->getMessage());
            $this->assertCount(2, $this->http->requests);
            $this->assertSame('new-key', $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
        }
    }

    public function test_a_failure_to_send_is_not_retried_when_retries_are_off(): void
    {
        $this->http->queue(new class('The response could not be read.') extends RuntimeException implements ClientExceptionInterface {});

        try {
            $this->transport(maxRetries: 0)->request('POST', '/v1/charges', [], ['asset' => 'BTC']);
            $this->fail('No exception was thrown.');
        } catch (ConnectionException $exception) {
            $this->assertNull($exception->idempotencyKey);
            $this->assertCount(1, $this->http->requests);
            $this->assertSame([], $this->sleeps);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function cloudflareOriginErrors(): iterable
    {
        yield 'unknown error' => [520];
        yield 'web server down' => [521];
        yield 'connection timed out' => [522];
        yield 'origin unreachable' => [523];
        yield 'a timeout occurred' => [524];
    }

    #[DataProvider('cloudflareOriginErrors')]
    public function test_cloudflares_origin_errors_are_retried_for_reads_and_keyed_writes(int $status): void
    {
        $this->http
            ->queue(new Response($status, ['Content-Type' => 'text/html'], '<html>Cloudflare</html>'))
            ->json(self::listResponse([]))
            ->queue(new Response($status, ['Content-Type' => 'text/html'], '<html>Cloudflare</html>'))
            ->json(['data' => self::fixture('charge')], 201);

        $this->transport()->request('GET', '/v1/charges');
        $this->transport()->request('POST', '/v1/charges', [], ['asset' => 'BTC'], 'order-1042');

        $this->assertCount(4, $this->http->requests);
        $this->assertSame('order-1042', $this->http->requests[2]->getHeaderLine('Idempotency-Key'));
        $this->assertSame('order-1042', $this->http->requests[3]->getHeaderLine('Idempotency-Key'));
        $this->assertCount(2, $this->sleeps);
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
    private function transport(int $maxRetries = 2, int $maxRetryAfter = 10): HttpTransport
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
            maxRetryAfter: $maxRetryAfter,
        );
    }
}
