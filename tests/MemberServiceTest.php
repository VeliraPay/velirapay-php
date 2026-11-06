<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Response;

final class MemberServiceTest extends TestCase
{
    public function test_members_are_listed_changed_and_removed(): void
    {
        $this->http->json([
            'data' => [['id' => '3', 'object' => 'member', 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'admin', 'joined_at' => '2026-10-01T08:00:00.000000Z']],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1],
        ]);

        $member = $this->client()->members->list()->data[0];

        $this->assertSame('/v1/members', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('ada@example.com', $member->email);
        $this->assertSame('admin', $member->role);
        $this->assertEquals(new DateTimeImmutable('2026-10-01T08:00:00Z'), $member->joinedAt);

        $this->http->json(['data' => ['id' => '3', 'object' => 'member', 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'member']]);

        $this->assertSame('member', $this->client()->members->update('3', ['role' => 'member'])->role);
        $this->assertSame(['role' => 'member'], self::body($this->http->lastRequest()));

        $this->http->queue(new Response(204));

        $this->client()->members->remove('3');

        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/members/3', $this->http->lastRequest()->getUri()->getPath());
    }

    public function test_invitations_are_sent_listed_and_cancelled(): void
    {
        $invitation = ['id' => '9', 'object' => 'invitation', 'email' => 'grace@example.com', 'role' => 'admin', 'invited_by' => 'Ada', 'expired' => false, 'expires_at' => '2026-11-09T09:00:00.000000Z'];
        $this->http->json(['data' => $invitation], 201);

        $sent = $this->client()->invitations->create(['email' => 'grace@example.com', 'role' => 'admin']);

        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/invitations', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('9', $sent->id);
        $this->assertSame('Ada', $sent->invitedBy);
        $this->assertFalse($sent->expired);

        $this->http->json(['data' => [$invitation], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1]]);

        $this->assertSame('grace@example.com', $this->client()->invitations->list()->data[0]->email);

        $this->http->queue(new Response(204));

        $this->client()->invitations->cancel('9');

        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/invitations/9', $this->http->lastRequest()->getUri()->getPath());
    }
}
