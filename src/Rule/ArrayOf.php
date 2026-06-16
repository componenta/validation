<?php

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * ArrayOf rule: each element in the array must pass the given rule.
 *
 * Example:
 *   new ArrayOf(new Email()) // array of valid emails
 *   new ArrayOf(new Integer()) // array of integers
 */
final class ArrayOf implements RuleInterface
{
    public const string NOT_ARRAY_MESSAGE_ID = 'validation.array_of.not_array';

    public string $name {
        get => 'array_of(' . $this->rule->name . ')';
    }

    public function __construct(
        private readonly RuleInterface $rule,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!($this->rule)($item)) {
                return false;
            }
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

        $hasErrors = false;
        $stopFirst = (bool) $context->getAttribute(ContextInterface::STOP_ON_FIRST_FAILURE_ATTRIBUTE, false);

        foreach ($value as $index => $item) {
            $itemPath = $path === '' ? (string) $index : $path . '.' . $index;

            $itemContext = $context->withAttributes([
                ContextInterface::CURRENT_PATH_ATTRIBUTE => $itemPath,
                ContextInterface::CURRENT_FIELD_ATTRIBUTE => (string) $index,
            ]);

            $result = $this->rule->validate($item, $itemContext);

            if ($result !== true) {
                $hasErrors = true;
                $collector->merge($result);

                if ($stopFirst) {
                    break;
                }
            }
        }

        return $hasErrors ? $collector : true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_ARRAY_MESSAGE_ID => 'The value must be an array.',
        ];
    }
}
