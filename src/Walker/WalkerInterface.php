<?php

namespace Componenta\Validation\Walker;

use Componenta\Validation\Rule\RuleCollectorInterface;

/**
 * Walker interface for traversing data structure with validation rules.
 *
 * Walks through input data and matches it against validation rules,
 * supporting nested structures and wildcard patterns.
 */
interface WalkerInterface
{
    /**
     * Walk through data applying validation rules.
     *
     * Traverses input data structure and yields validation targets
     * containing field paths, values, and corresponding rules.
     * Supports wildcards for array validation.
     *
     * @param iterable<string, mixed> $data Input data to validate
     * @param RuleCollectorInterface $rules Validation rules (supports wildcards)
     * @return iterable<Target> Validation targets (field path, value, rule)
     */
    public function walk(iterable $data, RuleCollectorInterface $rules): iterable;
}