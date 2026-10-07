<?php

declare(strict_types=1);

namespace VeliraPay;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Http\Discovery\Exception\NotFoundException as DiscoveryNotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;
use VeliraPay\Enums\Mode;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Http\ApiResponse;
use VeliraPay\Http\HttpTransport;
use VeliraPay\Services\AccountService;
use VeliraPay\Services\ChargeService;
use VeliraPay\Services\EventService;
use VeliraPay\Services\InvoiceService;
use VeliraPay\Services\PaymentLinkService;

/**
 * A client for the VeliraPay API, authenticated with one API key.
 */
final class VeliraPayClient
{
    /**
     * The version of this library.
     */
    public const VERSION = '0.2.0';

    /**
     * The address of the VeliraPay API.
     */
    public const DEFAULT_BASE_URL = 'https://api.velirapay.com';

    /**
     * The account the API key belongs to.
     */
    public readonly AccountService $account;

    /**
     * Charges: single payments in one coin.
     */
    public readonly ChargeService $charges;

    /**
     * Payment links: reusable checkout pages.
     */
    public readonly PaymentLinkService $paymentLinks;

    /**
     * Invoices: itemised bills customers pay in the coin of their choice.
     */
    public readonly InvoiceService $invoices;

    /**
     * Events: the history of every charge and invoice.
     */
    public readonly EventService $events;

    /**
     * Sends the requests.
     */
    private readonly HttpTransport $transport;

    /**
     * Create a new client.
     *
     * @param  string  $apiKey  A secret key from the dashboard, starting with "vp_live_" or "vp_test_".
     * @param  ClientInterface|null  $httpClient  Any PSR-18 client; Guzzle or a discovered client when null.
     * @param  int  $maxRetries  How many times a request is tried again after a network error, a rate limit, an outage, or while one with the same idempotency key is still being processed.
     * @param  float  $timeout  How many seconds a request may take, when this library creates the HTTP client.
     * @param  string|null  $appInfo  Your application's name and version, added to the User-Agent.
     * @param  int  $maxRetryAfter  The longest Retry-After, in seconds, that is waited out before trying again; a longer one is thrown straight away.
     */
    public function __construct(
        #[SensitiveParameter] private readonly string $apiKey,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $maxRetries = 2,
        float $timeout = 30.0,
        ?string $appInfo = null,
        int $maxRetryAfter = 10,
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('A VeliraPay API key is required. Create one in the dashboard under Developers > API keys.');
        }

        if ($this->mode() === null) {
            throw new InvalidArgumentException('A VeliraPay API key starts with "vp_live_" or "vp_test_". Copy yours from the dashboard under Developers > API keys.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('The number of retries cannot be negative.');
        }

        if ($maxRetryAfter < 0) {
            throw new InvalidArgumentException('The longest Retry-After to wait out cannot be negative.');
        }

        $userAgent = 'VeliraPay-PHP/'.self::VERSION.' PHP/'.PHP_VERSION;

        if ($appInfo !== null && trim($appInfo) !== '') {
            $userAgent .= ' '.trim($appInfo);
        }

        try {
            $httpClient ??= self::defaultHttpClient($timeout);
            $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryNotFoundException $exception) {
            throw new InvalidArgumentException('No PSR-18 HTTP client was found. Install one with "composer require guzzlehttp/guzzle", or pass your own to the VeliraPayClient.', 0, $exception);
        }

        $this->transport = new HttpTransport(
            apiKey: $apiKey,
            client: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            baseUrl: $baseUrl,
            maxRetries: $maxRetries,
            userAgent: $userAgent,
            maxRetryAfter: $maxRetryAfter,
        );

        $this->account = new AccountService($this->transport);
        $this->charges = new ChargeService($this->transport);
        $this->paymentLinks = new PaymentLinkService($this->transport);
        $this->invoices = new InvoiceService($this->transport);
        $this->events = new EventService($this->transport);
    }

    /**
     * Get the account endpoints.
     */
    public function account(): AccountService
    {
        return $this->account;
    }

    /**
     * Get the charge endpoints.
     */
    public function charges(): ChargeService
    {
        return $this->charges;
    }

    /**
     * Get the payment link endpoints.
     */
    public function paymentLinks(): PaymentLinkService
    {
        return $this->paymentLinks;
    }

    /**
     * Get the invoice endpoints.
     */
    public function invoices(): InvoiceService
    {
        return $this->invoices;
    }

    /**
     * Get the event endpoints.
     */
    public function events(): EventService
    {
        return $this->events;
    }

    /**
     * Get the mode the API key works in, or null when its prefix is not recognised.
     */
    public function mode(): ?Mode
    {
        foreach (Mode::cases() as $mode) {
            if (str_starts_with($this->apiKey, "vp_{$mode->value}_")) {
                return $mode;
            }
        }

        return null;
    }

    /**
     * Determine whether the API key moves real funds.
     */
    public function isLiveMode(): bool
    {
        return $this->mode() === Mode::Live;
    }

    /**
     * Determine whether the API key works on test networks.
     */
    public function isTestMode(): bool
    {
        return $this->mode() === Mode::Test;
    }

    /**
     * Get the response to the most recent request, such as to read its headers.
     */
    public function lastResponse(): ?ApiResponse
    {
        return $this->transport->lastResponse();
    }

    /**
     * Send a request to an endpoint this library has no method for yet.
     *
     * @param  array<string, mixed>  $params  The query string for GET requests, the JSON body otherwise.
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    public function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): ApiResponse
    {
        return strtoupper($method) === 'GET'
            ? $this->transport->request('GET', $path, $params)
            : $this->transport->request($method, $path, [], $params === [] ? null : $params, $idempotencyKey);
    }

    /**
     * Create the HTTP client used when none is given.
     */
    private static function defaultHttpClient(float $timeout): ClientInterface
    {
        if (class_exists(GuzzleClient::class)) {
            return new GuzzleClient([
                RequestOptions::TIMEOUT => $timeout,
                RequestOptions::CONNECT_TIMEOUT => min(10.0, $timeout),
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
