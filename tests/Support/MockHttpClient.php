<?php

declare(strict_types=1);

namespace VeliraPay\Tests\Support;

use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers with queued responses and records every request.
 */
final class MockHttpClient implements ClientInterface
{
    /**
     * The requests sent so far.
     *
     * @var list<RequestInterface>
     */
    public array $requests = [];

    /**
     * The responses and errors still to be returned, in order.
     *
     * @var list<ResponseInterface|ClientExceptionInterface>
     */
    private array $queue = [];

    /**
     * Queue responses or errors for the next requests.
     */
    public function queue(ResponseInterface|ClientExceptionInterface ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * Queue a JSON response.
     *
     * @param  array<array-key, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function json(array $body, int $status = 200, array $headers = []): self
    {
        return $this->queue(new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body, JSON_THROW_ON_ERROR)));
    }

    /**
     * Answer a request with the next queued response.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);

        if ($next === null) {
            throw new LogicException("No response was queued for {$request->getMethod()} {$request->getUri()}.");
        }

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    /**
     * Get the most recent request.
     */
    public function lastRequest(): RequestInterface
    {
        $request = end($this->requests);

        if ($request === false) {
            throw new LogicException('No request was sent.');
        }

        return $request;
    }
}
