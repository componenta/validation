<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * IsInt rule: value must be an integer.
 *
 * Accepts:
 * - PHP int type
 * - Numeric strings representing integers ("123", "-45")
 * - Floats that are whole numbers (5.0)
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class IsInt implements RuleInterface
{
    public const string NOT_INT_MESSAGE_ID = 'validation.is_int.not_int';

    public string $name {
        get => 'is_int';
    }

    /**
     * @param bool $strict Only accept PHP int type (not numeric strings)
     */
    public function __construct(
        private readonly bool $strict = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if ($this->strict) {
            return is_int($value);
        }
        if (is_int($value)) {
            return true;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return true;
        }
        if (is_float($value) && floor($value) === $value && !is_infinite($value)) {
            return true;
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
        $collector->add($path, new ErrorMessage($context, self::NOT_INT_MESSAGE_ID, [
            'type' => get_debug_type($value),
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_INT_MESSAGE_ID => 'The value must be an integer.',
        ];
    }
}
