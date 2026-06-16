<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Distinct rule: array elements must be unique.
 *
 * By default uses strict comparison. Can check a specific key in nested arrays.
 *
 * Example:
 *   new Distinct() // [1, 2, 3] OK, [1, 2, 2] FAIL
 *   new Distinct('id') // [{id: 1}, {id: 2}] OK, [{id: 1}, {id: 1}] FAIL
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Distinct implements RuleInterface
{
    public const string NOT_ARRAY_MESSAGE_ID = 'validation.distinct.not_array';
    public const string DUPLICATES_MESSAGE_ID = 'validation.distinct.duplicates';

    public string $name {
        get => 'distinct';
    }

    /**
     * @param string|null $key Key to check in nested arrays (null for direct values)
     * @param bool $strict Use strict comparison
     * @param bool $ignoreCase Ignore case for string comparison
     */
    public function __construct(
        private readonly ?string $key = null,
        private readonly bool $strict = true,
        private readonly bool $ignoreCase = false,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $values = $this->extractValues($value);
        $duplicates = $this->findDuplicates($values);

        return empty($duplicates);
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

        $values = $this->extractValues($value);
        $duplicates = $this->findDuplicates($values);

        if (!empty($duplicates)) {
            $collector->add($path, new ErrorMessage($context, self::DUPLICATES_MESSAGE_ID, [
                'duplicates' => implode(', ', array_map($this->stringify(...), $duplicates)),
            ]));
            return $collector;
        }

        return true;
    }

    private function extractValues(array $array): array
    {
        if ($this->key === null) {
            return $array;
        }

        $values = [];
        foreach ($array as $item) {
            if (is_array($item) && array_key_exists($this->key, $item)) {
                $values[] = $item[$this->key];
            }
        }

        return $values;
    }

    private function findDuplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($values as $value) {
            $normalized = $this->normalize($value);

            foreach ($seen as $seenValue) {
                if ($this->equals($normalized, $seenValue)) {
                    $duplicates[] = $value;
                    break;
                }
            }

            $seen[] = $normalized;
        }

        return array_unique($duplicates, SORT_REGULAR);
    }

    private function normalize(mixed $value): mixed
    {
        if ($this->ignoreCase && is_string($value)) {
            return mb_strtolower($value);
        }
        return $value;
    }

    private function equals(mixed $a, mixed $b): bool
    {
        return $this->strict ? $a === $b : $a == $b;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        return gettype($value);
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_ARRAY_MESSAGE_ID => 'The value must be an array.',
            self::DUPLICATES_MESSAGE_ID => 'Duplicate values found: :duplicates.',
        ];
    }
}
