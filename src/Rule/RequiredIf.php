<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * RequiredIf rule: field is required if another field has a specific value.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class RequiredIf implements RuleInterface
{
    public const string REQUIRED_IF_MESSAGE_ID = 'validation.required_if';

    public string $name {
        get => 'required_if';
    }

    /**
     * @param string $otherField Field to check in data
     * @param mixed $expectedValue Value that triggers required validation
     */
    public function __construct(
        private readonly string $otherField,
        private readonly mixed  $expectedValue
    ) {}

    /**
     * Quick check: if condition is met, value must not be empty.
     *
     * @param mixed $value Value to check
     * @param mixed $otherValue The other field's value
     */
    public function __invoke(mixed $value, mixed $otherValue = null): bool
    {
        // If condition not met, always valid
        if ($otherValue !== $this->expectedValue) {
            return true;
        }

        // Condition met - value must not be empty
        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        $otherValue = $data[$this->otherField] ?? null;

        if ($this($value, $otherValue)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $collector->add($path, new ErrorMessage(
            $context,
            self::REQUIRED_IF_MESSAGE_ID,
            [
                'field' => $this->otherField,
                'value' => is_scalar($this->expectedValue) ? (string) $this->expectedValue : gettype($this->expectedValue),
            ]
        ));
        return $collector;
    }

    /**
     * Provides default English message template.
     */
    public static function getMessages(): array
    {
        return [
            self::REQUIRED_IF_MESSAGE_ID => 'This field is required because ":field" equals ":value".',
        ];
    }
}
