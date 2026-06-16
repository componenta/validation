<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Negative rule: value must be a negative number.
 *
 * By default, zero is not considered negative.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Negative implements RuleInterface
{
    public const string NOT_NUMERIC_MESSAGE_ID = 'validation.negative.not_numeric';
    public const string NOT_NEGATIVE_MESSAGE_ID = 'validation.negative.not_negative';

    public string $name {
        get => 'negative';
    }

    /**
     * @param bool $allowZero Allow zero as a valid value
     */
    public function __construct(
        private readonly bool $allowZero = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $numericValue = (float) $value;
        return $this->allowZero ? $numericValue <= 0 : $numericValue < 0;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if (!is_numeric($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_NUMERIC_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        $numericValue = is_string($value) ? (float) $value : $value;

        $isValid = $this->allowZero ? $numericValue <= 0 : $numericValue < 0;

        if (!$isValid) {
            $collector->add($path, new ErrorMessage($context, self::NOT_NEGATIVE_MESSAGE_ID, [
                'actual' => $numericValue,
                'allow_zero' => $this->allowZero ? 'yes' : 'no',
            ]));
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_NUMERIC_MESSAGE_ID => 'Value must be numeric, :type given.',
            self::NOT_NEGATIVE_MESSAGE_ID => 'The value must be negative.',
        ];
    }
}
