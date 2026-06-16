<?php

namespace Componenta\Validation\Rule;
use Attribute;

use DateTimeImmutable;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * DateFormat rule: date string must match a specific format.
 *
 * Uses PHP's DateTime::createFromFormat() for validation.
 *
 * Example:
 *   new DateFormat('Y-m-d') // 2024-01-15
 *   new DateFormat('d/m/Y') // 15/01/2024
 *   new DateFormat('Y-m-d H:i:s') // 2024-01-15 14:30:00
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class DateFormat implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.date_format.not_string';
    public const string INVALID_FORMAT_MESSAGE_ID = 'validation.date_format.invalid_format';

    public string $name {
        get => 'date_format';
    }

    public function __construct(
        private readonly string $format,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat($this->format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return false;
        }

        return $date->format($this->format) === $value;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if (!is_string($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_STRING_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        $date = DateTimeImmutable::createFromFormat($this->format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        // Check if date was created and matches the original format
        if ($date === false || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_FORMAT_MESSAGE_ID, [
                'format' => $this->format,
            ]));
            return $collector;
        }

        // Verify the formatted date matches the input (catches invalid dates like 2024-02-30)
        if ($date->format($this->format) !== $value) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_FORMAT_MESSAGE_ID, [
                'format' => $this->format,
            ]));
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Value must be a string, :type given.',
            self::INVALID_FORMAT_MESSAGE_ID => 'The date must match the format :format.',
        ];
    }
}
