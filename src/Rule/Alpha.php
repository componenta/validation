<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Alpha rule: value must contain only letters.
 *
 * Supports Unicode letters when $ascii is false.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Alpha implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.alpha.not_string';
    public const string INVALID_MESSAGE_ID = 'validation.alpha.invalid';

    public string $name {
        get => 'alpha';
    }

    /**
     * @param bool $ascii Only ASCII letters (a-zA-Z), false for Unicode support
     */
    public function __construct(
        private readonly bool $ascii = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $pattern = $this->ascii ? '/^[a-zA-Z]+$/' : '/^[\p{L}]+$/u';
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

        $pattern = $this->ascii ? '/^[a-zA-Z]+$/' : '/^[\p{L}]+$/u';

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
            self::INVALID_MESSAGE_ID => 'The value may only contain letters.',
        ];
    }
}
