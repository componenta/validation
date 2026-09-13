<?php
declare(strict_types=1);

namespace Componenta\Validation\Provider;

use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\ValidatorInterface;
use InvalidArgumentException;

/**
 * @phpstan-type Entry array<array-key, string>|string
 */
final readonly class MapValidationProvider implements ValidationProviderInterface
{
    /** @var array<string, Entry> */
    private array $entries;

    /** @param array<array-key, mixed> $entries */
    public function __construct(
        array $entries,
        private ValidatorFactoryInterface $validators,
        private RuleFactoryInterface $rules,
    ) {
        if (!self::validMap($entries)) {
            throw new InvalidArgumentException('Validation map must associate class names with field rules or validator service names.');
        }
        $normalized = [];
        foreach ($entries as $class => $entry) {
            $normalized[strtolower(ltrim($class, '\\'))] = $entry;
        }
        $this->entries = $normalized;
    }

    public function provide(string $entryId): ?ValidatorInterface
    {
        $key = strtolower(ltrim($entryId, '\\'));
        if (!array_key_exists($key, $this->entries)) {
            return null;
        }
        $entry = $this->entries[$key];
        if ($entry === []) {
            return null;
        }
        if (is_array($entry)) {
            $fields = [];
            foreach ($entry as $field => $definition) {
                $fields[$field] = $this->rules->createRule($definition);
            }
            return $this->validators->createFrom($fields);
        }
        if (!is_a($entry, ValidatorInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                '#[ValidatedBy] on "%s" must reference a class or interface implementing %s; got "%s".',
                $entryId, ValidatorInterface::class, $entry,
            ));
        }
        return $this->validators->create($entry);
    }

    /** @phpstan-assert-if-true array<string, Entry> $map */
    public static function validMap(mixed $map): bool
    {
        if (!is_array($map)) {
            return false;
        }
        $seen = [];
        foreach ($map as $class => $entry) {
            if (!is_string($class) || trim($class) === '') {
                return false;
            }
            $key = strtolower(ltrim($class, '\\'));
            if ($key === '' || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            if (is_array($entry)) {
                foreach ($entry as $field => $rule) {
                    if ($field === '' || !is_string($rule)) {
                        return false;
                    }
                }
            } elseif (!is_string($entry) || trim($entry) === '') {
                return false;
            }
        }
        return true;
    }
}
