<?php

declare(strict_types=1);

namespace VeliraPay\Tests;

use DateTimeImmutable;
use VeliraPay\Enums\BusinessType;
use VeliraPay\Enums\Industry;
use VeliraPay\Enums\MonthlyVolume;

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
        $this->assertSame('+442071234567', $account->supportPhone);
        $this->assertSame('#4f46e5', $account->brandColor);
        $this->assertSame('EUR', $account->defaultCurrency);
        $this->assertSame('1', $account->underpaymentTolerance);
        $this->assertSame('3', $account->overpaymentThreshold);
        $this->assertEquals(new DateTimeImmutable('2026-01-15T10:00:00Z'), $account->createdAt);
        $this->assertSame('test', $account->mode);
        $this->assertTrue($account->isTestMode());
        $this->assertSame(['BTC', 'ETH', 'USDC'], $account->acceptedAssets);
    }

    public function test_the_business_behind_the_account_is_read_into_typed_properties(): void
    {
        $this->http->json(self::fixture('account'));

        $business = $this->client()->account->retrieve()->business;

        $this->assertSame(BusinessType::Company, $business->type);
        $this->assertSame('Acme Holdings Ltd', $business->legalName);
        $this->assertSame('12345678', $business->registrationNumber);
        $this->assertSame('GB123456789', $business->taxId);
        $this->assertSame('1 Example Street', $business->address->line1);
        $this->assertNull($business->address->line2);
        $this->assertSame('London', $business->address->city);
        $this->assertSame('EC1A 1BB', $business->address->postalCode);
        $this->assertNull($business->address->state);
        $this->assertSame('GB', $business->address->country);
        $this->assertSame(Industry::Software, $business->industry);
        $this->assertSame('Project management software, billed monthly.', $business->productDescription);
        $this->assertSame(MonthlyVolume::Under50k, $business->monthlyVolume);
        $this->assertSame('+442071234500', $business->phone);
    }

    /**
     * Account settings returned by the API are available without raw attribute access.
     */
    public function test_account_checkout_invoice_and_payment_settings_are_read(): void
    {
        $this->http->json(['data' => [
            'id' => 'acme',
            'logo_dark_url' => 'https://acme.example/logo-dark.png',
            'terms_url' => 'https://acme.example/terms',
            'refund_policy_url' => 'https://acme.example/refunds',
            'checkout' => ['appearance' => 'dark', 'coin_order' => ['BTC', 'ETH'], 'default_coin' => 'LTC', 'success_url' => null, 'cancel_url' => null, 'success_message' => 'Thank you', 'hide_powered_by' => true],
            'invoices' => ['number_prefix' => 'BILL', 'footer' => 'Thank you', 'show_business_details' => true],
            'checkout_locale' => 'fr',
            'invoice_reminders' => true,
            'api_payout_address_changes' => true,
        ]]);

        $account = $this->client()->account->retrieve();

        $this->assertSame('https://acme.example/logo-dark.png', $account->logoDarkUrl);
        $this->assertSame('https://acme.example/terms', $account->termsUrl);
        $this->assertSame('https://acme.example/refunds', $account->refundPolicyUrl);
        $this->assertSame(['appearance' => 'dark', 'coin_order' => ['BTC', 'ETH'], 'default_coin' => 'LTC', 'success_url' => null, 'cancel_url' => null, 'success_message' => 'Thank you', 'hide_powered_by' => true], $account->checkout);
        $this->assertSame(['number_prefix' => 'BILL', 'footer' => 'Thank you', 'show_business_details' => true], $account->invoices);
        $this->assertSame('fr', $account->checkoutLocale);
        $this->assertTrue($account->invoiceReminders);
        $this->assertTrue($account->apiPayoutAddressChanges);
    }

    /**
     * Older or partial responses keep their account settings optional.
     */
    public function test_missing_account_settings_have_safe_defaults(): void
    {
        $this->http->json(['data' => ['id' => 'acme']]);

        $account = $this->client()->account->retrieve();

        $this->assertNull($account->logoDarkUrl);
        $this->assertNull($account->termsUrl);
        $this->assertNull($account->refundPolicyUrl);
        $this->assertSame([], $account->checkout);
        $this->assertSame([], $account->invoices);
        $this->assertNull($account->checkoutLocale);
        $this->assertFalse($account->invoiceReminders);
        $this->assertFalse($account->apiPayoutAddressChanges);
    }

    public function test_business_details_withheld_from_a_key_without_account_write_read_as_null(): void
    {
        $this->http->json(['data' => ['id' => 'acme', 'display_name' => 'Acme Inc.', 'business' => null], 'meta' => ['accepted_assets' => ['BTC'], 'mode' => 'live']]);

        $account = $this->client()->account->retrieve();

        $this->assertNull($account->get('business'));
        $this->assertSame([], $account->business->toArray());
        $this->assertNull($account->business->type);
        $this->assertNull($account->business->legalName);
        $this->assertNull($account->business->taxId);
        $this->assertNull($account->business->address->country);
        $this->assertNull($account->business->industry);
        $this->assertNull($account->business->monthlyVolume);
        $this->assertSame('Acme Inc.', $account->displayName);
        $this->assertSame(['BTC'], $account->acceptedAssets);
    }

    public function test_business_details_that_are_unset_or_unknown_to_this_library_are_null(): void
    {
        $this->http->json(['data' => ['id' => 'acme', 'business' => ['type' => 'cooperative', 'industry' => null]], 'meta' => []]);

        $account = $this->client()->account->retrieve();

        $this->assertNull($account->supportPhone);
        $this->assertNull($account->business->type);
        $this->assertSame('cooperative', $account->business->get('type'));
        $this->assertNull($account->business->industry);
        $this->assertNull($account->business->monthlyVolume);
        $this->assertNull($account->business->legalName);
        $this->assertNull($account->business->address->country);
    }
}
