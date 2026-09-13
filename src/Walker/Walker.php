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
    public function walk(iterable $data, RuleCollectorInterface $rules): Generator
    {
        $ruleCache = [];
        $yieldedPaths = [];
        $data = self::materialize($data);

        yield from $this->walkData($data, $rules, '', $ruleCache, $yieldedPaths);

        foreach (array_keys($rules->toArray()) as $pattern) {
            yield from $this->walkMissingPattern(
                $data,
                explode('.', $pattern),
                0,
                '',
                $rules,
                $ruleCache,
                $yieldedPaths,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @param array<string, RuleInterface|null> $ruleCache
     * @param array<string, true> $yieldedPaths
     */
    private function walkData(
        array $data,
        RuleCollectorInterface $rules,
        string $parentPath,
        array &$ruleCache,
        array &$yieldedPaths,
    ): Generator
    {
        foreach ($data as $key => $value) {
            $field = (string) $key;
            $path = $parentPath === '' ? $field : $parentPath . '.' . $field;
            $rule = $this->resolveRule($path, $rules, $ruleCache);
            $yieldedPaths[$path] = true;

            // Containers that only lead to descendant rules are traversal nodes,
            // not validation targets of their own. This preserves strict
            // SKIP_MISSING_RULES=false behavior for paths such as items.*.sku.
            if ($rule !== null
                || !is_array($value)
                || !$this->hasDescendantRule($path, $rules)
            ) {
                yield new Target($field, $path, $rule, $value);
            }

            if (is_array($value)) {
                yield from $this->walkData($value, $rules, $path, $ruleCache, $yieldedPaths);
            }
        }
    }

    /**
     * @param array<array-key, mixed>|mixed $value
     * @param list<string> $segments
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @param array<string, RuleInterface|null> $ruleCache
     * @param array<string, true> $yieldedPaths
     */
    private function walkMissingPattern(
        mixed $value,
        array $segments,
        int $index,
        string $parentPath,
        RuleCollectorInterface $rules,
        array &$ruleCache,
        array &$yieldedPaths,
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
                    $rules,
                    $ruleCache,
                    $yieldedPaths,
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
                    $rules,
                    $ruleCache,
                    $yieldedPaths,
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

        if (isset($yieldedPaths[$missingPath])) {
            return;
        }

        $yieldedPaths[$missingPath] = true;
        yield new Target(
            $segments[array_key_last($segments)],
            $missingPath,
            $this->resolveRule($missingPath, $rules, $ruleCache),
            null,
        );
    }

    /**
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @param array<string, RuleInterface|null> $ruleCache
     */
    private function resolveRule(string $path, RuleCollectorInterface $rules, array &$ruleCache): ?RuleInterface
    {
        if (array_key_exists($path, $ruleCache)) {
            return $ruleCache[$path];
        }

        if ($rules->has($path)) {
            return $ruleCache[$path] = $rules->get($path);
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

        return $ruleCache[$path] = $best;
    }

    private function hasDescendantRule(string $path, RuleCollectorInterface $rules): bool
    {
        $pathSegments = explode('.', $path);

        foreach (array_keys($rules->toArray()) as $pattern) {
            $patternSegments = explode('.', $pattern);
            if (count($patternSegments) <= count($pathSegments)) {
                continue;
            }

            foreach ($pathSegments as $index => $segment) {
                $patternSegment = $patternSegments[$index] ?? null;
                if ($patternSegment !== '*' && $patternSegment !== $segment) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
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
