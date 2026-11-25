<?php

declare(strict_types=1);

namespace Componenta\Validation\Internal;

use Componenta\Validation\Rule\RuleCollectorInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\Rules;

/** @internal Matches rule paths during one traversal. */
final class RulePathMatcher
{
    /** @var array<array-key, non-empty-list<string>> */
    private array $segments = [];

    /** @var array<string, array<int, string>> */
    private array $literals = [];

    /** @var array<int, array<array-key, RuleInterface>>|null */
    private ?array $wildcardsByDepth = null;

    /** @param RuleCollectorInterface<RuleInterface> $rules */
    public function __construct(private readonly RuleCollectorInterface $rules) {}

    /** @return non-empty-list<string> */
    public function segments(string $pattern): array
    {
        return $this->segments[$pattern] ??= explode('.', $pattern);
    }

    public function resolveWildcard(string $path): ?RuleInterface
    {
        // Only the built-in collection guarantees stable rule membership for the entire walk.
        $index = $this->rules instanceof Rules ? ($this->wildcardsByDepth ??= $this->indexWildcards()) : null;
        if ($index === []) {
            return null;
        }

        $pathSegments = explode('.', $path);
        $depth = count($pathSegments);
        $candidates = $index === null ? $this->rules->toArray() : ($index[$depth] ?? []);
        $best = null;
        $bestSpecificity = -1;

        foreach ($candidates as $pattern => $rule) {
            $pattern = (string) $pattern;
            if ($index === null && (!str_contains($pattern, '*') || count($this->segments($pattern)) !== $depth)) {
                continue;
            }

            $literals = $this->literals[$pattern] ??= array_diff($this->segments($pattern), ['*']);
            $specificity = count($literals);
            if ($specificity <= $bestSpecificity) {
                continue;
            }

            foreach ($literals as $position => $segment) {
                if ($segment !== $pathSegments[$position]) {
                    continue 2;
                }
            }

            $best = $rule;
            $bestSpecificity = $specificity;
        }

        return $best;
    }

    public function hasDescendant(string $path): bool
    {
        $pathSegments = explode('.', $path);
        $depth = count($pathSegments);

        foreach (array_keys($this->rules->toArray()) as $pattern) {
            $patternSegments = $this->segments((string) $pattern);
            if (count($patternSegments) <= $depth) {
                continue;
            }

            foreach ($pathSegments as $index => $segment) {
                if ($patternSegments[$index] !== '*' && $patternSegments[$index] !== $segment) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /** @return array<int, array<array-key, RuleInterface>> */
    private function indexWildcards(): array
    {
        $index = [];
        foreach ($this->rules->toArray() as $pattern => $rule) {
            $pattern = (string) $pattern;
            if (str_contains($pattern, '*')) {
                $index[count($this->segments($pattern))][$pattern] = $rule;
            }
        }

        return $index;
    }
}
