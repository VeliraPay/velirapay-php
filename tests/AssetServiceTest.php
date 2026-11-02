<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use VeliraPay\Enums\Asset;

final class AssetServiceTest extends TestCase
{
    /**
     * The API's answer for two coins, one with a minimum of its own.
     *
     * @return array<string, mixed>
     */
    private static function assets(): array
    {
        return [
            'data' => [
                [
                    'id' => 'BTC', 'object' => 'asset', 'name' => 'Bitcoin', 'ticker' => 'BTC', 'network' => 'Bitcoin',
                    'contract_address' => null, 'decimals' => 8, 'required_confirmations' => 2, 'confirmation_wait' => 'about 20 minutes',
                    'accepted' => true, 'currency' => 'EUR', 'exchange_rate' => '68527.48', 'minimum_amount' => null, 'minimum_price' => '0.01',
                ],
                [
                    'id' => 'USDT', 'object' => 'asset', 'name' => 'Tether USD', 'ticker' => 'USDT', 'network' => 'Ethereum (ERC-20)',
                    'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'decimals' => 6, 'required_confirmations' => 6, 'confirmation_wait' => 'about a minute',
                    'accepted' => false, 'currency' => 'EUR', 'exchange_rate' => '0.8709', 'minimum_amount' => '10', 'minimum_price' => '8.71',
                ],
                [
                    'id' => 'NEWCOIN', 'object' => 'asset', 'name' => 'New', 'ticker' => 'NEW', 'network' => 'New',
                    'contract_address' => null, 'decimals' => 18, 'required_confirmations' => 1, 'confirmation_wait' => 'under a minute',
                    'accepted' => false, 'currency' => 'EUR', 'exchange_rate' => null, 'minimum_amount' => null, 'minimum_price' => '0.01',
                ],
            ],
            'meta' => ['mode' => 'live', 'currencies' => ['USD', 'EUR']],
        ];
    }

    public function test_the_coins_on_offer_are_read_with_their_minimums(): void
    {
        $this->http->json(self::assets());

        $page = $this->client()->assets->list(['currency' => 'EUR']);

        $request = $this->http->lastRequest();
        $this->assertSame('/v1/assets', $request->getUri()->getPath());
        $this->assertSame('currency=EUR', $request->getUri()->getQuery());
        $this->assertCount(3, $page);
        $this->assertFalse($page->hasMore());

        [$bitcoin, $tether, $unknown] = $page->data;

        $this->assertSame('BTC', $bitcoin->id);
        $this->assertSame(Asset::BTC, $bitcoin->asset());
        $this->assertTrue($bitcoin->accepted);
        $this->assertNull($bitcoin->minimumAmount);
        $this->assertSame('0.01', $bitcoin->minimumPrice);
        $this->assertNull($bitcoin->contractAddress);
        $this->assertSame(8, $bitcoin->decimals);
        $this->assertSame(2, $bitcoin->requiredConfirmations);
        $this->assertSame('about 20 minutes', $bitcoin->confirmationWait);

        $this->assertSame('Tether USD', $tether->name);
        $this->assertSame('USDT', $tether->ticker);
        $this->assertSame('Ethereum (ERC-20)', $tether->network);
        $this->assertFalse($tether->accepted);
        $this->assertSame('EUR', $tether->currency);
        $this->assertSame('0.8709', $tether->exchangeRate);
        $this->assertSame('10', $tether->minimumAmount);
        $this->assertSame('8.71', $tether->minimumPrice);

        $this->assertNull($unknown->asset());
        $this->assertNull($unknown->exchangeRate);
    }
}
