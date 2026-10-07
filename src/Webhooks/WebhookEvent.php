<?php

declare(strict_types=1);

namespace VeliraPay\Webhooks;

use DateTimeImmutable;
use JsonException;
use VeliraPay\Enums\EventType;
use VeliraPay\Enums\Mode;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Resources\ApiResource;
use VeliraPay\Resources\Charge;
use VeliraPay\Resources\Invoice;
use VeliraPay\Resources\Transaction;

/**
 * A webhook delivery's payload.
 */
final class WebhookEvent extends ApiResource
{
    /**
     * Create a new webhook event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        array $attributes,
        /** The delivery's id, also sent as the X-VeliraPay-Delivery header; retries keep it, but every endpoint's copy of an event has its own. */
        public readonly string $id,
        /** The event's id in the events API, the same on every endpoint's copy, so the one to skip duplicates by; null for test deliveries. */
        public readonly ?string $eventId,
        /** One of the EventType values, such as "charge.paid". */
        public readonly string $type,
        /** Either "live" or "test". */
        public readonly string $mode,
        /** Whether this is a sample sent with the dashboard's "Send test event" button. */
        public readonly bool $test,
        /** When the delivery was created. */
        public readonly ?DateTimeImmutable $createdAt,
        /** The charge, for charge.* events. */
        public readonly ?Charge $charge,
        /** The invoice, for invoice.* events. */
        public readonly ?Invoice $invoice,
        /** The transfer the event is about, for charge.payment_detected and charge.late_payment. */
        public readonly ?Transaction $transaction = null,
    ) {
        parent::__construct($attributes);
    }

    /**
     * Create a webhook event from a delivery's raw body.
     *
     * @throws InvalidArgumentException
     */
    public static function fromPayload(string $payload): self
    {
        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The webhook payload is not valid JSON: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('The webhook payload is not a JSON object.');
        }

        return self::fromArray(self::keyed($data));
    }

    /**
     * Create a webhook event from a decoded payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $objects = self::map($data, 'data');

        return new self(
            attributes: $data,
            id: self::string($data, 'id'),
            eventId: self::nullableString($data, 'event_id'),
            type: self::string($data, 'event'),
            mode: self::string($data, 'mode'),
            test: self::bool($data, 'test'),
            createdAt: self::date($data, 'created_at'),
            charge: self::object($objects, 'charge', Charge::fromArray(...)),
            invoice: self::object($objects, 'invoice', Invoice::fromArray(...)),
            transaction: self::object($objects, 'transaction', Transaction::fromArray(...)),
        );
    }

    /**
     * Determine whether the event is of any of the given types.
     */
    public function is(EventType|string ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->type === ($type instanceof EventType ? $type->value : $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the event concerns real funds.
     */
    public function isLiveMode(): bool
    {
        return $this->mode === Mode::Live->value;
    }
}
