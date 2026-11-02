<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;

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
}
