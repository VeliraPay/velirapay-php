<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use DateTimeInterface;
use Generator;
use VeliraPay\Enums\InvoiceStatus;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Invoice;

/**
 * The /v1/invoices endpoints.
 */
final class InvoiceService extends Service
{
    /**
     * Create an invoice, for a total or a list of items, and optionally email it to the customer.
     *
     * @param  array{customer_name: string, customer_email: string, currency: string, amount?: numeric-string|int|float, items?: list<array{description: string, quantity: numeric-string|int|float, unit_amount: numeric-string|int|float}>, memo?: string|null, due_at?: DateTimeInterface|string|null, send_email?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): Invoice
    {
        if (($params['due_at'] ?? null) instanceof DateTimeInterface) {
            $params['due_at'] = $params['due_at']->format('Y-m-d');
        }

        return Invoice::fromArray($this->request('POST', '/v1/invoices', $params, $idempotencyKey));
    }

    /**
     * Retrieve an invoice by its id.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): Invoice
    {
        return Invoice::fromArray($this->request('GET', self::path('/v1/invoices/%s', $id)));
    }

    /**
     * List invoices, newest first.
     *
     * @param  array{status?: InvoiceStatus|string, page?: int, per_page?: int}  $params
     * @return Page<Invoice>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/invoices', $params, Invoice::fromArray(...));
    }

    /**
     * Iterate over every invoice, newest first, fetching pages as they are reached.
     *
     * @param  array{status?: InvoiceStatus|string, per_page?: int}  $params
     * @return Generator<int, Invoice>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Email an open invoice to its customer, as a reminder when it was sent before.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function send(string $id, ?string $idempotencyKey = null): Invoice
    {
        return Invoice::fromArray($this->request('POST', self::path('/v1/invoices/%s/send', $id), null, $idempotencyKey));
    }

    /**
     * Void an open invoice so it can no longer be paid.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function void(string $id, ?string $idempotencyKey = null): Invoice
    {
        return Invoice::fromArray($this->request('POST', self::path('/v1/invoices/%s/void', $id), null, $idempotencyKey));
    }
}
