<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use DateTimeInterface;
use Generator;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\PricingType;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\PaymentLink;

/**
 * The /v1/payment-links endpoints.
 */
final class PaymentLinkService extends Service
{
    /**
     * Create a payment link.
     *
     * @param  array{title: string, pricing_type: PricingType|string, currency: string, amount?: numeric-string|int|float|null, description?: string|null, suggested_amounts?: list<numeric-string|int|float>|null, min_amount?: numeric-string|int|float|null, max_amount?: numeric-string|int|float|null, accepted_assets?: list<Asset|string>|null, success_url?: string|null, cancel_url?: string|null, collect_name?: bool, require_email?: bool, success_message?: string|null, custom_fields?: list<array{label: string, required: bool}>, expires_at?: DateTimeInterface|string|null, max_payments?: int|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): PaymentLink
    {
        return PaymentLink::fromArray($this->request('POST', '/v1/payment-links', $params, $idempotencyKey));
    }

    /**
     * Retrieve a payment link by its id.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): PaymentLink
    {
        return PaymentLink::fromArray($this->request('GET', self::path('/v1/payment-links/%s', $id)));
    }

    /**
     * Change any of a payment link's fields.
     *
     * @param  array{title?: string, pricing_type?: PricingType|string, currency?: string, amount?: numeric-string|int|float|null, description?: string|null, suggested_amounts?: list<numeric-string|int|float>|null, min_amount?: numeric-string|int|float|null, max_amount?: numeric-string|int|float|null, accepted_assets?: list<Asset|string>|null, success_url?: string|null, cancel_url?: string|null, collect_name?: bool, require_email?: bool, success_message?: string|null, custom_fields?: list<array{label: string, required: bool}>, expires_at?: DateTimeInterface|string|null, max_payments?: int|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(string $id, array $params, ?string $idempotencyKey = null): PaymentLink
    {
        return PaymentLink::fromArray($this->request('PATCH', self::path('/v1/payment-links/%s', $id), $params, $idempotencyKey));
    }

    /**
     * List payment links, newest first.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<PaymentLink>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/payment-links', $params, PaymentLink::fromArray(...));
    }

    /**
     * Iterate over every payment link, newest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, PaymentLink>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Archive a payment link so it stops taking payments.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function archive(string $id, ?string $idempotencyKey = null): PaymentLink
    {
        return PaymentLink::fromArray($this->request('POST', self::path('/v1/payment-links/%s/archive', $id), null, $idempotencyKey));
    }

    /**
     * Restore an archived payment link.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function restore(string $id, ?string $idempotencyKey = null): PaymentLink
    {
        return PaymentLink::fromArray($this->request('DELETE', self::path('/v1/payment-links/%s/archive', $id), null, $idempotencyKey));
    }
}
