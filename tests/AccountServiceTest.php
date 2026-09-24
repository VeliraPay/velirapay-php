<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;

final class AccountServiceTest extends TestCase
{
    public function test_the_account_is_retrieved_with_the_coins_it_accepts(): void
    {
        $this->http->json(self::fixture('account'));

        $account = $this->client()->account->retrieve();

        $this->assertSame('/v1/account', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('acme', $account->id);
        $this->assertTrue($account->isOrganization());
        $this->assertSame('Acme', $account->name);
        $this->assertSame('Acme Inc.', $account->displayName);
        $this->assertNull($account->logoUrl);
        $this->assertSame('billing@acme.example', $account->supportEmail);
        $this->assertSame('#4f46e5', $account->brandColor);
        $this->assertSame('EUR', $account->defaultCurrency);
        $this->assertSame('1', $account->underpaymentTolerance);
        $this->assertSame('3', $account->overpaymentThreshold);
        $this->assertEquals(new DateTimeImmutable('2026-01-15T10:00:00Z'), $account->createdAt);
        $this->assertSame('test', $account->mode);
        $this->assertTrue($account->isTestMode());
        $this->assertSame(['BTC', 'ETH', 'USDC_BASE'], $account->acceptedAssets);
    }
}
