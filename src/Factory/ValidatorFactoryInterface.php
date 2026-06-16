<?php

namespace Componenta\Validation\Factory;

use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\ValidatorInterface;

/**
 * Factory interface for creating validator instances.
 *
 * Provides methods to create validators from class name or from rule definitions.
 */
interface ValidatorFactoryInterface
{
    /**
     * Create validator from class.
     *
     * @template T of ValidatorInterface
     * @param class-string<T> $cls Fully qualified class name
     * @return ValidatorInterface Validator instance
     */
    public function create(string $cls): ValidatorInterface;

    /**
     * Create validator from rule definitions.
     *
     * Creates a validator from an array of rules.
     *
     * @param array<string, string|RuleInterface> $rules Rule definitions (array of RuleInterface or string syntax)
     * @return ValidatorInterface Validator instance
     */
    public function createFrom(array $rules): ValidatorInterface;
}