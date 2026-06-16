<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * ProhibitedIf rule: field must be empty/missing if another field has a specific value.
 *
 * Example:
 *   new ProhibitedIf('type', 'guest') // prohibited when type is 'guest'
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ProhibitedIf implements RuleInterface
{
    public const string PROHIBITED_MESSAGE_ID = 'validation.prohibited_if';

    public string $name {
        get => 'prohibited_if';
    }

    /**
     * @param string $otherField Field to check
     * @param mixed $value Value that triggers prohibition
     */
    public function __construct(
        private readonly string $otherField,
        private readonly mixed $value,
    ) {}

    /**
     * Quick check: if condition is met, value must be empty.
     *
     * @param mixed $value Value to check
     * @param mixed $otherValue The other field's value
     */
    public function __invoke(mixed $value, mixed $otherValue = null): bool
    {
        // If condition not met, field is allowed
        if ($otherValue !== $this->value) {
            return true;
        }

        // Condition met - field must be empty
        if ($value === null || $value === '' || $value === []) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        return false;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $otherValue = $data[$this->otherField] ?? null;

        // If condition not met, field is allowed
        if ($otherValue !== $this->value) {
            return true;
        }

        // Condition met - field must be empty
        $isEmpty = $value === null
            || $value === ''
            || $value === []
            || (is_string($value) && trim($value) === '');

        if ($isEmpty) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::PROHIBITED_MESSAGE_ID, [
            'field' => $this->otherField,
            'value' => is_scalar($this->value) ? (string) $this->value : gettype($this->value),
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::PROHIBITED_MESSAGE_ID => 'This field is prohibited when :field is :value.',
        ];
    }
}
