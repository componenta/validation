<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Nullable rule: allows null values.
 *
 * When combined with other rules via AllOf, validation passes if value is null.
 * This rule should typically be first in a chain.
 *
 * Example:
 *   new AllOf(new Nullable(), new Email()) // null or valid email
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Nullable implements RuleInterface
{
    public string $name {
        get => 'nullable';
    }

    public function __invoke(mixed $value): bool
    {
        return $value === null;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        return true;
    }

    public function inverse(mixed $value): bool
    {
        return $value !== null;
    }
}
