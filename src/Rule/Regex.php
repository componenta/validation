<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Regex rule: value must match a regular expression.
 *
 * Example:
 *   new Regex('/^[A-Z]{2}\d{4}$/')
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Regex implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.regex.not_string';
    public const string NO_MATCH_MESSAGE_ID = 'validation.regex.no_match';

    public string $name {
        get => 'regex';
    }

    /**
     * @param string $pattern PCRE pattern with delimiters
     * @param string|null $message Custom error message
     */
    public function __construct(
        private readonly string $pattern,
        private readonly ?string $message = null,
    ) {}

    public function __invoke(mixed $value): bool
    {
        return is_string($value) && preg_match($this->pattern, $value) === 1;
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

        if (!preg_match($this->pattern, $value)) {
            $collector->add($path, new ErrorMessage($context, self::NO_MATCH_MESSAGE_ID, [
                'pattern' => $this->pattern,
                'message' => $this->message,
            ]));
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Value must be a string, :type given.',
            self::NO_MATCH_MESSAGE_ID => 'The value format is invalid.',
        ];
    }
}
