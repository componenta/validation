<?php

namespace Componenta\Validation\Rule;
use Attribute;

use DateTimeImmutable;
use DateTimeInterface;
use DateInterval;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * After rule: date must be after a given date.
 *
 * The reference can be:
 * - A fixed date (string or DateTimeInterface)
 * - A field name prefixed with "field:" to compare with another field
 * - "today", "tomorrow", "yesterday" shortcuts
 *
 * Example:
 *   new After('2024-01-01')
 *   new After('field:start_date')
 *   new After('today')
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class After implements RuleInterface
{
    public const string INVALID_DATE_MESSAGE_ID = 'validation.after.invalid_date';
    public const string NOT_AFTER_MESSAGE_ID = 'validation.after.not_after';

    public string $name {
        get => 'after';
    }

    /**
     * @param string|DateTimeInterface $date Reference date or "field:fieldname"
     * @param bool $orEqual Also accept equal dates.
     * @param int $graceMinutes Allow the value to lag behind the reference by this many minutes.
     */
    public function __construct(
        private readonly string|DateTimeInterface $date,
        private readonly bool $orEqual = false,
        private readonly int $graceMinutes = 0,
    ) {}

    /**
     * Quick check if value is after reference date.
     *
     * @param mixed $value Date to check
     * @param mixed $reference Reference date (optional, uses constructor date if not provided)
     */
    public function __invoke(mixed $value, mixed $reference = null): bool
    {
        $valueDate = $this->toDateTime($value);
        if ($valueDate === null) {
            return false;
        }

        $refDate = $reference !== null
            ? $this->toDateTime($reference)
            : $this->toDateTime($this->date);

        if ($refDate === null) {
            return false;
        }

        $refDate = $this->applyGraceWindow($refDate);

        return $this->orEqual ? $valueDate >= $refDate : $valueDate > $refDate;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        $valueDate = $this->toDateTime($value);
        if ($valueDate === null) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_DATE_MESSAGE_ID));
            return $collector;
        }

        $referenceDate = $this->getReferenceDate($context);
        if ($referenceDate === null) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_DATE_MESSAGE_ID));
            return $collector;
        }

        $referenceDate = $this->applyGraceWindow($referenceDate);

        $isValid = $this->orEqual
            ? $valueDate >= $referenceDate
            : $valueDate > $referenceDate;

        if ($isValid) {
            return true;
        }

        $collector->add($path, new ErrorMessage($context, self::NOT_AFTER_MESSAGE_ID, [
            'date' => $this->formatReference($referenceDate),
            'or_equal' => $this->orEqual ? ' or equal to' : '',
        ]));

        return $collector;
    }

    private function getReferenceDate(ContextInterface $context): ?DateTimeImmutable
    {
        if ($this->date instanceof DateTimeInterface) {
            return $this->date instanceof DateTimeImmutable
                ? $this->date
                : DateTimeImmutable::createFromInterface($this->date);
        }

        // Check for field reference
        if (str_starts_with($this->date, 'field:')) {
            $fieldName = substr($this->date, 6);
            $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
            $fieldValue = $data[$fieldName] ?? null;
            return $this->toDateTime($fieldValue);
        }

        return $this->toDateTime($this->date);
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

    private function applyGraceWindow(DateTimeImmutable $referenceDate): DateTimeImmutable
    {
        if ($this->graceMinutes <= 0) {
            return $referenceDate;
        }

        return $referenceDate->sub(new DateInterval(sprintf('PT%dM', $this->graceMinutes)));
    }

    private function formatReference(DateTimeImmutable $referenceDate): string
    {
        if ($this->graceMinutes > 0) {
            return $referenceDate->format('Y-m-d H:i:s');
        }

        if ($this->date instanceof DateTimeInterface) {
            return $this->date->format('Y-m-d H:i:s');
        }

        if (str_starts_with($this->date, 'field:')) {
            return substr($this->date, 6);
        }

        return $this->date;
    }

    public static function getMessages(): array
    {
        return [
            self::INVALID_DATE_MESSAGE_ID => 'The value must be a valid date.',
            self::NOT_AFTER_MESSAGE_ID => 'The date must be after:or_equal :date.',
        ];
    }
}
