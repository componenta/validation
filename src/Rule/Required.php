<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Required rule: field must be present and not empty.
 *
 * Empty values: null, '', [], (whitespace-only strings)
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Required implements RuleInterface
{
    public const string REQUIRED_MESSAGE_ID = 'validation.required';

    public string $name {
        get => 'required';
    }

    public function __invoke(mixed $value): bool
    {
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
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::REQUIRED_MESSAGE_ID));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::REQUIRED_MESSAGE_ID => 'This field is required.',
        ];
    }
}
