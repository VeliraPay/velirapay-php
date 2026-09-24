<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use VeliraPay\Enums\EventType;
use VeliraPay\Resources\Event;

final class EventServiceTest extends TestCase
{
    public function test_an_event_is_retrieved_with_its_charge(): void
    {
        $this->http->json(['data' => self::event()]);

        $event = $this->client()->events->retrieve('9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b');

        $this->assertSame('/v1/events/9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b', $event->id);
        $this->assertSame('charge.paid', $event->type);
        $this->assertTrue($event->is(EventType::ChargeUnderpaid, 'charge.paid'));
        $this->assertFalse($event->is(EventType::ChargeExpired));
        $this->assertSame(['amount' => '0.0025'], $event->details);
        $this->assertEquals(new DateTimeImmutable('2026-09-20T10:05:41Z'), $event->createdAt);
        $this->assertSame('k3v9x2m7q8wz', $event->charge?->id);
        $this->assertNull($event->invoice);
    }

    public function test_events_are_listed_with_filters(): void
    {
        $this->http->json(self::listResponse([self::event()]));

        $page = $this->client()->events->list(['type' => EventType::ChargePaid, 'charge' => 'k3v9x2m7q8wz']);

        $this->assertSame('/v1/events', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['type' => 'charge.paid', 'charge' => 'k3v9x2m7q8wz'], self::query($this->http->lastRequest()));
        $this->assertInstanceOf(Event::class, $page->data[0]);
    }

    /**
     * Build an event as the API returns it.
     *
     * @return array<string, mixed>
     */
    private static function event(): array
    {
        return [
            'id' => '9b1f7a3e-2c4d-4e8f-a6b0-1d2c3e4f5a6b',
            'object' => 'event',
            'type' => 'charge.paid',
            'details' => ['amount' => '0.0025'],
            'created_at' => '2026-09-20T10:05:41.000000Z',
            'charge' => self::fixture('charge'),
            'invoice' => null,
        ];
    }
}
