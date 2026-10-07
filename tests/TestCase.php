<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;
use VeliraPay\Tests\Support\MockHttpClient;
use VeliraPay\VeliraPayClient;

/**
 * The base for tests that talk to a mocked API.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * The HTTP client the API client sends its requests to.
     */
    protected MockHttpClient $http;

    /**
     * Set up the mocked HTTP client.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new MockHttpClient;
    }

    /**
     * Create an API client that sends its requests to the mocked HTTP client.
     */
    protected function client(string $apiKey = 'vp_test_secret', int $maxRetries = 0, ?string $appInfo = null, int $maxRetryAfter = 10): VeliraPayClient
    {
        $factory = new HttpFactory;

        return new VeliraPayClient(
            apiKey: $apiKey,
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
            maxRetries: $maxRetries,
            appInfo: $appInfo,
            maxRetryAfter: $maxRetryAfter,
        );
    }

    /**
     * Load a JSON fixture.
     *
     * @return array<string, mixed>
     */
    protected static function fixture(string $name): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Wrap objects in a list response.
     *
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    protected static function listResponse(array $data, int $currentPage = 1, int $lastPage = 1, int $perPage = 25, ?int $total = null): array
    {
        return [
            'data' => $data,
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => [
                'current_page' => $currentPage,
                'from' => $data === [] ? null : ($currentPage - 1) * $perPage + 1,
                'last_page' => $lastPage,
                'path' => 'https://api.velirapay.com/v1/charges',
                'per_page' => $perPage,
                'to' => $data === [] ? null : ($currentPage - 1) * $perPage + count($data),
                'total' => $total ?? count($data),
            ],
        ];
    }

    /**
     * Decode a request's JSON body.
     *
     * @return array<string, mixed>
     */
    protected static function body(RequestInterface $request): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Get a request's query parameters.
     *
     * @return array<string, mixed>
     */
    protected static function query(RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $query);

        /** @var array<string, mixed> $query */
        return $query;
    }
}
