<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use VeliraPay\Enums\Asset;
use VeliraPay\Enums\BusinessType;
use VeliraPay\Enums\CheckoutAppearance;
use VeliraPay\Enums\CheckoutLocale;
use VeliraPay\Enums\Currency;
use VeliraPay\Enums\Industry;
use VeliraPay\Enums\MonthlyVolume;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Http\ApiResponse;
use VeliraPay\Resources\Account;

/**
 * The /v1/account and /v1/payment-settings endpoints.
 */
final class AccountService extends Service
{
    /**
     * Retrieve the account the API key belongs to, with the coins it accepts in the key's mode.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(): Account
    {
        return self::account($this->respond('GET', '/v1/account'));
    }

    /**
     * Change the account's name, public profile, checkout and invoice settings or business details, in the shape retrieve() answers with; null clears a field. Needs account:write.
     *
     * @param  array{name?: string, display_name?: string|null, website_url?: string|null, support_email?: string|null, support_phone?: string|null, terms_url?: string|null, refund_policy_url?: string|null, brand_color?: string|null, checkout?: array{appearance?: CheckoutAppearance|string, coin_order?: list<Asset|string>, default_coin?: Asset|string|null, success_url?: string|null, cancel_url?: string|null, success_message?: string|null, hide_powered_by?: bool}, invoices?: array{number_prefix?: string|null, footer?: string|null, show_business_details?: bool}, business?: array{type?: BusinessType|string|null, legal_name?: string|null, registration_number?: string|null, tax_id?: string|null, address?: array{line1?: string|null, line2?: string|null, city?: string|null, postal_code?: string|null, state?: string|null, country?: string|null}, industry?: Industry|string|null, product_description?: string|null, monthly_volume?: MonthlyVolume|string|null, phone?: string|null}}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(array $params, ?string $idempotencyKey = null): Account
    {
        return self::account($this->respond('PATCH', '/v1/account', $params, $idempotencyKey));
    }

    /**
     * Change the default currency, the payment tolerances, the checkout language or invoice reminders. Needs payment_settings:write.
     *
     * @param  array{default_currency?: Currency|string, underpayment_tolerance?: string|int|float, overpayment_threshold?: string|int|float, checkout_locale?: CheckoutLocale|string|null, invoice_reminders?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function updatePaymentSettings(array $params, ?string $idempotencyKey = null): Account
    {
        return self::account($this->respond('PATCH', '/v1/payment-settings', self::decimals($params, 'underpayment_tolerance', 'overpayment_threshold'), $idempotencyKey));
    }

    /**
     * Read the account and its meta from an answer.
     */
    private static function account(ApiResponse $response): Account
    {
        return Account::fromArray(self::data($response), self::meta($response));
    }
}
