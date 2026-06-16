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
 * GreaterThan rule: value must be greater than another field's value.
 *
 * Supports numbers and dates.
 *
 * Example:
 *   new GreaterThan('min_price') // max_price must be > min_price
 *   new GreaterThan('start_date') // end_date must be > start_date
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class GreaterThan implements RuleInterface
{
    public const string NOT_COMPARABLE_MESSAGE_ID = 'validation.greater_than.not_comparable';
    public const string NOT_GREATER_MESSAGE_ID = 'validation.greater_than.not_greater';

    public string $name {
        get => 'greater_than';
    }

    /**
     * @param string $otherField Field to compare with
     * @param bool $orEqual Also accept equal values (>=)
     */
    public function __construct(
        private readonly string $otherField,
        private readonly bool $orEqual = false,
    ) {}

    /**
     * Quick check if value is greater than other.
     * Supports numbers and dates.
     */
    public function __invoke(mixed $value, mixed $other = null): bool
    {
        // Try date comparison first
        $valueDate = $this->toDateTime($value);
        $otherDate = $this->toDateTime($other);

        if ($valueDate !== null && $otherDate !== null) {
            return $this->orEqual ? $valueDate >= $otherDate : $valueDate > $otherDate;
        }

        // Numeric comparison
        if (!is_numeric($value) || !is_numeric($other)) {
            return false;
        }

        $numValue = (float) $value;
        $numOther = (float) $other;

        return $this->orEqual ? $numValue >= $numOther : $numValue > $numOther;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $otherValue = $this->getNestedValue($data, $this->otherField);

        // Try date comparison first
        $valueDate = $this->toDateTime($value);
        $otherDate = $this->toDateTime($otherValue);

        if ($valueDate !== null && $otherDate !== null) {
            if ($this($value, $otherValue)) {
                return true;
            }

            $collector->add($path, new ErrorMessage($context, self::NOT_GREATER_MESSAGE_ID, [
                'other' => $this->otherField,
                'or_equal' => $this->orEqual ? 'or equal to' : '',
            ]));
            return $collector;
        }

        // Numeric comparison
        if (!is_numeric($value) || !is_numeric($otherValue)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_COMPARABLE_MESSAGE_ID, [
                'other' => $this->otherField,
            ]));
            return $collector;
        }

        if ($this($value, $otherValue)) {
            return true;
        }

        $collector->add($path, new ErrorMessage($context, self::NOT_GREATER_MESSAGE_ID, [
            'other' => $this->otherField,
            'or_equal' => $this->orEqual ? 'or equal to' : '',
        ]));

        return $collector;
    }

    private function getNestedValue(array $data, string $key): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
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

    public static function getMessages(): array
    {
        return [
            self::NOT_COMPARABLE_MESSAGE_ID => 'Cannot compare values with :other.',
            self::NOT_GREATER_MESSAGE_ID => 'The value must be greater than :or_equal :other.',
        ];
    }
}
