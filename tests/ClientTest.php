<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use VeliraPay\Enums\ChargeStatus;
use VeliraPay\Enums\Mode;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Exceptions\RateLimitException;
use VeliraPay\VeliraPayClient;

final class ClientTest extends TestCase
{
    public function test_requests_are_authenticated_and_identify_the_library(): void
    {
        $this->http->json(self::fixture('account'));

        $this->client(appInfo: 'MyShop/2.1')->account->retrieve();

        $request = $this->http->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.velirapay.com/v1/account', (string) $request->getUri());
        $this->assertSame('Bearer vp_test_secret', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame('VeliraPay-PHP/'.VeliraPayClient::VERSION.' PHP/'.PHP_VERSION.' MyShop/2.1', $request->getHeaderLine('User-Agent'));
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
    }

    public function test_each_endpoint_group_is_also_reachable_through_a_method(): void
    {
        $client = $this->client();

        $this->assertSame($client->account, $client->account());
        $this->assertSame($client->charges, $client->charges());
        $this->assertSame($client->paymentLinks, $client->paymentLinks());
        $this->assertSame($client->invoices, $client->invoices());
        $this->assertSame($client->events, $client->events());
    }

    public function test_the_mode_is_read_from_the_key_prefix(): void
    {
        $this->assertSame(Mode::Live, $this->client('vp_live_secret')->mode());
        $this->assertTrue($this->client('vp_live_secret')->isLiveMode());
        $this->assertSame(Mode::Test, $this->client('vp_test_secret')->mode());
        $this->assertTrue($this->client('vp_test_secret')->isTestMode());
    }

    public function test_an_empty_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VeliraPayClient(' ');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysWithoutAMode(): iterable
    {
        yield 'another service\'s key' => ['sk_live_secret'];
        yield 'no mode' => ['vp_secret'];
        yield 'a prefix in capitals' => ['VP_LIVE_secret'];
    }

    #[DataProvider('keysWithoutAMode')]
    public function test_a_key_that_does_not_start_with_a_mode_is_rejected(string $apiKey): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('starts with "vp_live_" or "vp_test_"');

        $this->client($apiKey);
    }

    public function test_a_negative_number_of_retries_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client(maxRetries: -1);
    }

    public function test_a_negative_longest_retry_after_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client(maxRetryAfter: -1);
    }

    public function test_a_retry_after_longer_than_the_configured_longest_is_thrown_straight_away(): void
    {
        $this->http->json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '6']);

        try {
            $this->client(maxRetries: 2, maxRetryAfter: 5)->charges->list();
            $this->fail('No exception was thrown.');
        } catch (RateLimitException $exception) {
            $this->assertSame(6, $exception->retryAfter());
            $this->assertCount(1, $this->http->requests);
        }
    }

    public function test_an_http_client_is_found_when_none_is_given(): void
    {
        $client = new VeliraPayClient('vp_test_secret');

        $this->assertSame(Mode::Test, $client->mode());
    }

    public function test_requests_go_to_a_custom_base_url(): void
    {
        $factory = new HttpFactory;
        $client = new VeliraPayClient('vp_test_secret', $this->http, $factory, $factory, baseUrl: 'http://localhost:8000/', maxRetries: 0);
        $this->http->json(['data' => self::fixture('charge')]);

        $client->charges->retrieve('k3v9x2m7q8wz');

        $this->assertSame('http://localhost:8000/v1/charges/k3v9x2m7q8wz', (string) $this->http->lastRequest()->getUri());
    }

    public function test_raw_requests_send_params_as_the_query_or_the_body(): void
    {
        $client = $this->client();
        $this->http->json(self::listResponse([]))->json(['data' => self::fixture('charge')], 201);

        $client->request('GET', '/v1/charges', ['status' => ChargeStatus::Paid, 'per_page' => 5, 'payment_link' => null]);

        $this->assertSame('status=paid&per_page=5', $this->http->lastRequest()->getUri()->getQuery());

        $response = $client->request('POST', '/v1/charges', ['asset' => 'BTC', 'amount' => 25.0], 'order-7');

        $this->assertSame(201, $response->status);
        $this->assertSame('{"asset":"BTC","amount":25.0}', (string) $this->http->lastRequest()->getBody());
        $this->assertSame('order-7', $this->http->lastRequest()->getHeaderLine('Idempotency-Key'));
        $this->assertSame($response, $client->lastResponse());
    }

    public function test_replayed_responses_are_recognised(): void
    {
        $client = $this->client();
        $this->http->json(['data' => self::fixture('charge')], 201, ['Idempotent-Replayed' => 'true']);

        $client->charges->create(['asset' => 'BTC', 'amount' => '150.00', 'currency' => 'EUR'], 'order-1042');

        $this->assertTrue($client->lastResponse()?->wasReplayed());
    }
}
