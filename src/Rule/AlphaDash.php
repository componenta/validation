<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * AlphaDash rule: value must contain only letters, numbers, dashes and underscores.
 *
 * Useful for slugs, usernames, identifiers.
 * Supports Unicode when $ascii is false.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class AlphaDash implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.alpha_dash.not_string';
    public const string INVALID_MESSAGE_ID = 'validation.alpha_dash.invalid';

    public string $name {
        get => 'alpha_dash';
    }

    /**
     * @param bool $ascii Only ASCII (a-zA-Z0-9-_), false for Unicode support
     */
    public function __construct(
        private readonly bool $ascii = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $pattern = $this->ascii ? '/^[a-zA-Z0-9_-]+$/' : '/^[\p{L}\p{N}_-]+$/u';
        return preg_match($pattern, $value) === 1;
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

        $pattern = $this->ascii ? '/^[a-zA-Z0-9_-]+$/' : '/^[\p{L}\p{N}_-]+$/u';

        if (!preg_match($pattern, $value)) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_MESSAGE_ID));
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Value must be a string, :type given.',
            self::INVALID_MESSAGE_ID => 'The value may only contain letters, numbers, dashes and underscores.',
        ];
    }
}
