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
 * Range rule: numeric or date value must be within bounds.
 *
 * Supports:
 * - Numbers (int, float, numeric strings)
 * - Dates (DateTimeInterface, timestamp, date strings)
 *
 * Examples:
 *   new Range(min: 0)                    // >= 0
 *   new Range(max: 100)                  // <= 100
 *   new Range(min: 0, max: 100)          // 0-100
 *   new Range(min: '2024-01-01', max: '2024-12-31')  // date range
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Range implements RuleInterface
{
    public const string NOT_NUMERIC_MESSAGE_ID = 'validation.range.not_numeric';
    public const string TOO_SMALL_MESSAGE_ID = 'validation.range.too_small';
    public const string TOO_LARGE_MESSAGE_ID = 'validation.range.too_large';
    public const string OUT_OF_RANGE_MESSAGE_ID = 'validation.range.out_of_range';
    public const string INVALID_DATE_MESSAGE_ID = 'validation.range.invalid_date';

    private readonly bool $isDateRange;
    private readonly ?DateTimeImmutable $minDate;
    private readonly ?DateTimeImmutable $maxDate;

    public string $name {
        get => 'range';
    }

    /**
     * @param int|float|string|DateTimeInterface|null $min Minimum value
     * @param int|float|string|DateTimeInterface|null $max Maximum value
     */
    public function __construct(
        private readonly int|float|string|DateTimeInterface|null $min = null,
        private readonly int|float|string|DateTimeInterface|null $max = null,
    ) {
        if ($this->min === null && $this->max === null) {
            throw new \InvalidArgumentException('At least one of min or max must be specified');
        }

        $this->isDateRange = $this->isDateValue($min) || $this->isDateValue($max);

        if ($this->isDateRange) {
            $this->minDate = $min !== null ? $this->toDateTime($min) : null;
            $this->maxDate = $max !== null ? $this->toDateTime($max) : null;
        } else {
            $this->minDate = null;
            $this->maxDate = null;
        }
    }

    public function __invoke(mixed $value): bool
    {
        if ($this->isDateRange) {
            $dateValue = $this->toDateTime($value);
            if ($dateValue === null) {
                return false;
            }
            if ($this->minDate !== null && $dateValue < $this->minDate) {
                return false;
            }
            if ($this->maxDate !== null && $dateValue > $this->maxDate) {
                return false;
            }
            return true;
        }

        if (!is_numeric($value)) {
            return false;
        }

        $numericValue = (float) $value;
        $minNum = $this->min !== null ? (float) $this->min : null;
        $maxNum = $this->max !== null ? (float) $this->max : null;

        if ($minNum !== null && $numericValue < $minNum) {
            return false;
        }
        if ($maxNum !== null && $numericValue > $maxNum) {
            return false;
        }

        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if ($this->isDateRange) {
            return $this->validateDate($value, $context, $collector, $path);
        }

        return $this->validateNumeric($value, $context, $collector, $path);
    }

    private function validateNumeric(
        mixed $value,
        ContextInterface $context,
        ErrorMessageCollector $collector,
        string $path
    ): true|ErrorMessageCollectorInterface {
        if (!is_numeric($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_NUMERIC_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        $numericValue = (float) $value;
        $minNum = $this->min !== null ? (float) $this->min : null;
        $maxNum = $this->max !== null ? (float) $this->max : null;

        $tooSmall = $minNum !== null && $numericValue < $minNum;
        $tooLarge = $maxNum !== null && $numericValue > $maxNum;

        if ($tooSmall || $tooLarge) {
            if ($minNum !== null && $maxNum !== null) {
                $collector->add($path, new ErrorMessage($context, self::OUT_OF_RANGE_MESSAGE_ID, [
                    'min' => $this->min,
                    'max' => $this->max,
                    'actual' => $numericValue,
                ]));
            } elseif ($tooSmall) {
                $collector->add($path, new ErrorMessage($context, self::TOO_SMALL_MESSAGE_ID, [
                    'min' => $this->min,
                    'actual' => $numericValue,
                ]));
            } else {
                $collector->add($path, new ErrorMessage($context, self::TOO_LARGE_MESSAGE_ID, [
                    'max' => $this->max,
                    'actual' => $numericValue,
                ]));
            }
            return $collector;
        }

        return true;
    }

    private function validateDate(
        mixed $value,
        ContextInterface $context,
        ErrorMessageCollector $collector,
        string $path
    ): true|ErrorMessageCollectorInterface {
        $dateValue = $this->toDateTime($value);

        if ($dateValue === null) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_DATE_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        $tooSmall = $this->minDate !== null && $dateValue < $this->minDate;
        $tooLarge = $this->maxDate !== null && $dateValue > $this->maxDate;

        if ($tooSmall || $tooLarge) {
            if ($this->minDate !== null && $this->maxDate !== null) {
                $collector->add($path, new ErrorMessage($context, self::OUT_OF_RANGE_MESSAGE_ID, [
                    'min' => $this->formatDate($this->minDate),
                    'max' => $this->formatDate($this->maxDate),
                    'actual' => $this->formatDate($dateValue),
                ]));
            } elseif ($tooSmall) {
                $collector->add($path, new ErrorMessage($context, self::TOO_SMALL_MESSAGE_ID, [
                    'min' => $this->formatDate($this->minDate),
                    'actual' => $this->formatDate($dateValue),
                ]));
            } else {
                $collector->add($path, new ErrorMessage($context, self::TOO_LARGE_MESSAGE_ID, [
                    'max' => $this->formatDate($this->maxDate),
                    'actual' => $this->formatDate($dateValue),
                ]));
            }
            return $collector;
        }

        return true;
    }

    private function isDateValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if ($value instanceof DateTimeInterface) {
            return true;
        }
        if (is_string($value) && !is_numeric($value)) {
            return strtotime($value) !== false;
        }
        return false;
    }

    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value) && !is_numeric($value)) {
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return (new DateTimeImmutable())->setTimestamp($timestamp);
            }
        }
        return null;
    }

    private function formatDate(?DateTimeImmutable $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? 'null';
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_NUMERIC_MESSAGE_ID => 'Value must be numeric, :type given.',
            self::TOO_SMALL_MESSAGE_ID => 'The value must be at least :min.',
            self::TOO_LARGE_MESSAGE_ID => 'The value must be at most :max.',
            self::OUT_OF_RANGE_MESSAGE_ID => 'The value must be between :min and :max.',
            self::INVALID_DATE_MESSAGE_ID => 'The value must be a valid date.',
        ];
    }
}
