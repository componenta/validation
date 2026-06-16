<?php

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Conditional rule: applies the inner rule only if a given condition is true.
 */
final class IfThen implements RuleInterface
{
    private(set) string $name;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param callable(mixed, ContextInterface): bool $callback A callback returning true if rule should be applied
     * @param RuleInterface $rule Inner rule to apply
     * @param string|null $name Optional custom name, defaults to inner rule's name
     */
    public function __construct(
        callable $callback,
        private readonly RuleInterface $rule,
        ?string $name = null
    ) {
        $this->callback = $callback;
        $this->name = $name ?? $rule->name;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if (($this->callback)($value, $context)) {
            return $this->rule->validate($value, $context);
        }

        return true;
    }
}
