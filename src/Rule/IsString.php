<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * IsString rule: value must be a string.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class IsString implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.is_string.not_string';

    public string $name {
        get => 'is_string';
    }

    public function __invoke(mixed $value): bool
    {
        return is_string($value);
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_STRING_MESSAGE_ID, [
            'type' => get_debug_type($value),
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'The value must be a string, :type given.',
        ];
    }
}
