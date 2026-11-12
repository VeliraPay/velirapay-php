<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use VeliraPay\Enums\Currency;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\SupportedAsset;

/**
 * The /v1/assets endpoint.
 */
final class AssetService extends Service
{
    /**
     * List the coins that can be paid in the API key's mode, with the lowest price each can be charged at.
     *
     * @param  array{currency?: Currency|string}  $params  The currency of the prices; the account's default when left out.
     * @return Page<SupportedAsset>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/assets', $params, SupportedAsset::fromArray(...));
    }
}
