<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Countable;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Count rule: array/countable must have element count within bounds.
 *
 * Examples:
 *   new Count(min: 1)                   // at least 1 element
 *   new Count(max: 10)                  // at most 10 elements
 *   new Count(min: 1, max: 10)          // 1-10 elements
 *   new Count(exact: 3)                 // exactly 3 elements
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Count implements RuleInterface
{
    public const string NOT_COUNTABLE_MESSAGE_ID = 'validation.count.not_countable';
    public const string TOO_FEW_MESSAGE_ID = 'validation.count.too_few';
    public const string TOO_MANY_MESSAGE_ID = 'validation.count.too_many';
    public const string OUT_OF_RANGE_MESSAGE_ID = 'validation.count.out_of_range';
    public const string EXACT_MESSAGE_ID = 'validation.count.exact';

    private readonly ?int $min;
    private readonly ?int $max;

    public string $name {
        get => 'count';
    }

    /**
     * @param int|null $min Minimum count
     * @param int|null $max Maximum count
     * @param int|null $exact Exact count (shorthand for min=max)
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
        if (!is_array($value) && !$value instanceof Countable) {
            return false;
        }

        $count = count($value);

        if ($this->min !== null && $count < $this->min) {
            return false;
        }
        if ($this->max !== null && $count > $this->max) {
            return false;
        }

        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if (!is_array($value) && !$value instanceof Countable) {
            $collector->add($path, new ErrorMessage($context, self::NOT_COUNTABLE_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        $count = count($value);
        $tooFew = $this->min !== null && $count < $this->min;
        $tooMany = $this->max !== null && $count > $this->max;

        if ($tooFew || $tooMany) {
            // Exact count case
            if ($this->min !== null && $this->max !== null && $this->min === $this->max) {
                $collector->add($path, new ErrorMessage($context, self::EXACT_MESSAGE_ID, [
                    'expected' => $this->min,
                    'actual' => $count,
                ]));
            } elseif ($this->min !== null && $this->max !== null) {
                $collector->add($path, new ErrorMessage($context, self::OUT_OF_RANGE_MESSAGE_ID, [
                    'min' => $this->min,
                    'max' => $this->max,
                    'actual' => $count,
                ]));
            } elseif ($tooFew) {
                $collector->add($path, new ErrorMessage($context, self::TOO_FEW_MESSAGE_ID, [
                    'min' => $this->min,
                    'actual' => $count,
                ]));
            } else {
                $collector->add($path, new ErrorMessage($context, self::TOO_MANY_MESSAGE_ID, [
                    'max' => $this->max,
                    'actual' => $count,
                ]));
            }
            return $collector;
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_COUNTABLE_MESSAGE_ID => 'Value must be an array or countable, :type given.',
            self::TOO_FEW_MESSAGE_ID => 'Must have at least :min items.',
            self::TOO_MANY_MESSAGE_ID => 'Must have at most :max items.',
            self::OUT_OF_RANGE_MESSAGE_ID => 'Must have between :min and :max items.',
            self::EXACT_MESSAGE_ID => 'Must have exactly :expected items.',
        ];
    }
}
