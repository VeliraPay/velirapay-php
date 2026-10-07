<?php

declare(strict_types=1);

namespace VeliraPay\Services;

use Closure;
use VeliraPay\Exceptions\ApiException;
use VeliraPay\Exceptions\ConnectionException;
use VeliraPay\Exceptions\InvalidArgumentException;
use VeliraPay\Exceptions\UnexpectedResponseException;
use VeliraPay\Http\ApiResponse;
use VeliraPay\Http\HttpTransport;
use VeliraPay\Page;
use VeliraPay\Resources\ApiResource;

/**
 * The requests shared by every endpoint group.
 */
abstract class Service
{
    /**
     * Create a new service.
     */
    public function __construct(protected readonly HttpTransport $transport)
    {
        //
    }

    /**
     * Send a request and return the object under the response's "data" key.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    protected function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        return self::data($this->transport->request($method, $path, [], $body, $idempotencyKey));
    }

    /**
     * Read the object under a response's "data" key.
     *
     * @return array<string, mixed>
     *
     * @throws UnexpectedResponseException
     */
    protected static function data(ApiResponse $response): array
    {
        $data = $response->json()['data'] ?? null;

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new UnexpectedResponseException('The VeliraPay API answered without the object it was asked for.', $response);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Fetch one page of a list.
     *
     * @template TResource of ApiResource
     *
     * @param  array<string, mixed>  $params
     * @param  Closure(array<string, mixed>): TResource  $hydrate
     * @return Page<TResource>
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    protected function page(string $path, array $params, Closure $hydrate): Page
    {
        $response = $this->transport->request('GET', $path, $params);
        $json = $response->json();

        if (! is_array($json['data'] ?? null)) {
            throw new UnexpectedResponseException('The VeliraPay API answered without the list it was asked for.', $response);
        }

        $data = [];

        foreach ($json['data'] as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $data[] = $hydrate($item);
            }
        }

        $meta = is_array($json['meta'] ?? null) ? $json['meta'] : [];
        $currentPage = self::integer($meta['current_page'] ?? null, 1);

        return new Page(
            data: $data,
            currentPage: $currentPage,
            lastPage: self::integer($meta['last_page'] ?? null, $currentPage),
            perPage: self::integer($meta['per_page'] ?? null, count($data)),
            total: self::integer($meta['total'] ?? null, count($data)),
            fetch: fn (int $page): Page => $this->page($path, [...$params, 'page' => $page], $hydrate),
        );
    }

    /**
     * Build a path with the given ids escaped into it.
     *
     * @throws InvalidArgumentException
     */
    protected static function path(string $format, string ...$ids): string
    {
        foreach ($ids as $id) {
            if (trim($id) === '') {
                throw new InvalidArgumentException('The id in '.str_replace('%s', '{id}', $format).' cannot be empty.');
            }
        }

        return vsprintf($format, array_map(rawurlencode(...), $ids));
    }

    /**
     * Read an integer from list metadata.
     */
    private static function integer(mixed $value, int $default): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : $default;
    }
}
