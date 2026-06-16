<?php

namespace Componenta\Validation\Rule;

use Attribute;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * IsBoolean rule: value must be a boolean.
 *
 * Accepted values:
 * - true / false
 * - 1 / 0
 * - "1" / "0"
 * - "true" / "false"
 *
 * With strict mode only PHP bool type is accepted.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class IsBoolean implements RuleInterface
{
    public const string NOT_BOOLEAN_MESSAGE_ID = 'validation.is_boolean.not_boolean';

    public string $name {
        get => 'is_boolean';
    }

    /**
     * @param bool $strict Only accept PHP bool type
     */
    public function __construct(
        private readonly bool $strict = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if ($this->strict) {
            return is_bool($value);
        }

        if (is_bool($value)) {
            return true;
        }

        if ($value === 1 || $value === 0) {
            return true;
        }

        if (is_string($value)) {
            return in_array(
                strtolower($value),
                ['1', '0', 'true', 'false'],
                true,
            );
        }

        return false;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_BOOLEAN_MESSAGE_ID, [
            'type' => get_debug_type($value),
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_BOOLEAN_MESSAGE_ID => 'The value must be a boolean.',
        ];
    }
}
