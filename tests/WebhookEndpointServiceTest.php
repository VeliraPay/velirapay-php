<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;

final class WebhookEndpointServiceTest extends TestCase
{
    public function test_endpoints_are_listed_with_whether_they_can_receive(): void
    {
        $this->http->json([
            'data' => [
                [
                    'id' => '12', 'object' => 'webhook_endpoint', 'url' => 'https://example.com/hooks', 'description' => 'Orders', 'events' => ['charge.paid'],
                    'active' => true, 'verified' => true, 'deliveries_count' => 2, 'failed_deliveries_count' => 1,
                    'verified_at' => '2026-10-20T10:00:00.000000Z', 'created_at' => '2026-10-20T09:00:00.000000Z',
                ],
                [
                    'id' => '13', 'object' => 'webhook_endpoint', 'url' => 'https://example.com/new', 'description' => null, 'events' => [],
                    'active' => true, 'verified' => false, 'deliveries_count' => 0, 'failed_deliveries_count' => 0,
                    'verified_at' => null, 'created_at' => '2026-10-21T09:00:00.000000Z',
                ],
            ],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 2],
        ]);

        [$verified, $unverified] = $this->client()->webhookEndpoints->list()->data;

        $this->assertSame('/v1/webhook-endpoints', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('12', $verified->id);
        $this->assertSame('https://example.com/hooks', $verified->url);
        $this->assertSame('Orders', $verified->description);
        $this->assertSame(['charge.paid'], $verified->events);
        $this->assertTrue($verified->isReceiving());
        $this->assertSame(2, $verified->deliveriesCount);
        $this->assertSame(1, $verified->failedDeliveriesCount);
        $this->assertEquals(new DateTimeImmutable('2026-10-20T10:00:00Z'), $verified->verifiedAt);

        $this->assertFalse($unverified->isReceiving());
        $this->assertNull($unverified->verifiedAt);
    }

