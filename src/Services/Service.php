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
     * Send a request and return the whole response, for answers whose meta matters too.
     *
     * @param  array<string, mixed>|null  $body
     *
     * @throws ApiException
     * @throws ConnectionException
     */
    protected function respond(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->transport->request($method, $path, [], $body, $idempotencyKey);
    }

    /**
     * Read what is under a response's "meta" key.
     *
     * @return array<string, mixed>
     */
    protected static function meta(ApiResponse $response): array
    {
        return $response->meta();
    }

    /**
     * Read the list under a response's "data" key that is not paged.
     *
     * @template TResource of ApiResource
     *
     * @param  Closure(array<string, mixed>): TResource  $hydrate
     * @return list<TResource>
     *
     * @throws UnexpectedResponseException
     */
    protected static function items(ApiResponse $response, Closure $hydrate): array
    {
        $data = $response->json()['data'] ?? null;

        if (! is_array($data)) {
            throw new UnexpectedResponseException('The VeliraPay API answered without the list it was asked for.', $response);
        }

        $items = [];

        foreach ($data as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $items[] = $hydrate($item);
            }
        }

        return $items;
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

        $meta = self::meta($response);
        $currentPage = self::integer($meta['current_page'] ?? null, 1);

        return new Page(
            data: $data,
            currentPage: $currentPage,
            lastPage: self::integer($meta['last_page'] ?? null, $currentPage),
            perPage: self::integer($meta['per_page'] ?? null, count($data)),
            total: self::integer($meta['total'] ?? null, count($data)),
            fetch: fn (int $page): Page => $this->page($path, [...$params, 'page' => $page], $hydrate),
            meta: $meta,
        );
    }

    /**
     * Write the floats among the given parameters, and in the lists and objects under them, as plain decimal strings, since the API refuses an amount with an exponent, such as 5.0E-5.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected static function decimals(array $params, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $params)) {
                $params[$key] = self::decimal($params[$key]);
            }
        }

        return $params;
    }

    /**
     * Write a float, or every float in a list or object, as a plain decimal string of the shortest digits that read back as the same number.
     *
     * @throws InvalidArgumentException
     */
    private static function decimal(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::decimal(...), $value);
        }

        if (! is_float($value)) {
            return $value;
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException('An amount must be a finite number.');
        }

        $written = strtolower((string) json_encode($value));

        if (! str_contains($written, 'e')) {
            return $written;
        }

        [$mantissa, $exponent] = explode('e', $written, 2);
        $sign = str_starts_with($mantissa, '-') ? '-' : '';
        $mantissa = ltrim($mantissa, '-');
        $digits = str_replace('.', '', $mantissa);
        $point = (strpos($mantissa, '.') ?: strlen($mantissa)) + (int) $exponent;

        if ($point <= 0) {
            return $sign.'0.'.str_repeat('0', -$point).rtrim($digits, '0');
        }

        $fraction = rtrim(substr($digits, $point), '0');

        return $sign.str_pad(substr($digits, 0, $point), $point, '0').($fraction === '' ? '' : '.'.$fraction);
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
