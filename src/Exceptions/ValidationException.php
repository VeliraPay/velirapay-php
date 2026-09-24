<?php

declare(strict_types=1);

namespace VeliraPay\Exceptions;

/**
 * The request failed validation (422).
 */
class ValidationException extends ApiException
{
    /**
     * Get the validation messages keyed by field, such as "amount" or "items.0.quantity".
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = [];

        foreach (is_array($this->body['errors'] ?? null) ? $this->body['errors'] : [] as $field => $messages) {
            $errors[(string) $field] = array_values(array_filter((array) $messages, 'is_string'));
        }

        return $errors;
    }

    /**
     * Get the first validation message for a field.
     */
    public function firstError(string $field): ?string
    {
        return $this->errors()[$field][0] ?? null;
    }
}
