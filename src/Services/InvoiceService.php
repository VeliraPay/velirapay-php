<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use DateTimeInterface;
use Generator;
use VeliraPay\Enums\Currency;
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
     * Create an invoice, for a total or a list of items, each optionally taxed at its own tax_rate, with a discount_percent or a discount_amount taken off the items before tax, addressed to the customer by customer_name, customer_email or both, and optionally email it to them, which needs customer_email. A DateTimeInterface given as due_at is sent as the day it falls on where it is, and one given as expires_at as the same moment in UTC; a YYYY-MM-DD date as expires_at closes the invoice at the end of that day.
     *
     * @param  array{customer_name?: string|null, customer_email?: string|null, currency: Currency|string, amount?: numeric-string|int|float, items?: list<array{description: string, quantity: numeric-string|int|float, unit_amount: numeric-string|int|float, tax_rate?: numeric-string|int|float|null}>, discount_percent?: numeric-string|int|float|null, discount_amount?: numeric-string|int|float|null, memo?: string|null, due_at?: DateTimeInterface|string|null, expires_at?: DateTimeInterface|string|null, success_url?: string|null, cancel_url?: string|null, success_message?: string|null, send_email?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): Invoice
    {
        if (($params['due_at'] ?? null) instanceof DateTimeInterface) {
            $params['due_at'] = $params['due_at']->format('Y-m-d');
        }

        return Invoice::fromArray($this->request('POST', '/v1/invoices', self::decimals($params, 'amount', 'items', 'discount_percent', 'discount_amount'), $idempotencyKey));
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
     * @param  array{status?: InvoiceStatus|string, search?: string, overdue?: bool, page?: int, per_page?: int}  $params
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
     * @param  array{status?: InvoiceStatus|string, search?: string, overdue?: bool, per_page?: int}  $params
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
     * Email an open invoice to its customer, as a reminder when it was sent before. An invoice without a customer email is refused on customer_email, and one that is no longer open or has expired with a conflict.
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
