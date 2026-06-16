<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * ExcludeIf rule: skip validation entirely if another field has a specific value.
 *
 * Unlike ProhibitedIf (which requires field to be empty), this rule
 * simply skips all validation when the condition is met.
 *
 * Example:
 *   new ExcludeIf('is_draft', true) // skip validation when is_draft is true
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ExcludeIf implements RuleInterface
{
    public string $name {
        get => 'exclude_if';
    }

    /**
     * @param string $otherField Field to check
     * @param mixed $value Value that triggers exclusion
     */
    public function __construct(
        private readonly string $otherField,
        private readonly mixed $value,
    ) {}

    /**
     * Always returns true - ExcludeIf is a meta-rule that affects validation flow.
     */
    public function __invoke(mixed $value): bool
    {
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $otherValue = $data[$this->otherField] ?? null;

        // If condition met - skip validation (always pass)
        if ($otherValue === $this->value) {
            return true;
        }

        // Condition not met - this rule passes, other rules in chain will validate
        return true;
    }

    /**
     * Check if validation should be skipped based on context.
     *
     * This method can be used by composite rules (AllOf, etc.) to determine
     * if subsequent rules should be skipped.
     */
    public function shouldExclude(ContextInterface $context): bool
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $otherValue = $data[$this->otherField] ?? null;

        return $otherValue === $this->value;
    }

    public static function getMessages(): array
    {
        return [];
    }
}
