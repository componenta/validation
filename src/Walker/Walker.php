<?php

namespace Componenta\Validation\Walker;

use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;
use Generator;

/**
 * Walks over data recursively and yields Target objects with correct rules.
 *
 * Supports exact keys and wildcards (e.g., "users.*.addresses.*").
 *
 * Optimizations:
 * - No internal state; rules passed recursively.
 * - Works directly with iterable without unnecessary conversion.
 * - Caches resolved rules for paths to speed up repeated lookups.
 */
final class Walker implements WalkerInterface
{
    /**
     * Cache of path => [PolicyInterface|null, hasWildcardChild: bool]
     * @var array<string, array{0: RuleInterface|null, 1: bool}>
     */
    private array $ruleCache = [];

    /**
     * @param iterable $data Input data
     * @param RuleCollectorInterface $rules Rules collection
     * @return Generator<Target>
     */
    public function walk(iterable $data, RuleCollectorInterface $rules): Generator
    {
        $this->ruleCache = []; // Reset cache for each walk

        yield from $this->walkData($data, $rules, '');
        yield from $this->walkMissingRules($data, $rules);
    }

    /**
     * Walk actual data and yield Targets.
     *
     * @param iterable $data
     * @param RuleCollectorInterface $rules
     * @param string $parentPath
     * @return Generator<Target>
     */
    private function walkData(iterable $data, RuleCollectorInterface $rules, string $parentPath): Generator
    {
        foreach ($data as $key => $value) {
            $currentPath = $parentPath === '' ? (string) $key : $parentPath . '.' . $key;

            [$rule, $hasWildcardChild] = $this->resolveRule($currentPath, $rules);

            // If this is a container with only wildcard children, recurse without yielding
            if ($rule === null && $hasWildcardChild && $this->isIterable($value)) {
                yield from $this->walkData($value, $rules, $currentPath);
                continue;
            }

            yield new Target((string) $key, $currentPath, $rule, $value);

            if ($this->isIterable($value)) {
                yield from $this->walkData($value, $rules, $currentPath);
            }
        }
    }

    /**
     * Yield Targets for missing fields that exist in rules but not in data.
     *
     * Only simple (non-dot, non-wildcard) root-level keys are handled here.
     *
     * @param iterable $data
     * @param RuleCollectorInterface $rules
     * @return Generator<Target>
     */
    private function walkMissingRules(iterable $data, RuleCollectorInterface $rules): Generator
    {
        $arrayData = is_array($data) ? $data : iterator_to_array($data);

        foreach ($rules->toArray() as $ruleKey => $ruleItem) {
            if (str_contains($ruleKey, '*') || str_contains($ruleKey, '.')) {
                continue;
            }

            if (!array_key_exists($ruleKey, $arrayData)) {
                yield new Target($ruleKey, $ruleKey, $ruleItem, null);
            }
        }
    }

    /**
     * Resolve rule for a given path, including wildcard matches.
     *
     * Supports multi-wildcard patterns like "users.*.addresses.*".
     *
     * Returns [Rule|null, hasWildcardChild: bool]
     *
     * @param string $path
     * @param RuleCollectorInterface $rules
     * @return array{0: RuleInterface|null, 1: bool}
     */
    private function resolveRule(string $path, RuleCollectorInterface $rules): array
    {
        if (isset($this->ruleCache[$path])) {
            return $this->ruleCache[$path];
        }

        // Exact match
        if ($rules->has($path)) {
            $result = [$rules->get($path), $this->hasWildcardChild($path, $rules)];
            $this->ruleCache[$path] = $result;
            return $result;
        }

        // Generate all possible wildcard patterns
        $segments = explode('.', $path);
        $patterns = $this->generateWildcardPatterns($segments);

        foreach ($patterns as $pattern) {
            if ($rules->has($pattern)) {
                $result = [$rules->get($pattern), $this->hasWildcardChild($path, $rules)];
                $this->ruleCache[$path] = $result;
                return $result;
            }
        }

        $result = [null, $this->hasWildcardChild($path, $rules)];
        $this->ruleCache[$path] = $result;
        return $result;
    }

    /**
     * Generate all possible wildcard patterns for a path.
     *
     * For path "users.0.addresses.1.city", generates patterns like:
     * - users.*.addresses.1.city
     * - users.0.addresses.*.city
     * - users.*.addresses.*.city
     * - etc.
     *
     * Patterns are ordered by specificity (fewer wildcards first).
     *
     * @param array<int, string> $segments
     * @return Generator<string>
     */
    private function generateWildcardPatterns(array $segments): Generator
    {
        $numericIndices = [];
        foreach ($segments as $i => $segment) {
            if (is_numeric($segment)) {
                $numericIndices[] = $i;
            }
        }

        if (empty($numericIndices)) {
            return;
        }

        // Generate all combinations of wildcard replacements
        // Start with single wildcards, then pairs, etc. (ordered by specificity)
        $count = count($numericIndices);

        for ($numWildcards = 1; $numWildcards <= $count; $numWildcards++) {
            foreach ($this->combinations($numericIndices, $numWildcards) as $combo) {
                $pattern = $segments;
                foreach ($combo as $idx) {
                    $pattern[$idx] = '*';
                }
                yield implode('.', $pattern);
            }
        }
    }

    /**
     * Generate all k-combinations of an array.
     *
     * @param array<int, int> $items
     * @param int $k
     * @return Generator<array<int, int>>
     */
    private function combinations(array $items, int $k): Generator
    {
        $n = count($items);
        if ($k > $n) {
            return;
        }

        $indices = range(0, $k - 1);

        yield array_map(fn($i) => $items[$i], $indices);

        while (true) {
            $i = $k - 1;
            while ($i >= 0 && $indices[$i] === $n - $k + $i) {
                $i--;
            }

            if ($i < 0) {
                break;
            }

            $indices[$i]++;
            for ($j = $i + 1; $j < $k; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }

            yield array_map(fn($idx) => $items[$idx], $indices);
        }
    }

    /**
     * Check if there's a wildcard child rule for the given path.
     *
     * @param string $path
     * @param RuleCollectorInterface $rules
     * @return bool
     */
    private function hasWildcardChild(string $path, RuleCollectorInterface $rules): bool
    {
        return $rules->has($path . '.*');
    }

    /**
     * Checks if a value is iterable and not a string.
     *
     * @param mixed $value
     * @return bool
     */
    private function isIterable(mixed $value): bool
    {
        return is_iterable($value) && !is_string($value);
    }
}
