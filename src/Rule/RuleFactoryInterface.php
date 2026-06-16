<?php

namespace Componenta\Validation\Rule;

/**
 * Factory interface for creating validation rules from string syntax.
 *
 * Supports pipe-separated rule definitions and nested rule structures.
 */
interface RuleFactoryInterface
{
    /**
     * Create rule instance from string definition.
     *
     * Parses string syntax and creates appropriate rule instance with dependencies resolved.
     * Supports single rules, combined rules, and rules with parameters.
     *
     * @param string $definition Rule definition string
     * @return RuleInterface Rule instance
     */
    public function createRule(string $definition): RuleInterface;

    /**
     * Create multiple rules from field-to-definition map.
     *
     * @param array<string, string> $definitions Map of field names to rule definitions
     * @return array<string, RuleInterface> Map of field names to rule instances
     */
    public function createRules(array $definitions): array;
}
