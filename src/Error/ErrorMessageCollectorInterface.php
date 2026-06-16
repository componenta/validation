<?php

namespace Componenta\Validation\Error;

/**
 * Collection of validation error messages.
 *
 * Stores validation errors indexed by field paths.
 * Supports nested structure for complex objects and arrays.
 */
interface ErrorMessageCollectorInterface extends \IteratorAggregate, \Countable
{
    /**
     * All error messages in the collection.
     *
     * Keys represent field paths, values are either individual error messages
     * or nested collectors for complex structures.
     *
     * @var iterable<string|int, ErrorMessageInterface|ErrorMessageCollectorInterface>
     */
    public iterable $messages { get; }

    /**
     * Check if error exists for given field path.
     *
     * @param string|int $key Field path or array index
     * @return bool True if error exists
     */
    public function has(string|int $key): bool;

    /**
     * Get error message or nested collector for field path.
     *
     * @param string|int $key Field path or array index
     * @return ErrorMessageInterface|ErrorMessageCollectorInterface Error message or nested collector
     */
    public function get(string|int $key): ErrorMessageInterface|ErrorMessageCollectorInterface;

    /**
     * Convert all errors to nested array structure.
     *
     * @return array<string, mixed> Nested array of error messages
     */
    public function toArray(): array;
}
