<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Filled rule: if present, the field must not be empty.
 *
 * Unlike Required, this rule passes if the field is missing entirely.
 * But if present, it must have a non-empty value.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Filled implements RuleInterface
{
    public const string FILLED_MESSAGE_ID = 'validation.filled';

    public string $name {
        get => 'filled';
    }

    public function __invoke(mixed $value): bool
    {
        // null means "not present" - which is OK for Filled
        if ($value === null) {
            return true;
        }
        if ($value === '' || $value === []) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::FILLED_MESSAGE_ID));
        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::FILLED_MESSAGE_ID => 'This field must not be empty when present.',
        ];
    }
}
