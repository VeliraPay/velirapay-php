<?php

declare(strict_types=1);

namespace VeliraPay\Services;

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
     * @param  array<string, mixed>  $params
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
     * @param  array{default_currency?: string, underpayment_tolerance?: string|int|float, overpayment_threshold?: string|int|float, checkout_locale?: string|null, invoice_reminders?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function updatePaymentSettings(array $params, ?string $idempotencyKey = null): Account
    {
        return self::account($this->respond('PATCH', '/v1/payment-settings', $params, $idempotencyKey));
    }

    /**
     * Read the account and its meta from an answer.
     */
    private static function account(ApiResponse $response): Account
    {
        return Account::fromArray(self::data($response), self::meta($response));
    }
}
