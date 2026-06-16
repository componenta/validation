<?php

namespace Componenta\Validation\Rule;

use OutOfBoundsException;

/**
 * Trait for rule collections implementing RuleCollectionInterface
 */
trait RuleCollector
{
    /** @var array<string, RuleInterface> */
    private array $rules = [];

    public string $name {
        get => implode('|', array_map(
            static fn(RuleInterface $r): string => $r->name,
            $this->rules
        ));
    }

    /**
     * @param iterable<string, RuleInterface> $rules
     */
    public function __construct(iterable $rules)
    {
        foreach ($rules as $key => $rule) {
            if (!$rule instanceof RuleInterface) {
                throw new \InvalidArgumentException("Rule at key '$key' must implement PolicyInterface");
            }
            $this->rules[$key] = $rule;
        }
    }

    /**
     * Returns a new collection with the rule added.
     * Uses rule->name as the key.
     */
    public function withRule(RuleInterface $rule): RuleCollectorInterface
    {
        $copy = clone $this;
        $copy->rules[$rule->name] = $rule;
        return $copy;
    }

    /**
     * Returns a new collection with all provided rules.
     *
     * @param iterable<string, RuleInterface> $rules
     */
    public function withRules(iterable $rules): RuleCollectorInterface
    {
        return new static($rules);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->rules);
    }

    public function get(string $name): RuleInterface
    {
        if (array_key_exists($name, $this->rules)) {
            return $this->rules[$name];
        }

        throw new OutOfBoundsException("Rule '$name' not found in collection");
    }

    /**
     * @return array<string, RuleInterface>
     */
    public function toArray(): array
    {
        return $this->rules;
    }

    /**
     * @return \Generator<string, RuleInterface>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->rules as $key => $rule) {
            yield $key => $rule;
        }
    }

    public function count(): int
    {
        return count($this->rules);
    }
}
