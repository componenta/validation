<?php

declare(strict_types=1);

namespace Componenta\Validation\Walker;

use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;
use Generator;
use Traversable;

/** Walks actual and missing values for exact and wildcard rule paths. */
final class Walker implements WalkerInterface
{
    /** @var array<string, RuleInterface|null> */
    private array $ruleCache = [];

    /** @var array<string, true> */
    private array $yieldedPaths = [];

    public function walk(iterable $data, RuleCollectorInterface $rules): Generator
    {
        $this->ruleCache = [];
        $this->yieldedPaths = [];
        $data = self::materialize($data);

        yield from $this->walkData($data, $rules, '');

        foreach ($rules->toArray() as $pattern => $rule) {
            yield from $this->walkMissingPattern(
                $data,
                explode('.', $pattern),
                0,
                '',
                $rule,
            );
        }
    }

    /** @param array<array-key, mixed> $data */
    private function walkData(array $data, RuleCollectorInterface $rules, string $parentPath): Generator
    {
        foreach ($data as $key => $value) {
            $field = (string) $key;
            $path = $parentPath === '' ? $field : $parentPath . '.' . $field;
            $this->yieldedPaths[$path] = true;

            yield new Target($field, $path, $this->resolveRule($path, $rules), $value);

            if (is_array($value)) {
                yield from $this->walkData($value, $rules, $path);
            }
        }
    }

    /**
     * @param array<array-key, mixed>|mixed $value
     * @param list<string> $segments
     */
    private function walkMissingPattern(
        mixed $value,
        array $segments,
        int $index,
        string $parentPath,
        RuleInterface $rule,
    ): Generator {
        $segment = $segments[$index] ?? null;
        if ($segment === null) {
            return;
        }

        $last = $index === count($segments) - 1;

        if ($segment === '*') {
            if (!is_array($value)) {
                return;
            }

            foreach ($value as $key => $child) {
                $path = $parentPath === '' ? (string) $key : $parentPath . '.' . $key;
                if ($last) {
                    continue;
                }

                yield from $this->walkMissingPattern(
                    $child,
                    $segments,
                    $index + 1,
                    $path,
                    $rule,
                );
            }

            return;
        }

        $path = $parentPath === '' ? $segment : $parentPath . '.' . $segment;
        if (is_array($value) && array_key_exists($segment, $value)) {
            if (!$last) {
                yield from $this->walkMissingPattern(
                    $value[$segment],
                    $segments,
                    $index + 1,
                    $path,
                    $rule,
                );
            }

            return;
        }

        $remaining = array_slice($segments, $index);
        if (in_array('*', $remaining, true)) {
            return;
        }

        $missingPath = $parentPath === ''
            ? implode('.', $remaining)
            : $parentPath . '.' . implode('.', $remaining);

        if (isset($this->yieldedPaths[$missingPath])) {
            return;
        }

        $this->yieldedPaths[$missingPath] = true;
        yield new Target($segments[array_key_last($segments)], $missingPath, $rule, null);
    }

    private function resolveRule(string $path, RuleCollectorInterface $rules): ?RuleInterface
    {
        if (array_key_exists($path, $this->ruleCache)) {
            return $this->ruleCache[$path];
        }

        if ($rules->has($path)) {
            return $this->ruleCache[$path] = $rules->get($path);
        }

        $pathSegments = explode('.', $path);
        $best = null;
        $bestSpecificity = -1;

        foreach ($rules->toArray() as $pattern => $candidate) {
            if (!str_contains($pattern, '*')) {
                continue;
            }

            $patternSegments = explode('.', $pattern);
            if (count($patternSegments) !== count($pathSegments)) {
                continue;
            }

            $matches = true;
            $specificity = 0;
            foreach ($patternSegments as $index => $segment) {
                if ($segment === '*') {
                    continue;
                }

                if ($segment !== $pathSegments[$index]) {
                    $matches = false;
                    break;
                }

                $specificity++;
            }

            if ($matches && $specificity > $bestSpecificity) {
                $best = $candidate;
                $bestSpecificity = $specificity;
            }
        }

        return $this->ruleCache[$path] = $best;
    }

    /** @return array<array-key, mixed> */
    private static function materialize(iterable $data): array
    {
        $result = is_array($data) ? $data : iterator_to_array($data);

        foreach ($result as $key => $value) {
            if (is_array($value) || $value instanceof Traversable) {
                $result[$key] = self::materialize($value);
            }
        }

        return $result;
    }
}
