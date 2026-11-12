<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Generator;
use VeliraPay\Enums\EventType;
use VeliraPay\Enums\WebhookDeliveryStatus;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Http\ApiResponse;
use VeliraPay\Page;
use VeliraPay\Resources\WebhookDelivery;
use VeliraPay\Resources\WebhookEndpoint;

/**
 * The /v1/webhook-endpoints endpoints. Changing them needs webhook_endpoints:write.
 */
final class WebhookEndpointService extends Service
{
    /**
     * List the account's webhook endpoints in the API key's mode, newest first.
     *
     * @param  array{page?: int, per_page?: int}  $params
     * @return Page<WebhookEndpoint>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function list(array $params = []): Page
    {
        return $this->page('/v1/webhook-endpoints', $params, WebhookEndpoint::fromArray(...));
    }

    /**
     * Iterate over every webhook endpoint, newest first, fetching pages as they are reached.
     *
     * @param  array{per_page?: int}  $params
     * @return Generator<int, WebhookEndpoint>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function iterator(array $params = []): Generator
    {
        yield from $this->list($params)->autoPagingIterator();
    }

    /**
     * Retrieve one webhook endpoint.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieve(string $id): WebhookEndpoint
    {
        return WebhookEndpoint::fromArray($this->request('GET', self::path('/v1/webhook-endpoints/%s', $id)));
    }

    /**
     * Add an endpoint and send it its verification challenge, which counts against the 10 requests a minute that verifying, testing and retrying share. The answer carries its signing secret, shown this once, and verificationError while it has not answered.
     * A ConflictException whose wasReplayed() is true means the endpoint was added, by this request retried after its answer was lost or by an earlier one with the same idempotency key, but its secret is gone: list the endpoints and rotate that one's secret.
     *
     * @param  array{url: string, events: list<EventType|string>, description?: string|null, active?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function create(array $params, ?string $idempotencyKey = null): WebhookEndpoint
    {
        return self::endpoint($this->respond('POST', '/v1/webhook-endpoints', $params, $idempotencyKey));
    }

    /**
     * Change an endpoint's url, events, description or active, only those given. A new url is verified again, which counts against the same 10 requests a minute, with verificationError while it has not answered.
     *
     * @param  array{url?: string, events?: list<EventType|string>, description?: string|null, active?: bool}  $params
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function update(string $id, array $params, ?string $idempotencyKey = null): WebhookEndpoint
    {
        return self::endpoint($this->respond('PATCH', self::path('/v1/webhook-endpoints/%s', $id), $params, $idempotencyKey));
    }

    /**
     * Delete an endpoint and its delivery history.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $this->transport->request('DELETE', self::path('/v1/webhook-endpoints/%s', $id), [], null, $idempotencyKey);
    }

    /**
     * Send the endpoint its verification challenge again. One that does not answer throws a ValidationException saying what it answered instead.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function verify(string $id, ?string $idempotencyKey = null): WebhookEndpoint
    {
        return WebhookEndpoint::fromArray($this->request('POST', self::path('/v1/webhook-endpoints/%s/verify', $id), null, $idempotencyKey));
    }

    /**
     * Queue a sample charge.paid event, marked as a test, to a verified endpoint.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function test(string $id, ?string $idempotencyKey = null): WebhookDelivery
    {
        return WebhookDelivery::fromArray($this->request('POST', self::path('/v1/webhook-endpoints/%s/test', $id), null, $idempotencyKey));
    }

    /**
     * Replace the endpoint's signing secret. The answer carries the new one, shown this once; the old one keeps signing for a day, until previousSecretExpiresAt.
     * A ConflictException whose wasReplayed() is true means the secret was replaced, by this request retried after its answer was lost or by an earlier one with the same idempotency key, but the new one is gone: rotate it again, with a new idempotency key.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function rotateSecret(string $id, ?string $idempotencyKey = null): WebhookEndpoint
    {
        return self::endpoint($this->respond('POST', self::path('/v1/webhook-endpoints/%s/secret', $id), null, $idempotencyKey));
    }

    /**
     * List what the endpoint was sent, newest first, kept for 30 days.
     *
     * @param  array{status?: WebhookDeliveryStatus|string, event?: EventType|string, page?: int, per_page?: int}  $params
     * @return Page<WebhookDelivery>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function deliveries(string $id, array $params = []): Page
    {
        return $this->page(self::path('/v1/webhook-endpoints/%s/deliveries', $id), $params, WebhookDelivery::fromArray(...));
    }

    /**
     * Retrieve one of the endpoint's deliveries with every attempt to send it, the latest first, and the JSON body it carried when the key may also read events.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retrieveDelivery(string $id, string $deliveryId): WebhookDelivery
    {
        return WebhookDelivery::fromArray($this->request('GET', self::path('/v1/webhook-endpoints/%s/deliveries/%s', $id, $deliveryId)));
    }

    /**
     * Send one of the endpoint's deliveries again, once the endpoint is verified and not paused.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function retryDelivery(string $id, string $deliveryId, ?string $idempotencyKey = null): WebhookDelivery
    {
        return WebhookDelivery::fromArray($this->request('POST', self::path('/v1/webhook-endpoints/%s/deliveries/%s/retry', $id, $deliveryId), null, $idempotencyKey));
    }

    /**
     * Read the endpoint and its meta from an answer.
     */
    private static function endpoint(ApiResponse $response): WebhookEndpoint
    {
        return WebhookEndpoint::fromArray(self::data($response), self::meta($response));
    }
}
