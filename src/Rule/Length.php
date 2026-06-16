<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Length rule: string length must be within bounds.
 *
 * Uses mb_strlen for multibyte support.
 *
 * Examples:
 *   new Length(min: 8)                  // >= 8 chars
 *   new Length(max: 255)                // <= 255 chars
 *   new Length(min: 8, max: 255)        // 8-255 chars
 *   new Length(exact: 6)                // exactly 6 chars
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Length implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.length.not_string';
    public const string TOO_SHORT_MESSAGE_ID = 'validation.length.too_short';
    public const string TOO_LONG_MESSAGE_ID = 'validation.length.too_long';
    public const string OUT_OF_RANGE_MESSAGE_ID = 'validation.length.out_of_range';
    public const string EXACT_MESSAGE_ID = 'validation.length.exact';

    private readonly ?int $min;
    private readonly ?int $max;

    public string $name {
        get => 'length';
    }

    /**
     * @param int|null $min Minimum length
     * @param int|null $max Maximum length
     * @param int|null $exact Exact length (shorthand for min=max)
     */
    public function __construct(
        ?int $min = null,
        ?int $max = null,
        ?int $exact = null,
    ) {
        if ($exact !== null) {
            $this->min = $exact;
            $this->max = $exact;
        } else {
            if ($min === null && $max === null) {
                throw new \InvalidArgumentException('At least one of min, max, or exact must be specified');
            }
            $this->min = $min;
            $this->max = $max;
        }
    }

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $length = mb_strlen($value);

        if ($this->min !== null && $length < $this->min) {
            return false;
        }
        if ($this->max !== null && $length > $this->max) {
            return false;
        }

        return true;
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

        $length = mb_strlen($value);
        $tooShort = $this->min !== null && $length < $this->min;
        $tooLong = $this->max !== null && $length > $this->max;

        if ($tooShort || $tooLong) {
            // Exact length case
            if ($this->min !== null && $this->max !== null && $this->min === $this->max) {
                $collector->add($path, new ErrorMessage($context, self::EXACT_MESSAGE_ID, [
                    'expected' => $this->min,
                    'actual' => $length,
                ]));
            } elseif ($this->min !== null && $this->max !== null) {
                $collector->add($path, new ErrorMessage($context, self::OUT_OF_RANGE_MESSAGE_ID, [
                    'min' => $this->min,
                    'max' => $this->max,
                    'actual' => $length,
                ]));
            } elseif ($tooShort) {
                $collector->add($path, new ErrorMessage($context, self::TOO_SHORT_MESSAGE_ID, [
                    'min' => $this->min,
                    'actual' => $length,
                ]));
            } else {
                $collector->add($path, new ErrorMessage($context, self::TOO_LONG_MESSAGE_ID, [
                    'max' => $this->max,
                    'actual' => $length,
                ]));
            }
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Value must be a string, :type given.',
            self::TOO_SHORT_MESSAGE_ID => 'Must be at least :min characters long.',
            self::TOO_LONG_MESSAGE_ID => 'Must be at most :max characters long.',
            self::OUT_OF_RANGE_MESSAGE_ID => 'Length must be between :min and :max characters.',
            self::EXACT_MESSAGE_ID => 'Must be exactly :expected characters long.',
        ];
    }
}