    public function test_an_endpoint_is_added_with_its_secret_and_whether_it_answered(): void
    {
        $this->http->json(['data' => [...self::endpoint(), 'verified' => false, 'secret' => 'whsec_abc'], 'meta' => ['verification_error' => 'The endpoint answered the challenge with HTTP 404.']], 201);

        $endpoint = $this->client()->webhookEndpoints->create(['url' => 'https://example.com/hooks', 'events' => ['charge.paid']], 'add-hook');

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/webhook-endpoints', $request->getUri()->getPath());
        $this->assertSame('add-hook', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('whsec_abc', $endpoint->secret);
        $this->assertSame('The endpoint answered the challenge with HTTP 404.', $endpoint->verificationError);
    }

    public function test_an_endpoint_is_changed_verified_tested_rotated_and_deleted(): void
    {
        $this->http->json(['data' => [...self::endpoint(), 'active' => false], 'meta' => ['verification_error' => null]]);

        $this->assertFalse($this->client()->webhookEndpoints->update('12', ['active' => false])->active);
        $this->assertSame('PATCH', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/webhook-endpoints/12', $this->http->lastRequest()->getUri()->getPath());

        $this->http->json(['data' => self::endpoint()]);

        $this->assertTrue($this->client()->webhookEndpoints->verify('12')->verified);
        $this->assertSame('/v1/webhook-endpoints/12/verify', $this->http->lastRequest()->getUri()->getPath());

        $this->http->json(['data' => self::delivery()], 201);

        $this->assertTrue($this->client()->webhookEndpoints->test('12')->test);
        $this->assertSame('/v1/webhook-endpoints/12/test', $this->http->lastRequest()->getUri()->getPath());

        $this->http->json(['data' => [...self::endpoint(), 'secret' => 'whsec_new'], 'meta' => ['previous_secret_expires_at' => '2026-11-07T09:00:00.000000Z']]);

        $rotated = $this->client()->webhookEndpoints->rotateSecret('12');

        $this->assertSame('whsec_new', $rotated->secret);
        $this->assertEquals(new DateTimeImmutable('2026-11-07T09:00:00Z'), $rotated->previousSecretExpiresAt);
        $this->assertSame('/v1/webhook-endpoints/12/secret', $this->http->lastRequest()->getUri()->getPath());

        $this->http->queue(new Response(204));

        $this->client()->webhookEndpoints->delete('12');

        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
    }

    public function test_deliveries_are_listed_and_retried(): void
    {
        $this->http->json(['data' => [[...self::delivery(), 'test' => false, 'status' => 'failed', 'response_status' => 500, 'charge' => 'abc123']], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1]]);

        $delivery = $this->client()->webhookEndpoints->deliveries('12', ['status' => 'failed'])->data[0];

        $this->assertSame('/v1/webhook-endpoints/12/deliveries', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['status' => 'failed'], self::query($this->http->lastRequest()));
        $this->assertFalse($delivery->succeeded());
        $this->assertSame(500, $delivery->responseStatus);
        $this->assertSame('abc123', $delivery->charge);

        $this->http->json(['data' => [...self::delivery(), 'status' => 'pending']]);

        $this->client()->webhookEndpoints->retryDelivery('12', $delivery->id);

        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame("/v1/webhook-endpoints/12/deliveries/{$delivery->id}/retry", $this->http->lastRequest()->getUri()->getPath());
        $this->assertNull($delivery->payload);
        $this->assertSame([], $delivery->attemptLog);
    }

    public function test_a_delivery_is_retrieved_with_every_attempt_and_its_body(): void
    {
        $this->http->json(['data' => [
            ...self::delivery(),
            'attempts' => 2,
            'payload' => ['id' => 'whd_1', 'event' => 'charge.paid', 'data' => ['charge' => ['id' => 'abc123']]],
            'attempt_log' => [
                [
                    'object' => 'webhook_delivery_attempt', 'attempt' => 2, 'url' => 'https://example.com/hooks',
                    'request_headers' => ['Content-Type' => 'application/json', 'X-VeliraPay-Signature' => 't=1762419601,v1=…'],
                    'response_status' => 200, 'response_headers' => ['Content-Type' => 'text/plain'], 'response_body' => 'ok',
                    'error' => null, 'duration_ms' => 84, 'created_at' => '2026-11-06T09:05:00.000000Z',
                ],
                [
                    'object' => 'webhook_delivery_attempt', 'attempt' => 1, 'url' => 'https://example.com/hooks', 'request_headers' => ['Content-Type' => 'application/json'],
                    'response_status' => null, 'response_headers' => null, 'response_body' => null,
                    'error' => 'Timed out after 10 seconds.', 'duration_ms' => 10003, 'created_at' => '2026-11-06T09:00:00.000000Z',
                ],
            ],
        ]]);

        $delivery = $this->client()->webhookEndpoints->retrieveDelivery('12', '9d2e0b8a-0000-4000-8000-5f3a00000000');

        $this->assertSame('GET', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/webhook-endpoints/12/deliveries/9d2e0b8a-0000-4000-8000-5f3a00000000', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['id' => 'whd_1', 'event' => 'charge.paid', 'data' => ['charge' => ['id' => 'abc123']]], $delivery->payload);
        $this->assertCount(2, $delivery->attemptLog);

        [$latest, $first] = $delivery->attemptLog;

        $this->assertTrue($latest->succeeded());
        $this->assertSame(2, $latest->attempt);
        $this->assertSame(['Content-Type' => 'text/plain'], $latest->responseHeaders);
        $this->assertSame('ok', $latest->responseBody);
        $this->assertSame(84, $latest->durationMs);
        $this->assertFalse($first->succeeded());
        $this->assertNull($first->responseHeaders);
        $this->assertSame('Timed out after 10 seconds.', $first->error);
    }

    /**
     * A verified endpoint, as the API sends it.
     *
     * @return array<string, mixed>
     */
    private static function endpoint(): array
    {
        return [
            'id' => '12', 'object' => 'webhook_endpoint', 'url' => 'https://example.com/hooks', 'description' => null, 'events' => ['charge.paid'],
            'active' => true, 'verified' => true, 'deliveries_count' => 0, 'failed_deliveries_count' => 0,
            'verified_at' => '2026-10-20T10:00:00.000000Z', 'created_at' => '2026-10-20T09:00:00.000000Z',
        ];
    }

    /**
     * A test delivery, as the API sends it.
     *
     * @return array<string, mixed>
     */
    private static function delivery(): array
    {
        return [
            'id' => '9d2e0b8a-0000-4000-8000-5f3a00000000', 'object' => 'webhook_delivery', 'event' => 'charge.paid', 'event_id' => null, 'test' => true,
            'status' => 'succeeded', 'attempts' => 1, 'response_status' => 200, 'error' => null, 'charge' => null, 'invoice' => null,
            'created_at' => '2026-11-06T09:00:00.000000Z', 'last_attempted_at' => '2026-11-06T09:00:01.000000Z', 'delivered_at' => '2026-11-06T09:00:01.000000Z',
        ];
    }
}
