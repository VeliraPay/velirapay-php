<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use DateTimeInterface;
use Generator;
use VeliraPay\Enums\Asset;
use VeliraPay\Enums\ChargeStatus;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Page;
use VeliraPay\Resources\Charge;
use VeliraPay\Resources\RefundRequest;

/**
 * The /v1/charges endpoints.
 */
final class ChargeService extends Service
{
    /**
     * Charge a customer: create a charge with what you already know about them, and send them to its checkout URL.
     *
     * @param  array{asset: Asset|string, amount?: numeric-string|int|float, currency?: string, payment_link?: string|null, customer?: array{email?: string|null, name?: string|null, ip_address?: string|null, user_agent?: string|null, reference?: string|null, phone?: string|null, country?: string|null, metadata?: array<string, scalar|null>|null}|null, customer_email?: string|null, coupon?: string|null, description?: string|null, metadata?: array<string, scalar|null>|null, custom_fields?: list<array{label: string, value: string}>|null, success_url?: string|null, cancel_url?: string|null, success_message?: string|null, send_email?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', '/v1/charges', self::decimals($params, 'amount'), $idempotencyKey));
    }

    /**
     * Retrieve a charge by its id.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): Charge
    {
        return Charge::fromArray($this->request('GET', self::path('/v1/charges/%s', $id)));
    }

    /**
     * List charges, newest first. A DateTimeInterface given as created_from or created_to is sent as the same moment in UTC.
     *
     * @param  array{status?: ChargeStatus|string, payment_link?: string, asset?: Asset|string, search?: string, created_from?: DateTimeInterface|string, created_to?: DateTimeInterface|string, page?: int, per_page?: int}  $params
     * @return Page<Charge>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/charges', $params, Charge::fromArray(...));
    }

    /**
     * Iterate over every charge, newest first, fetching pages as they are reached.
     *
     * @param  array{status?: ChargeStatus|string, payment_link?: string, asset?: Asset|string, search?: string, created_from?: DateTimeInterface|string, created_to?: DateTimeInterface|string, per_page?: int}  $params
     * @return Generator<int, Charge>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Cancel a pending charge so it can no longer be paid.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function cancel(string $id, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/cancel', $id), null, $idempotencyKey));
    }

    /**
     * Record a refund you sent the customer from your own wallet. An underpaid charge or a late payment is accepted first,
     * and the network surcharge is never refundable; the API answers 422 otherwise.
     *
     * @param  array{amount: numeric-string|int|float, txid?: string|null, reason?: string|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function recordRefund(string $id, array $params, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/refunds', $id), self::decimals($params, 'amount'), $idempotencyKey));
    }

    /**
     * List the refunds started for a charge, newest first.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<RefundRequest>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function refundRequests(string $id, array $params = []): Page
    {
        return $this->page(self::path('/v1/charges/%s/refund-requests', $id), $params, RefundRequest::fromArray(...));
    }

    /**
     * Retrieve one of a charge's refund requests.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieveRefundRequest(string $id, string $refundRequestId): RefundRequest
    {
        return RefundRequest::fromArray($this->request('GET', self::path('/v1/charges/%s/refund-requests/%s', $id, $refundRequestId)));
    }

    /**
     * Start a refund: set the amount aside and, without an address, email the customer a link to give one, which is the
     * request's addressUrl either way. You then send it from your own wallet, and it is recorded once the chain shows it.
     *
     * @param  array{amount: numeric-string|int|float, reason?: string|null, address?: string|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function startRefund(string $id, array $params, ?string $idempotencyKey = null): RefundRequest
    {
        return RefundRequest::fromArray($this->request('POST', self::path('/v1/charges/%s/refund-requests', $id), self::decimals($params, 'amount'), $idempotencyKey));
    }

    /**
     * Give a refund waiting for the customer's address the one they gave you some other way.
     *
     * @param  array{address: string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function setRefundAddress(string $id, string $refundRequestId, array $params, ?string $idempotencyKey = null): RefundRequest
    {
        return RefundRequest::fromArray($this->request('POST', self::path('/v1/charges/%s/refund-requests/%s/address', $id, $refundRequestId), $params, $idempotencyKey));
    }

    /**
     * Give a refund the hash of the transaction you sent it in; the chain is looked at straight away and the refund is
     * recorded once the transfer has its confirmations.
     *
     * @param  array{txid: string}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function markRefundSent(string $id, string $refundRequestId, array $params, ?string $idempotencyKey = null): RefundRequest
    {
        return RefundRequest::fromArray($this->request('POST', self::path('/v1/charges/%s/refund-requests/%s/sent', $id, $refundRequestId), $params, $idempotencyKey));
    }

    /**
     * Record a started refund as sent, on your word, for a transfer the chain is not seen to carry.
     *
     * @param  array{txid?: string|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function completeRefund(string $id, string $refundRequestId, array $params = [], ?string $idempotencyKey = null): RefundRequest
    {
        return RefundRequest::fromArray($this->request('POST', self::path('/v1/charges/%s/refund-requests/%s/complete', $id, $refundRequestId), $params === [] ? null : $params, $idempotencyKey));
    }

    /**
     * Email the customer the link to give a refund's address again, which then works for another 14 days.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function resendRefundLink(string $id, string $refundRequestId, ?string $idempotencyKey = null): RefundRequest
    {
        return RefundRequest::fromArray($this->request('POST', self::path('/v1/charges/%s/refund-requests/%s/resend', $id, $refundRequestId), null, $idempotencyKey));
    }

    /**
     * Stop a refund that has not been recorded, which frees what it set aside.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function cancelRefund(string $id, string $refundRequestId, ?string $idempotencyKey = null): RefundRequest
    {
        return RefundRequest::fromArray($this->request('POST', self::path('/v1/charges/%s/refund-requests/%s/cancel', $id, $refundRequestId), null, $idempotencyKey));
    }

    /**
     * Email the customer the payment page of a pending charge.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function send(string $id, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/send', $id), null, $idempotencyKey));
    }

    /**
     * Accept an underpaid charge, or a late payment on an expired or canceled one, as paid for what arrived and confirmed.
     * The API answers 409 for any other charge, and for an underpaid one while a payment to it is still confirming.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function accept(string $id, ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/accept', $id), null, $idempotencyKey));
    }

    /**
     * Pay a test-mode charge without coins, in full or by a chosen amount in the charge's coin.
     *
     * @param  array{amount?: numeric-string|int|float|null}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function simulate(string $id, array $params = [], ?string $idempotencyKey = null): Charge
    {
        return Charge::fromArray($this->request('POST', self::path('/v1/charges/%s/simulate', $id), $params === [] ? null : self::decimals($params, 'amount'), $idempotencyKey));
    }
}
