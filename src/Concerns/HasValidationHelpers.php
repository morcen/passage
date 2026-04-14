<?php

namespace Morcen\Passage\Concerns;

trait HasValidationHelpers
{
    /**
     * Required integer ID (>= 1).
     *
     * Example:
     *   'product_id' => $this->requiredId()
     *   // ['required', 'integer', 'min:1']
     */
    protected function requiredId(): array
    {
        return ['required', 'integer', 'min:1'];
    }

    /**
     * Required RFC-compliant email address.
     *
     * Example:
     *   'email' => $this->requiredEmail()
     *   // ['required', 'email:rfc']
     */
    protected function requiredEmail(): array
    {
        return ['required', 'email:rfc'];
    }

    /**
     * Required string with an optional max length.
     *
     * Example:
     *   'name' => $this->requiredString()       // ['required', 'string']
     *   'name' => $this->requiredString(255)     // ['required', 'string', 'max:255']
     */
    protected function requiredString(?int $max = null): array
    {
        $rules = ['required', 'string'];

        if ($max !== null) {
            $rules[] = "max:{$max}";
        }

        return $rules;
    }

    /**
     * Optional (nullable) string with an optional max length.
     *
     * Example:
     *   'note' => $this->optionalString()       // ['nullable', 'string']
     *   'note' => $this->optionalString(500)     // ['nullable', 'string', 'max:500']
     */
    protected function optionalString(?int $max = null): array
    {
        $rules = ['nullable', 'string'];

        if ($max !== null) {
            $rules[] = "max:{$max}";
        }

        return $rules;
    }

    /**
     * Required positive integer (>= 1).
     *
     * Example:
     *   'quantity' => $this->requiredPositiveInt()
     *   // ['required', 'integer', 'min:1']
     */
    protected function requiredPositiveInt(): array
    {
        return ['required', 'integer', 'min:1'];
    }

    /**
     * Required valid URL.
     *
     * Example:
     *   'callback_url' => $this->requiredUrl()
     *   // ['required', 'url']
     */
    protected function requiredUrl(): array
    {
        return ['required', 'url'];
    }

    /**
     * Required value from a set of allowed values.
     *
     * Example:
     *   'status' => $this->requiredIn('active', 'inactive')
     *   // ['required', 'in:active,inactive']
     */
    protected function requiredIn(string ...$values): array
    {
        return ['required', 'in:'.implode(',', $values)];
    }

    /**
     * Optional (nullable) value from a set of allowed values.
     *
     * Example:
     *   'priority' => $this->optionalIn('low', 'medium', 'high')
     *   // ['nullable', 'in:low,medium,high']
     */
    protected function optionalIn(string ...$values): array
    {
        return ['nullable', 'in:'.implode(',', $values)];
    }

    /**
     * Required numeric value (integer or float).
     *
     * Example:
     *   'amount' => $this->requiredNumeric()
     *   // ['required', 'numeric']
     */
    protected function requiredNumeric(): array
    {
        return ['required', 'numeric'];
    }
}
