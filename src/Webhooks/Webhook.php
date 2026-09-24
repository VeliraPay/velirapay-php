<?php

declare(strict_types=1);

namespace VeliraPay\Webhooks;

use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Exceptions\SignatureVerificationException;

/**
 * Verifies and parses the webhooks VeliraPay sends.
 */
final class Webhook
{
    /**
     * The header carrying the delivery's signature.
     */
    public const SIGNATURE_HEADER = 'X-VeliraPay-Signature';

    /**
     * The header carrying the event type.
     */
    public const EVENT_HEADER = 'X-VeliraPay-Event';

    /**
     * The header carrying the delivery's id.
     */
    public const DELIVERY_HEADER = 'X-VeliraPay-Delivery';

    /**
     * How many seconds a signature stays valid.
     */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verify a delivery's signature and parse its payload.
     *
     * @param  string  $payload  The raw request body, exactly as received.
     * @param  string|null  $signatureHeader  The X-VeliraPay-Signature header.
     * @param  string  $secret  The endpoint's signing secret, starting with "whsec_".
     * @param  int  $tolerance  How many seconds old a signature may be; 0 accepts any age.
     *
     * @throws SignatureVerificationException
     * @throws InvalidArgumentException
     */
    public static function constructEvent(string $payload, ?string $signatureHeader, #[SensitiveParameter] string $secret, int $tolerance = self::DEFAULT_TOLERANCE): WebhookEvent
    {
        self::verifySignature($payload, $signatureHeader, $secret, $tolerance);

        return WebhookEvent::fromPayload($payload);
    }

    /**
     * Verify and parse the delivery in a PSR-7 request.
     *
     * @throws SignatureVerificationException
     * @throws InvalidArgumentException
     */
    public static function constructEventFromRequest(ServerRequestInterface $request, #[SensitiveParameter] string $secret, int $tolerance = self::DEFAULT_TOLERANCE): WebhookEvent
    {
        return self::constructEvent((string) $request->getBody(), $request->getHeaderLine(self::SIGNATURE_HEADER), $secret, $tolerance);
    }

    /**
     * Check that a payload was signed with the secret, recently.
     *
     * @throws SignatureVerificationException
     * @throws InvalidArgumentException
     */
    public static function verifySignature(string $payload, ?string $signatureHeader, #[SensitiveParameter] string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): void
    {
        if (trim($secret) === '') {
            throw new InvalidArgumentException('A webhook signing secret is required. Copy it from the webhook endpoint in the dashboard.');
        }

        if ($signatureHeader === null || trim($signatureHeader) === '') {
            throw new SignatureVerificationException('The '.self::SIGNATURE_HEADER.' header is missing.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && $timestamp === null) {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp)) {
            throw new SignatureVerificationException('The '.self::SIGNATURE_HEADER.' header has no timestamp.');
        }

        if ($signatures === []) {
            throw new SignatureVerificationException('The '.self::SIGNATURE_HEADER.' header has no v1 signature.');
        }

        $age = abs(($now ?? time()) - (int) $timestamp);

        if ($tolerance > 0 && $age > $tolerance) {
            throw new SignatureVerificationException("The signature is {$age} seconds away from now, more than the {$tolerance} allowed.");
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new SignatureVerificationException('The signature does not match the payload. Check the signing secret and that the raw request body is passed unchanged.');
    }

    /**
     * Build the signature header VeliraPay would send with a payload, to test a webhook handler.
     */
    public static function signatureHeader(string $payload, #[SensitiveParameter] string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }
}
