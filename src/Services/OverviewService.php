<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Resources\Overview;

/**
 * The /v1/overview endpoint. Needs overview:read.
 */
final class OverviewService extends Service
{
    /**
     * Retrieve the dashboard's figures for the last 30 days, in the API key's mode.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(): Overview
    {
        $response = $this->respond('GET', '/v1/overview');

        return Overview::fromArray(self::data($response), self::meta($response));
    }
}
