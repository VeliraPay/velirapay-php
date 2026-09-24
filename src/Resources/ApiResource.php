<?php

declare(strict_types=1);

namespace VeliraPay\Resources;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonSerializable;

/**
 * An object returned by the API, keeping every attribute it was sent with.
 */
abstract class ApiResource implements JsonSerializable
{
    /**
     * Create a new resource.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private readonly array $attributes)
    {
        //
    }

    /**
     * Get the attributes as the API sent them.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Get one attribute as the API sent it, including any this library does not know yet.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    /**
     * Get the attributes for JSON encoding.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    /**
     * Read a string attribute.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function string(array $data, string $key, string $default = ''): string
    {
        return self::nullableString($data, $key) ?? $default;
    }

    /**
     * Read a string attribute that may be null.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * Read a boolean attribute.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function bool(array $data, string $key, bool $default = false): bool
    {
        return self::nullableBool($data, $key) ?? $default;
    }

    /**
     * Read a boolean attribute that may be null.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function nullableBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * Read an integer attribute.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function int(array $data, string $key, int $default = 0): int
    {
        return self::nullableInt($data, $key) ?? $default;
    }

    /**
     * Read an integer attribute that may be null.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /**
     * Read a timestamp or date attribute.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function date(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

            return $date === false ? null : $date;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Read a list of strings.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    protected static function strings(array $data, string $key): array
    {
        return self::nullableStrings($data, $key) ?? [];
    }

    /**
     * Read a list of strings that may be null.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>|null
     */
    protected static function nullableStrings(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return null;
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item) || is_int($item) || is_float($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }

    /**
     * Read an object attribute as an array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? self::keyed($value) : [];
    }

    /**
     * Read a list of objects.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected static function records(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        $records = [];

        foreach (is_array($value) ? $value : [] as $item) {
            if (is_array($item)) {
                $records[] = self::keyed($item);
            }
        }

        return $records;
    }

    /**
     * Read a list of objects into resources.
     *
     * @template TResource
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(array<string, mixed>): TResource  $hydrate
     * @return list<TResource>
     */
    protected static function objects(array $data, string $key, Closure $hydrate): array
    {
        return array_map($hydrate, self::records($data, $key));
    }

    /**
     * Read an object attribute into a resource.
     *
     * @template TResource
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(array<string, mixed>): TResource  $hydrate
     * @return TResource|null
     */
    protected static function object(array $data, string $key, Closure $hydrate): mixed
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $hydrate(self::keyed($value)) : null;
    }

    /**
     * Read the code of a related object, sent either as the code itself or as an object carrying it.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function reference(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_array($value)) {
            $value = $value['id'] ?? $value['code'] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Give an array string keys.
     *
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    protected static function keyed(array $value): array
    {
        $keyed = [];

        foreach ($value as $key => $item) {
            $keyed[(string) $key] = $item;
        }

        return $keyed;
    }
}
