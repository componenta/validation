<?php

declare(strict_types=1);

namespace Componenta\Validation\Internal;

use InvalidArgumentException;
use ReflectionReference;
use Traversable;
use WeakMap;

/** @internal */
final class ValidationData
{
    /**
     * @param iterable<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function materialize(iterable $data): array
    {
        $activeReferences = [];
        /** @var WeakMap<Traversable<array-key, mixed>, array<array-key, mixed>|false> $iterables */
        $iterables = new WeakMap();

        return self::read($data, $activeReferences, $iterables);
    }

    /**
     * @param iterable<array-key, mixed> $data
     * @param array<string, true> $activeReferences
     * @param WeakMap<Traversable<array-key, mixed>, array<array-key, mixed>|false> $iterables
     * @return array<array-key, mixed>
     */
    private static function read(iterable $data, array &$activeReferences, WeakMap $iterables): array
    {
        if (is_array($data)) {
            return self::readArray($data, $activeReferences, $iterables);
        }

        if (isset($iterables[$data])) {
            $result = $iterables[$data];
            if ($result === false) {
                throw new InvalidArgumentException('Validation input cannot contain recursive collections.');
            }

            return $result;
        }

        $iterables[$data] = false;

        $result = self::readArray(iterator_to_array($data), $activeReferences, $iterables);
        $iterables[$data] = $result;

        return $result;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, true> $activeReferences
     * @param WeakMap<Traversable<array-key, mixed>, array<array-key, mixed>|false> $iterables
     * @return array<array-key, mixed>
     */
    private static function readArray(array $data, array &$activeReferences, WeakMap $iterables): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $reference = ReflectionReference::fromArrayElement($data, $key)?->getId();
                if ($reference !== null) {
                    if (isset($activeReferences[$reference])) {
                        throw new InvalidArgumentException('Validation input cannot contain recursive collections.');
                    }
                    $activeReferences[$reference] = true;
                }

                $value = self::readArray($value, $activeReferences, $iterables);
                if ($reference !== null) {
                    unset($activeReferences[$reference]);
                }
            } elseif ($value instanceof Traversable) {
                $value = self::read($value, $activeReferences, $iterables);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
