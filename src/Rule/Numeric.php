<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Numeric rule: value must be numeric.
 *
 * Uses PHP's is_numeric() which accepts:
 * - Integers
 * - Floats
 * - Numeric strings ("123", "12.5", "-45", "1e10")
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Numeric implements RuleInterface
{
    public const string NOT_NUMERIC_MESSAGE_ID = 'validation.numeric.not_numeric';

    public string $name {
        get => 'numeric';
    }

    public function __invoke(mixed $value): bool
    {
        return is_numeric($value);
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_NUMERIC_MESSAGE_ID, [
            'type' => get_debug_type($value),
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_NUMERIC_MESSAGE_ID => 'The value must be numeric.',
        ];
    }
}
