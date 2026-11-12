<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use VeliraPay\Enums\Asset;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Resources\ProPayment;

/**
 * The /v1/billing endpoints. Needs billing:write and a live key.
 */
final class BillingService extends Service
{
    /**
     * Start paying for a month of Pro in a coin, answering with the checkout to pay it on, or with the payment already waiting in that coin.
     *
     * @param  array{asset: Asset|string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function startProPayment(array $params, ?string $idempotencyKey = null): ProPayment
    {
        $response = $this->respond('POST', '/v1/billing/pro', $params, $idempotencyKey);

        return ProPayment::fromArray(self::data($response), self::meta($response));
    }
}
