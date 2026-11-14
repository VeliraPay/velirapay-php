<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\PayoutStatus;

final class PayoutServiceTest extends TestCase
{
    /**
     * A payout as the API returns it.
     *
     * @return array<string, mixed>
     */
    private static function payout(): array
    {
        return [
            'id' => 'k3v9x2m7q8wz', 'object' => 'payout', 'charge' => 'k3v9x2m7q8wz', 'status' => 'sent', 'asset' => 'USDT',
            'gross_amount' => '11.014175', 'fee_percent' => '3', 'fee_amount' => '0.330425', 'fee_fiat_amount' => '0.33', 'fiat_currency' => 'USD', 'exchange_rate' => '0.9987',
            'net_amount' => '10.68375', 'network_fee' => '0.081611', 'sent_amount' => '10.602139',
            'payout_address' => '0x7099', 'payout_txid' => '0x9a4f', 'explorer_url' => 'https://etherscan.io/tx/0x9a4f',
            'attempts' => 1, 'last_error' => null, 'next_attempt_at' => null,
            'sent_at' => '2026-11-02T20:58:00.000000Z', 'confirmed_at' => '2026-11-02T21:00:04.000000Z', 'created_at' => '2026-11-02T20:57:00.000000Z',
        ];
    }

    public function test_a_payout_is_read_by_its_charge_into_typed_properties(): void
    {
        $this->http->json(['data' => self::payout()]);

        $payout = $this->client()->payouts->retrieve('k3v9x2m7q8wz');

        $this->assertSame('/v1/payouts/k3v9x2m7q8wz', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('k3v9x2m7q8wz', $payout->charge);
        $this->assertTrue($payout->isSent());
        $this->assertFalse($payout->isManual());
        $this->assertSame('USDT', $payout->asset);
        $this->assertSame('11.014175', $payout->grossAmount);
        $this->assertSame('3', $payout->feePercent);
        $this->assertSame('0.330425', $payout->feeAmount);
        $this->assertSame('0.33', $payout->feeFiatAmount);
        $this->assertSame('USD', $payout->fiatCurrency);
        $this->assertSame('0.9987', $payout->exchangeRate);
        $this->assertSame('10.68375', $payout->netAmount);
        $this->assertSame('0.081611', $payout->networkFee);
        $this->assertSame('10.602139', $payout->sentAmount);
        $this->assertSame('0x7099', $payout->payoutAddress);
        $this->assertSame('0x9a4f', $payout->payoutTxid);
        $this->assertSame('https://etherscan.io/tx/0x9a4f', $payout->explorerUrl);
        $this->assertSame(1, $payout->attempts);
        $this->assertNull($payout->lastError);
        $this->assertNull($payout->nextAttemptAt);
        $this->assertEquals(new DateTimeImmutable('2026-11-02T21:00:04Z'), $payout->confirmedAt);
    }

    public function test_a_payout_reads_what_was_set_aside_for_its_network_costs(): void
    {
        $this->http->json(['data' => [
            'asset' => 'ETH',
            'merchant_network_reserve' => [
                'reserved' => '0.0004', 'payout_cost' => '0.00025', 'sweep_budget' => '0.0001', 'sweep_cost' => null,
                'sweep_available' => '0.0001', 'held' => '0.00015', 'residual' => null,
            ],
        ] + self::payout()]);

        $reserve = $this->client()->payouts->retrieve('k3v9x2m7q8wz')->merchantNetworkReserve;

        $this->assertNotNull($reserve);
        $this->assertSame('0.0004', $reserve->reserved);
        $this->assertSame('0.00025', $reserve->payoutCost);
        $this->assertSame('0.0001', $reserve->sweepBudget);
        $this->assertNull($reserve->sweepCost);
        $this->assertSame('0.0001', $reserve->sweepAvailable);
        $this->assertSame('0.00015', $reserve->held);
        $this->assertNull($reserve->residual);
    }

    public function test_a_payout_without_a_network_reserve_reads_it_as_null(): void
    {
        $this->http->json(['data' => self::payout()])->json(['data' => ['merchant_network_reserve' => null] + self::payout()]);

        $this->assertNull($this->client()->payouts->retrieve('k3v9x2m7q8wz')->merchantNetworkReserve);
        $this->assertNull($this->client()->payouts->retrieve('k3v9x2m7q8wz')->merchantNetworkReserve);
    }

    public function test_payouts_are_listed_with_their_filters(): void
    {
        $this->http->json(['data' => [self::payout()], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1]]);

        $page = $this->client()->payouts->list(['status' => PayoutStatus::Sent, 'asset' => Asset::USDT]);

        $this->assertSame('/v1/payouts', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('status=sent&asset=USDT', $this->http->lastRequest()->getUri()->getQuery());
        $this->assertSame('k3v9x2m7q8wz', $page->data[0]->id);
    }

    public function test_the_summary_adds_up_each_coin(): void
    {
        $this->http->json([
            'data' => [[
                'object' => 'payout_summary', 'asset' => 'BTC', 'payouts' => 3, 'gross_amount' => '0.3', 'fee_amount' => '0.009',
                'owed_amount' => '0.097', 'in_flight_amount' => '0', 'paid_out_amount' => '0.194', 'network_fee_amount' => '0.0001', 'waiting_for_address' => 0,
            ]],
            'meta' => ['mode' => 'live'],
        ]);

        $summary = $this->client()->payouts->summary();

        $this->assertSame('/v1/payouts/summary', $this->http->lastRequest()->getUri()->getPath());
        $this->assertCount(1, $summary);
        $this->assertSame('BTC', $summary[0]->asset);
        $this->assertSame(3, $summary[0]->payouts);
        $this->assertSame('0.3', $summary[0]->grossAmount);
        $this->assertSame('0.009', $summary[0]->feeAmount);
        $this->assertSame('0.194', $summary[0]->paidOutAmount);
        $this->assertSame('0', $summary[0]->inFlightAmount);
        $this->assertSame('0.097', $summary[0]->owedAmount);
        $this->assertSame('0.0001', $summary[0]->networkFeeAmount);
        $this->assertSame(0, $summary[0]->waitingForAddress);
    }
}
