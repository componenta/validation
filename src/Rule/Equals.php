<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Equals rule: value must equal another field's value.
 *
 * Uses strict comparison (===) by default.
 *
 * Example:
 *   new Equals('password') // for password_confirmation field
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Equals implements RuleInterface
{
    public const string NOT_EQUAL_MESSAGE_ID = 'validation.equals.not_equal';

    public string $name {
        get => 'equals';
    }

    /**
     * @param string $otherField Field to compare with
     * @param bool $strict Use strict comparison (===)
     */
    public function __construct(
        private readonly string $otherField,
        private readonly bool $strict = true,
    ) {}

    /**
     * Quick check if two values are equal.
     */
    public function __invoke(mixed $value, mixed $other = null): bool
    {
        return $this->strict ? $value === $other : $value == $other;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $otherValue = $this->getNestedValue($data, $this->otherField);

        if ($this($value, $otherValue)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_EQUAL_MESSAGE_ID, [
            'other' => $this->otherField,
        ]));

        return $collector;
    }

    /**
     * Get value from nested array using dot notation.
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_EQUAL_MESSAGE_ID => 'The value must match :other.',
        ];
    }
}
