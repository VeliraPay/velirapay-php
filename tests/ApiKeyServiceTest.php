<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use GuzzleHttp\Psr7\Response;
use VeliraPay\Enums\ApiKeyPermission;

final class ApiKeyServiceTest extends TestCase
{
    public function test_a_key_is_created_with_the_key_itself_shown_once(): void
    {
        $this->http->json(['data' => [
            'id' => '42', 'object' => 'api_key', 'name' => 'Shop server', 'prefix' => 'vp_live_8Kq2', 'key' => 'vp_live_8Kq2abc',
            'mode' => 'live', 'permissions' => ['charges:read'], 'allowed_ips' => ['203.0.113.7'], 'created_by' => 'Ada',
            'last_used_at' => null, 'created_at' => '2026-11-06T09:12:44.000000Z',
        ]], 201);

        $apiKey = $this->client()->apiKeys->create(['name' => 'Shop server', 'permissions' => ['charges:read'], 'allowed_ips' => ['203.0.113.7']], 'new-key');

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/api-keys', $request->getUri()->getPath());
        $this->assertSame('new-key', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(['name' => 'Shop server', 'permissions' => ['charges:read'], 'allowed_ips' => ['203.0.113.7']], self::body($request));
        $this->assertSame('vp_live_8Kq2abc', $apiKey->key);
        $this->assertSame(['charges:read'], $apiKey->permissions);
        $this->assertSame(['203.0.113.7'], $apiKey->allowedIps);
        $this->assertFalse($apiKey->hasFullAccess());
    }

    public function test_permissions_are_sent_as_their_values_whether_given_as_cases_or_strings(): void
    {
        $this->http->json(['data' => [
            'id' => '43', 'object' => 'api_key', 'name' => 'Books', 'prefix' => 'vp_live_9Lr3', 'key' => 'vp_live_9Lr3abc',
            'mode' => 'live', 'permissions' => ['payouts:read', 'account:write'], 'allowed_ips' => null,
        ]], 201);

        $this->client()->apiKeys->create(['name' => 'Books', 'permissions' => [ApiKeyPermission::PayoutsRead, 'account:write']]);

        $this->assertSame(['name' => 'Books', 'permissions' => ['payouts:read', 'account:write']], self::body($this->http->lastRequest()));
    }

    public function test_keys_are_listed_changed_and_revoked(): void
    {
        $this->http->json([
            'data' => [['id' => '7', 'object' => 'api_key', 'name' => 'Old', 'prefix' => 'vp_live_aaaa', 'mode' => 'live', 'permissions' => null, 'allowed_ips' => null]],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1],
        ]);

        $listed = $this->client()->apiKeys->list()->data[0];

        $this->assertSame('/v1/api-keys', $this->http->lastRequest()->getUri()->getPath());
        $this->assertNull($listed->key);
        $this->assertTrue($listed->hasFullAccess());

        $this->http->json(['data' => ['id' => '7', 'object' => 'api_key', 'name' => 'Reader', 'prefix' => 'vp_live_aaaa', 'mode' => 'live', 'permissions' => ['charges:read'], 'allowed_ips' => null]]);

        $this->client()->apiKeys->update('7', ['name' => 'Reader', 'permissions' => ['charges:read']]);

        $this->assertSame('PATCH', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/api-keys/7', $this->http->lastRequest()->getUri()->getPath());

        $this->http->queue(new Response(204));

        $this->client()->apiKeys->revoke('7');

        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/api-keys/7', $this->http->lastRequest()->getUri()->getPath());
    }
}
