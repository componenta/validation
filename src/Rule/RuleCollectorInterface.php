<?php

namespace Componenta\Validation\Rule;

use Componenta\Arrayable\Arrayable;

/**
 * Collection of validation rules indexed by field names.
 *
 * Stores and provides access to validation rules mapped to field names.
 * Used by validator to apply rules to corresponding fields.
 *
 * @template T of RuleInterface
 *
 * @extends \IteratorAggregate<string, T>
 * @extends \Countable
 * @extends Arrayable<string, T>
 */
interface RuleCollectorInterface extends \IteratorAggregate, \Countable, Arrayable
{
    /**
     * Check if rule exists for given field name.
     *
     * @param string $name Field name
     * @return bool True if rule exists
     */
    public function has(string $name): bool;

    /**
     * Get rule for given field name.
     *
     * @param string $name Field name
     * @return RuleInterface Rule instance
     * @throws \OutOfBoundsException When rule does not exist
     */
    public function get(string $name): RuleInterface;

    /**
     * Get all rules as associative array.
     *
     * @return array<string, RuleInterface> Map of field names to rules
     */
    public function toArray(): array;
}
