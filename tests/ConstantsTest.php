<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use BackedEnum;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use VeliraPay\Enums\AccountType;
use VeliraPay\Enums\ActivityAction;
use VeliraPay\Enums\AddressNetwork;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\CheckoutAppearance;
use VeliraPay\Enums\CheckoutLocale;
use VeliraPay\Enums\Currency;
use VeliraPay\Enums\EventType;
use VeliraPay\Enums\MemberRole;
use VeliraPay\Enums\PlanName;
use VeliraPay\Enums\WebhookDeliveryStatus;
use VeliraPay\Resources\PayoutAddress;

/**
 * The constants of the values the API uses, and the methods that take them as well as strings.
 */
final class ConstantsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<BackedEnum>, list<string>}>
     */
    public static function valueSets(): iterable
    {
        yield 'currencies' => [Currency::class, ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'TRY']];
        yield 'member roles' => [MemberRole::class, ['owner', 'admin', 'finance', 'support', 'viewer']];
        yield 'address networks' => [AddressNetwork::class, ['bitcoin', 'litecoin', 'dogecoin', 'bitcoincash', 'evm', 'bsc', 'base', 'arbitrum', 'polygon', 'tron', 'monero', 'solana', 'lightning']];
        yield 'checkout appearances' => [CheckoutAppearance::class, ['auto', 'light', 'dark']];
        yield 'webhook delivery statuses' => [WebhookDeliveryStatus::class, ['pending', 'succeeded', 'failed']];
        yield 'plan names' => [PlanName::class, ['free', 'pro']];
        yield 'account types' => [AccountType::class, ['personal', 'organization']];
        yield 'checkout locales' => [CheckoutLocale::class, ['en', 'fr', 'de', 'es', 'tr', 'pt', 'ru', 'ar']];
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @param  list<string>  $values
     */
    #[DataProvider('valueSets')]
    public function test_each_set_names_the_values_the_api_uses_in_its_order(string $enum, array $values): void
    {
        $this->assertSame($values, array_map(static fn (BackedEnum $case): int|string => $case->value, $enum::cases()));
    }

    public function test_every_activity_action_is_named_the_newest_included(): void
    {
        $this->assertCount(55, ActivityAction::cases());
        $this->assertSame(ActivityAction::SecondFactorRequirementChanged, ActivityAction::from('team.second_factor_requirement_changed'));
        $this->assertSame(ActivityAction::SocialAccountDisconnected, ActivityAction::from('auth.social_disconnected'));
    }

    public function test_an_owner_is_never_given_through_the_api(): void
    {
        $this->assertSame([MemberRole::Admin, MemberRole::Finance, MemberRole::Support, MemberRole::Viewer], MemberRole::assignable());
    }

    public function test_currencies_and_the_checkout_language_are_sent_as_their_values(): void
    {
        $account = self::fixture('account')['data'];
        $this->assertIsArray($account);
        $this->http
            ->json(['data' => [], 'meta' => ['mode' => 'live', 'currencies' => ['USD', 'EUR']]])
            ->json(['data' => $account, 'meta' => ['mode' => 'live', 'accepted_assets' => []]]);

        $this->client()->assets->list(['currency' => Currency::EUR]);

        $this->assertSame('currency=EUR', $this->http->lastRequest()->getUri()->getQuery());

        $this->client()->account->updatePaymentSettings(['default_currency' => Currency::CHF, 'checkout_locale' => CheckoutLocale::French]);

        $this->assertSame(['default_currency' => 'CHF', 'checkout_locale' => 'fr'], self::body($this->http->lastRequest()));
    }

    public function test_roles_are_sent_as_their_values(): void
    {
        $this->http
            ->json(['data' => ['id' => '3', 'object' => 'member', 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'finance']])
            ->json(['data' => ['id' => '9', 'object' => 'invitation', 'email' => 'grace@example.com', 'role' => 'viewer']], 201);

        $this->client()->members->update('3', ['role' => MemberRole::Finance]);

        $this->assertSame(['role' => 'finance'], self::body($this->http->lastRequest()));

        $this->client()->invitations->create(['email' => 'grace@example.com', 'role' => MemberRole::Viewer]);

        $this->assertSame(['email' => 'grace@example.com', 'role' => 'viewer'], self::body($this->http->lastRequest()));
    }

    public function test_a_payout_address_is_changed_by_its_network_constant(): void
    {
        $bitcoin = ['object' => 'payout_address', 'network' => 'bitcoin', 'name' => 'Bitcoin', 'address' => 'bc1qexample', 'assets' => ['BTC']];
        $this->http->json(['data' => $bitcoin])->json(['data' => [...$bitcoin, 'network' => 'evm', 'name' => 'Ethereum', 'address' => null]]);

        $address = $this->client()->payoutAddresses->update(AddressNetwork::Bitcoin, ['address' => 'bc1qexample']);

        $this->assertSame('/v1/payout-addresses/bitcoin', $this->http->lastRequest()->getUri()->getPath());
        $this->assertSame(AddressNetwork::Bitcoin, $address->addressNetwork());

        $this->client()->payoutAddresses->delete(AddressNetwork::Evm);

        $this->assertSame('DELETE', $this->http->lastRequest()->getMethod());
        $this->assertSame('/v1/payout-addresses/evm', $this->http->lastRequest()->getUri()->getPath());
        $this->assertNull(PayoutAddress::fromArray(['network' => 'newchain'])->addressNetwork());
    }

    public function test_events_and_delivery_statuses_are_sent_as_their_values(): void
    {
        $endpoint = [
            'id' => '12', 'object' => 'webhook_endpoint', 'url' => 'https://example.com/hooks', 'description' => null, 'events' => ['charge.paid', 'invoice.paid'],
            'active' => true, 'verified' => false, 'deliveries_count' => 0, 'failed_deliveries_count' => 0, 'verified_at' => null, 'created_at' => '2026-11-06T09:00:00.000000Z',
        ];
        $this->http
            ->json(['data' => [...$endpoint, 'secret' => 'whsec_abc'], 'meta' => ['verification_error' => null]], 201)
            ->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0]]);

        $this->client()->webhookEndpoints->create(['url' => 'https://example.com/hooks', 'events' => [EventType::ChargePaid, 'invoice.paid']]);

        $this->assertSame(['url' => 'https://example.com/hooks', 'events' => ['charge.paid', 'invoice.paid']], self::body($this->http->lastRequest()));

        $this->client()->webhookEndpoints->deliveries('12', ['status' => WebhookDeliveryStatus::Failed, 'event' => EventType::ChargePaid]);

        $this->assertSame(['status' => 'failed', 'event' => 'charge.paid'], self::query($this->http->lastRequest()));
    }

    public function test_a_pro_payment_and_the_activity_log_take_their_constants(): void
    {
        $this->http
            ->json(['data' => ['object' => 'pro_payment', 'months' => 1, 'price' => '9.99', 'currency' => 'USD', 'asset' => 'LTC', 'asset_amount' => '0.1', 'checkout_url' => 'https://velirapay.com/c/abc'], 'meta' => ['started' => true]], 201)
            ->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'mode' => 'live']]);

        $this->client('vp_live_secret')->billing->startProPayment(['asset' => Asset::LTC]);

        $this->assertSame(['asset' => 'LTC'], self::body($this->http->lastRequest()));

        $this->client('vp_live_secret')->activity->list(['action' => ActivityAction::ApiKeyCreated]);

        $this->assertSame(['action' => 'api_key.created'], self::query($this->http->lastRequest()));
    }

    public function test_a_delivery_that_succeeded_is_recognised_by_its_status(): void
    {
        $this->http->queue(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['data' => [
            'id' => '9d2e0b8a-0000-4000-8000-5f3a00000000', 'object' => 'webhook_delivery', 'event' => 'charge.paid', 'status' => WebhookDeliveryStatus::Succeeded->value,
        ]])));

        $this->assertTrue($this->client()->webhookEndpoints->retrieveDelivery('12', '9d2e0b8a-0000-4000-8000-5f3a00000000')->succeeded());
    }
}
