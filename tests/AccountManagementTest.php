<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use DateTimeZone;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\BusinessType;
use VeliraPay\Enums\CheckoutAppearance;
use VeliraPay\Enums\Industry;
use VeliraPay\Enums\MonthlyVolume;
use VeliraPay\Resources\Overview;

/**
 * The overview, settings, payout addresses, activity log and Pro billing.
 */
final class AccountManagementTest extends TestCase
{
    public function test_the_account_and_its_payment_settings_are_changed(): void
    {
        $account = self::fixture('account')['data'];
        $this->assertIsArray($account);
        $this->http->json(['data' => [...$account, 'display_name' => 'Acme', 'plan' => ['name' => 'pro', 'fee_percent' => '0', 'pro_ends_at' => '2026-12-06T00:00:00.000000Z']], 'meta' => ['mode' => 'live', 'accepted_assets' => ['BTC']]]);

        $updated = $this->client()->account->update(['display_name' => 'Acme']);

        $this->assertSame('PATCH', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/account', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['display_name' => 'Acme'], self::body($this->http->lastRequest()));
        $this->assertSame('Acme', $updated->displayName);
        $this->assertSame(['BTC'], $updated->acceptedAssets);
        $this->assertTrue($updated->isPro());
        $this->assertSame('0', $updated->feePercent);
        $this->assertEquals(new DateTimeImmutable('2026-12-06T00:00:00Z'), $updated->proEndsAt);

        $this->http->json(['data' => $account, 'meta' => ['mode' => 'live', 'accepted_assets' => []]]);

        $settings = $this->client()->account->updatePaymentSettings(['underpayment_tolerance' => '2']);

        $this->assertSame('PATCH', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/payment-settings', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['underpayment_tolerance' => '2'], self::body($this->http->lastRequest()));
        $this->assertSame('live', $settings->mode);
    }

    public function test_every_account_setting_is_sent_in_the_shape_it_is_read_in(): void
    {
        $account = self::fixture('account')['data'];
        $this->assertIsArray($account);
        $this->http->json(['data' => $account, 'meta' => ['mode' => 'live', 'accepted_assets' => []]]);

        $this->client()->account->update([
            'name' => 'Acme Studio',
            'display_name' => 'Acme',
            'website_url' => 'https://acme.example',
            'support_email' => 'help@acme.example',
            'support_phone' => '+442071234567',
            'terms_url' => 'https://acme.example/terms',
            'refund_policy_url' => null,
            'brand_color' => '#0f766e',
            'checkout' => [
                'appearance' => CheckoutAppearance::Dark,
                'coin_order' => [Asset::LTC, 'BTC'],
                'default_coin' => Asset::LTC,
                'success_url' => 'https://acme.example/thanks',
                'cancel_url' => null,
                'success_message' => 'Thank you.',
                'hide_powered_by' => false,
            ],
            'invoices' => ['number_prefix' => 'ACME', 'footer' => null, 'show_business_details' => true],
            'business' => [
                'type' => BusinessType::Company,
                'legal_name' => 'Acme Studio Ltd',
                'registration_number' => '12345678',
                'tax_id' => 'GB123456789',
                'address' => ['line1' => '1 High Street', 'line2' => null, 'city' => 'London', 'postal_code' => 'EC1A 1BB', 'state' => null, 'country' => 'GB'],
                'industry' => Industry::Software,
                'product_description' => 'Design tools.',
                'monthly_volume' => MonthlyVolume::Under10k,
                'phone' => '+442071234567',
            ],
        ]);

        $body = self::body($this->http->lastRequest());
        $this->assertSame(['appearance' => 'dark', 'coin_order' => ['LTC', 'BTC'], 'default_coin' => 'LTC', 'success_url' => 'https://acme.example/thanks', 'cancel_url' => null, 'success_message' => 'Thank you.', 'hide_powered_by' => false], $body['checkout']);
        $this->assertSame(['number_prefix' => 'ACME', 'footer' => null, 'show_business_details' => true], $body['invoices']);
        $this->assertIsArray($body['business']);
        $this->assertSame('company', $body['business']['type']);
        $this->assertSame('software', $body['business']['industry']);
        $this->assertSame('1k_10k', $body['business']['monthly_volume']);
        $this->assertSame(['line1' => '1 High Street', 'line2' => null, 'city' => 'London', 'postal_code' => 'EC1A 1BB', 'state' => null, 'country' => 'GB'], $body['business']['address']);
        $this->assertNull($body['refund_policy_url']);
    }

    public function test_float_tolerances_are_sent_as_plain_decimal_strings(): void
    {
        $account = self::fixture('account')['data'];
        $this->assertIsArray($account);
        $this->http->json(['data' => $account, 'meta' => ['mode' => 'live', 'accepted_assets' => []]]);

        $this->client()->account->updatePaymentSettings(['underpayment_tolerance' => 1.5, 'overpayment_threshold' => 2]);

        $this->assertSame(['underpayment_tolerance' => '1.5', 'overpayment_threshold' => 2], self::body($this->http->lastRequest()));
    }

    public function test_the_overview_is_retrieved(): void
    {
        $this->http->json(['data' => ['object' => 'overview', 'currency' => 'USD', 'volume' => '1250.00', 'volume_change_percent' => 12.5, 'sent_volume' => '1100.00', 'paid_charges' => 12, 'conversion_rate_percent' => 60, 'pending_charges' => 2, 'active_payment_links' => 3, 'open_invoices' => 1, 'overdue_invoices' => 0, 'funnel' => ['started' => 20]]]);

        $overview = $this->client()->overview->retrieve();

        $this->assertSame('/v1/overview', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('1250.00', $overview->volume);
        $this->assertSame(12.5, $overview->volumeChangePercent);
        $this->assertSame('1100.00', $overview->sentVolume);
        $this->assertSame(12, $overview->paidCharges);
        $this->assertSame(60.0, $overview->conversionRatePercent);
        $this->assertSame(['started' => 20], $overview->get('funnel'));
    }

    public function test_the_overview_reads_its_period_funnel_attention_coins_links_setup_and_days(): void
    {
        $this->http->json(self::fixture('overview'));

        $overview = $this->client()->overview->retrieve();

        $this->assertSame('live', $overview->mode);
        $this->assertEquals(new DateTimeImmutable('2026-10-14T00:00:00Z'), $overview->period->from);
        $this->assertEquals(new DateTimeImmutable('2026-11-12T00:00:00Z'), $overview->period->to);

        $this->assertSame(20, $overview->funnel->started);
        $this->assertSame(12, $overview->funnel->paid);
        $this->assertSame(2, $overview->funnel->waiting);
        $this->assertSame(5, $overview->funnel->abandoned);
        $this->assertSame(1, $overview->funnel->incomplete);
        $this->assertCount(2, $overview->funnel->assets);
        $this->assertSame('LTC', $overview->funnel->assets[0]->asset);
        $this->assertSame(11, $overview->funnel->assets[0]->started);
        $this->assertSame(8, $overview->funnel->assets[0]->paid);
        $this->assertSame(2, $overview->funnel->assets[0]->abandoned);

        $underpaid = $overview->attention->underpaidCharges[0];
        $this->assertSame('k3v9x2m7q8wz', $underpaid->id);
        $this->assertSame('BTC', $underpaid->asset);
        $this->assertSame('0.0025', $underpaid->amount);
        $this->assertSame('0.002', $underpaid->receivedAmount);
        $this->assertSame('m2q7w4e9r1ty', $overview->attention->latePayments[0]->id);
        $this->assertSame('1.5', $overview->attention->latePayments[0]->receivedAmount);
        $this->assertSame('SOL', $overview->attention->payoutsWaitingForAddress[0]->asset);
        $this->assertSame(2, $overview->attention->payoutsWaitingForAddress[0]->count);
        $this->assertSame(1, $overview->attention->failingWebhookEndpoints);

        $this->assertSame(['LTC', 'BTC'], array_map(static fn ($asset): string => $asset->asset, $overview->assets));
        $this->assertSame(8, $overview->assets[0]->paidCharges);
        $this->assertSame(66.67, $overview->assets[0]->sharePercent);

        [$link, $deleted] = $overview->topPaymentLinks;
        $this->assertSame('p4n8r2t6y1ua', $link->id);
        $this->assertSame('Pro plan', $link->title);
        $this->assertSame(7, $link->paidCharges);
        $this->assertNull($deleted->id);
        $this->assertNull($deleted->title);

        $this->assertTrue($overview->setup->payoutAddress);
        $this->assertTrue($overview->setup->paymentLink);
        $this->assertTrue($overview->setup->paidCharge);
        $this->assertFalse($overview->setup->webhookEndpoint);
        $this->assertFalse($overview->setup->publicProfile);

        $this->assertCount(2, $overview->daily);
        $this->assertEquals(new DateTimeImmutable('2026-11-12T00:00:00Z'), $overview->daily[1]->date);
        $this->assertSame('150.00', $overview->daily[1]->volume);
        $this->assertSame('145.50', $overview->daily[1]->sentVolume);
        $this->assertSame(1, $overview->daily[1]->paidCharges);

        $this->assertSame(['started' => 20, 'paid' => 12, 'waiting' => 2, 'abandoned' => 5, 'incomplete' => 1, 'assets' => $overview->funnel->toArray()['assets']], $overview->get('funnel'));
    }

    public function test_an_overview_built_without_its_figures_has_empty_ones(): void
    {
        $overview = new Overview([], 'USD', '0', 0, 0, 0, 0, 0);

        $this->assertNull($overview->period->from);
        $this->assertSame(0, $overview->funnel->started);
        $this->assertSame([], $overview->funnel->assets);
        $this->assertSame([], $overview->attention->underpaidCharges);
        $this->assertSame(0, $overview->attention->failingWebhookEndpoints);
        $this->assertSame([], $overview->assets);
        $this->assertSame([], $overview->topPaymentLinks);
        $this->assertFalse($overview->setup->payoutAddress);
        $this->assertSame([], $overview->daily);
        $this->assertNull($overview->mode);
    }

    public function test_the_overview_tells_a_zero_percentage_from_one_with_nothing_to_compare(): void
    {
        $this->http->json(['data' => ['object' => 'overview', 'currency' => 'USD', 'volume' => '0', 'volume_change_percent' => null, 'sent_volume' => '0', 'paid_charges' => 0, 'conversion_rate_percent' => 0]]);

        $overview = $this->client()->overview->retrieve();

        $this->assertNull($overview->volumeChangePercent);
        $this->assertSame(0.0, $overview->conversionRatePercent);
    }

    public function test_an_overview_without_the_newer_figures_still_reads(): void
    {
        $this->http->json(['data' => ['object' => 'overview', 'currency' => 'USD', 'volume' => '1250.00', 'paid_charges' => 12]]);

        $overview = $this->client()->overview->retrieve();

        $this->assertNull($overview->volumeChangePercent);
        $this->assertSame('0', $overview->sentVolume);
        $this->assertNull($overview->conversionRatePercent);
        $this->assertNull($overview->period->to);
        $this->assertSame(0, $overview->funnel->paid);
        $this->assertSame([], $overview->attention->latePayments);
        $this->assertFalse($overview->setup->publicProfile);
        $this->assertSame([], $overview->daily);
        $this->assertNull($overview->mode);
    }

    public function test_payout_addresses_are_listed_set_and_removed(): void
    {
        $bitcoin = ['object' => 'payout_address', 'network' => 'bitcoin', 'name' => 'Bitcoin', 'address' => 'bc1qexample', 'assets' => ['BTC'], 'payouts_held_until' => null, 'updated_at' => '2026-10-02T08:14:51.000000Z'];
        $this->http->json(['data' => [$bitcoin, [...$bitcoin, 'network' => 'evm', 'name' => 'Ethereum', 'address' => null, 'assets' => ['ETH', 'USDT']]], 'meta' => ['mode' => 'live']]);

        [$set, $missing] = $this->client()->payoutAddresses->list();

        $this->assertSame('bc1qexample', $set->address);
        $this->assertNull($missing->address);
        $this->assertSame(['ETH', 'USDT'], $missing->assets);

        $this->http->json(['data' => [...$bitcoin, 'payouts_held_until' => '2026-11-08T08:00:00.000000Z']]);

        $updated = $this->client()->payoutAddresses->update('bitcoin', ['address' => 'bc1qexample']);

        $this->assertSame('PUT', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/payout-addresses/bitcoin', $this->http->lastRequest()->getUri()->getPath());
        $this->assertEquals(new DateTimeImmutable('2026-11-08T08:00:00Z'), $updated->payoutsHeldUntil);

        $this->http->json(['data' => [...$bitcoin, 'address' => null]]);

        $this->assertNull($this->client()->payoutAddresses->delete('bitcoin')->address);
        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
    }

    public function test_the_activity_log_is_listed_with_its_filters(): void
    {
        $this->http->json([
            'data' => [['id' => '5', 'object' => 'activity', 'action' => 'api_key.created', 'description' => 'Created an API key', 'actor' => 'API key "Bot"', 'subject' => 'Shop', 'properties' => ['prefix' => 'vp_live_aaaa'], 'ip_address' => '203.0.113.7', 'mode' => 'live', 'created_at' => '2026-11-06T09:00:00.000000Z']],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 1, 'mode' => 'live'],
        ]);

        $entry = $this->client()->activity->list(['action' => 'sensitive'])->data[0];

        $this->assertSame('/v1/activity', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(['action' => 'sensitive'], self::query($this->http->lastRequest()));
        $this->assertSame('api_key.created', $entry->action);
        $this->assertSame(['prefix' => 'vp_live_aaaa'], $entry->properties);
    }

    public function test_the_activity_log_is_narrowed_to_the_days_dates_fall_on(): void
    {
        $this->http->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'mode' => 'live']]);

        $this->client()->activity->list(['from' => new DateTimeImmutable('2026-11-01 00:30:00', new DateTimeZone('Europe/Paris')), 'to' => '2026-11-06']);

        $this->assertSame(['from' => '2026-11-01', 'to' => '2026-11-06'], self::query($this->http->lastRequest()));
    }

    public function test_a_pro_payment_is_started(): void
    {
        $this->http->json(['data' => ['object' => 'pro_payment', 'months' => 1, 'price' => '9.99', 'currency' => 'USD', 'asset' => 'BTC', 'asset_amount' => '0.0001', 'checkout_url' => 'https://velirapay.com/c/abc', 'expires_at' => '2026-11-06T10:00:00.000000Z'], 'meta' => ['started' => true]], 201);

        $payment = $this->client('vp_live_secret')->billing->startProPayment(['asset' => 'BTC']);

        $this->assertSame('POST', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/billing/pro', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame('https://velirapay.com/c/abc', $payment->checkoutUrl);
        $this->assertSame('9.99', $payment->price);
        $this->assertTrue($payment->started);
    }
}
