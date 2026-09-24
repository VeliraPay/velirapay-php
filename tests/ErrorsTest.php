<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\AuthenticationException;
use VeliraPay\Exceptions\ConflictException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Exceptions\InvalidRequestException;
use VeliraPay\Exceptions\NotFoundException;
use VeliraPay\Exceptions\PermissionException;
use VeliraPay\Exceptions\RateLimitException;
use VeliraPay\Exceptions\ServerException;
use VeliraPay\Exceptions\UnexpectedResponseException;
use VeliraPay\Exceptions\ValidationException;
use VeliraPay\Exceptions\VeliraPayException;

final class ErrorsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statuses(): iterable
    {
        yield 'bad request' => [400, InvalidRequestException::class];
        yield 'unauthenticated' => [401, AuthenticationException::class];
        yield 'forbidden' => [403, PermissionException::class];
        yield 'not found' => [404, NotFoundException::class];
        yield 'conflict' => [409, ConflictException::class];
        yield 'invalid' => [422, ValidationException::class];
        yield 'rate limited' => [429, RateLimitException::class];
        yield 'server error' => [500, ServerException::class];
        yield 'unavailable' => [503, ServerException::class];
    }

    /**
     * @param  class-string<ApiException>  $exception
     */
    #[DataProvider('statuses')]
    public function test_each_error_status_has_its_own_exception(int $status, string $exception): void
    {
        $this->http->json(['message' => 'Something went wrong.'], $status);

        try {
            $this->client()->charges->retrieve('k3v9x2m7q8wz');
            $this->fail('No exception was thrown.');
        } catch (ApiException $caught) {
            $this->assertInstanceOf($exception, $caught);
            $this->assertInstanceOf(VeliraPayException::class, $caught);
            $this->assertSame($status, $caught->status);
            $this->assertSame($status, $caught->getCode());
            $this->assertSame('Something went wrong.', $caught->getMessage());
            $this->assertSame(['message' => 'Something went wrong.'], $caught->body);
        }
    }

    public function test_an_error_without_a_json_message_still_explains_itself(): void
    {
        $this->http->queue(new Response(502, ['Content-Type' => 'text/html'], '<html>Bad Gateway</html>'));

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('The VeliraPay API answered with HTTP 502.');

        $this->client()->charges->retrieve('k3v9x2m7q8wz');
    }

    public function test_validation_errors_are_keyed_by_field(): void
    {
        $this->http->json([
            'message' => 'The asset field is required. (and 1 more error)',
            'errors' => [
                'asset' => ['The asset field is required.'],
                'items.0.quantity' => ['The items.0.quantity field must be at least 0.001.'],
            ],
        ], 422);

        try {
            $this->client()->charges->create(['asset' => '']);
            $this->fail('No exception was thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'asset' => ['The asset field is required.'],
                'items.0.quantity' => ['The items.0.quantity field must be at least 0.001.'],
            ], $exception->errors());
            $this->assertSame('The asset field is required.', $exception->firstError('asset'));
            $this->assertNull($exception->firstError('amount'));
        }
    }

    public function test_a_rate_limit_says_when_to_try_again(): void
    {
        $this->http->json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42']);

        try {
            $this->client()->charges->list();
            $this->fail('No exception was thrown.');
        } catch (RateLimitException $exception) {
            $this->assertSame(42, $exception->retryAfter());
            $this->assertSame('42', $exception->header('Retry-After'));
        }
    }

    public function test_a_successful_response_that_is_not_json_is_reported(): void
    {
        $this->http->queue(new Response(200, ['Content-Type' => 'text/html'], '<html>Maintenance</html>'));

        $this->expectException(UnexpectedResponseException::class);

        $this->client()->charges->retrieve('k3v9x2m7q8wz');
    }

    public function test_an_unreachable_api_is_reported(): void
    {
        $this->http->queue(new ConnectException('Could not resolve host', new Request('GET', 'https://api.velirapay.com/v1/charges')));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Could not reach the VeliraPay API: Could not resolve host');

        $this->client()->charges->list();
    }
}
