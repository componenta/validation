<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * IsArray rule: value must be an array.
 *
 * Optionally can require a list (sequential numeric keys starting from 0).
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class IsArray implements RuleInterface
{
    public const string NOT_ARRAY_MESSAGE_ID = 'validation.is_array.not_array';
    public const string NOT_LIST_MESSAGE_ID = 'validation.is_array.not_list';

    public string $name {
        get => 'is_array';
    }

    /**
     * @param bool $list Require a list (array_is_list)
     */
    public function __construct(
        private readonly bool $list = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if ($this->list && !array_is_list($value)) {
            return false;
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if (!is_array($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_ARRAY_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        if ($this->list && !array_is_list($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_LIST_MESSAGE_ID));
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_ARRAY_MESSAGE_ID => 'The value must be an array.',
            self::NOT_LIST_MESSAGE_ID => 'The value must be a list.',
        ];
    }
}
