<?php

declare(strict_types=1);

namespace Componenta\Validation\Walker;

use Componenta\Validation\Internal\RulePathMatcher;
use Componenta\Validation\Internal\ValidationData;
use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;
use Generator;

/** Walks actual and missing values for exact and wildcard rule paths. */
final class Walker implements WalkerInterface
{
    public function walk(iterable $data, RuleCollectorInterface $rules): Generator
    {
        yield from $this->walkMaterialized(ValidationData::materialize($data), $rules);
    }

    /**
     * @internal Input has already been materialized by the validator.
     * @param array<array-key, mixed> $data
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @return Generator<int, Target>
     */
    public function walkMaterialized(array $data, RuleCollectorInterface $rules): Generator
    {
        $ruleCache = [];
        $yieldedPaths = [];
        $matcher = null;

        yield from $this->walkData($data, $rules, '', $ruleCache, $yieldedPaths, $matcher);

        foreach (array_keys($rules->toArray()) as $pattern) {
            $pattern = (string) $pattern;
            if (isset($yieldedPaths[$pattern]) && !str_contains($pattern, '*')) {
                continue;
            }

            yield from $this->walkMissingPattern(
                $data,
                $matcher?->segments($pattern) ?? explode('.', $pattern),
                0,
                '',
                $rules,
                $ruleCache,
                $yieldedPaths,
                $matcher,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @param array<array-key, RuleInterface|null> $ruleCache
     * @param array<array-key, true> $yieldedPaths
     * @return Generator<int, Target>
     */
    private function walkData(
        array $data,
        RuleCollectorInterface $rules,
        string $parentPath,
        array &$ruleCache,
        array &$yieldedPaths,
        ?RulePathMatcher &$matcher,
    ): Generator
    {
        foreach ($data as $key => $value) {
            $field = (string) $key;
            $path = $parentPath === '' ? $field : $parentPath . '.' . $field;
            $rule = $this->resolveRule($path, $rules, $ruleCache, $matcher);
            $yieldedPaths[$path] = true;

            // Containers that only lead to descendant rules are traversal nodes,
            // not validation targets of their own. This preserves strict
            // SKIP_MISSING_RULES=false behavior for paths such as items.*.sku.
            if ($rule !== null
                || !is_array($value)
                || !($matcher ??= new RulePathMatcher($rules))->hasDescendant($path)
            ) {
                yield new Target($field, $path, $rule, $value);
            }

            if (is_array($value)) {
                yield from $this->walkData($value, $rules, $path, $ruleCache, $yieldedPaths, $matcher);
            }
        }
    }

    /**
     * @param non-empty-list<string> $segments
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @param array<array-key, RuleInterface|null> $ruleCache
     * @param array<array-key, true> $yieldedPaths
     * @return Generator<int, Target>
     */
    private function walkMissingPattern(
        mixed $value,
        array $segments,
        int $index,
        string $parentPath,
        RuleCollectorInterface $rules,
        array &$ruleCache,
        array &$yieldedPaths,
        ?RulePathMatcher &$matcher,
    ): Generator {
        $segment = $segments[$index] ?? null;
        if ($segment === null) {
            return;
        }

        $last = $index === count($segments) - 1;

        if ($segment === '*') {
            if ($last || !is_array($value)) {
                return;
            }

            foreach ($value as $key => $child) {
                $path = $parentPath === '' ? (string) $key : $parentPath . '.' . $key;
                yield from $this->walkMissingPattern(
                    $child,
                    $segments,
                    $index + 1,
                    $path,
                    $rules,
                    $ruleCache,
                    $yieldedPaths,
                    $matcher,
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
                    $matcher,
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
            $this->resolveRule($missingPath, $rules, $ruleCache, $matcher),
            null,
        );
    }

    /**
     * @param RuleCollectorInterface<RuleInterface> $rules
     * @param array<array-key, RuleInterface|null> $ruleCache
     */
    private function resolveRule(
        string $path,
        RuleCollectorInterface $rules,
        array &$ruleCache,
        ?RulePathMatcher &$matcher,
    ): ?RuleInterface {
        if (array_key_exists($path, $ruleCache)) {
            return $ruleCache[$path];
        }

        if ($rules->has($path)) {
            return $ruleCache[$path] = $rules->get($path);
        }

        $matcher ??= new RulePathMatcher($rules);

        return $ruleCache[$path] = $matcher->resolveWildcard($path);
    }
}
