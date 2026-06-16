<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Validation rule interface.
 *
 * Defines contract for validation rules that check values against specific constraints.
 */
interface RuleInterface
{
    /**
     * Unique identifier of the rule.
     */
    public string $name { get; }

    /**
     * Validate value against rule constraints.
     *
     * @param mixed $value Value to validate
     * @param ContextInterface $context Validation context
     * @return true|ErrorMessageCollectorInterface True if valid, error collector otherwise
     */
    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface;
}
