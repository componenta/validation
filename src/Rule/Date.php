<?php

namespace Componenta\Validation\Rule;
use Attribute;

use DateTimeInterface;
use DateTimeImmutable;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Date rule: value must be a valid date.
 *
 * Accepts:
 * - DateTimeInterface objects
 * - Strings parseable by strtotime() (e.g., "2024-01-15", "next monday")
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Date implements RuleInterface
{
    public const string INVALID_DATE_MESSAGE_ID = 'validation.date.invalid';

    public string $name {
        get => 'date';
    }

    public function __invoke(mixed $value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if (!is_string($value) || $value === '') {
            return false;
        }

        // Try to parse with strtotime
        $timestamp = @strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        // Additional check: verify it's a real date by parsing and formatting back
        // This catches cases where strtotime accepts invalid dates like "2024-02-30"
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date !== false) {
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return false;
            }
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
        $collector->add($path, new ErrorMessage($context, self::INVALID_DATE_MESSAGE_ID, [
            'type' => get_debug_type($value),
        ]));

        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::INVALID_DATE_MESSAGE_ID => 'The value must be a valid date.',
        ];
    }
}
