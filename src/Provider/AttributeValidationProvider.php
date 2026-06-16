<?php

namespace Componenta\Validation\Provider;

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\IfThen;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\ValidatorInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

/**
 * Validation provider using property attributes.
 *
 * Scans class properties for validation attributes and creates validator.
 * Supports three types of attributes: direct rule attributes, string syntax via Validate,
 * and RuleAttribute for rules with complex dependencies.
 * Caches created validators per class.
 */
final class AttributeValidationProvider implements ValidationProviderInterface
{
    /** @var array<string, ValidatorInterface> */
    private array $cache = [];

    /**
     * @param RuleFactoryInterface $ruleFactory Rule factory for creating rule instances
     */
    public function __construct(
        private readonly ValidatorFactoryInterface $validatorFactory,
        private readonly RuleFactoryInterface $ruleFactory,
    ) {}

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (isset($this->cache[$entryId])) {
            return $this->cache[$entryId];
        }

        if (!class_exists($entryId)) {
            return null;
        }

        $rules = $this->extractRules($entryId);

        if ($rules === []) {
            return null;
        }

        return $this->cache[$entryId] = $this->validatorFactory->createFrom($rules);
    }

    /**
     * @return array<string, RuleInterface>
     */
    private function extractRules(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $rules = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyRules = $this->extractPropertyRules($property);

            if ($propertyRules !== null) {
                $fieldName = $this->resolveFieldName($property);
                $rules[$fieldName] = $propertyRules;
            }
        }

        return $rules;
    }

    /**
     * Resolve field name for validation rules mapping.
     *
     * Priority:
     * 1. #[Field('field_name')] attribute (highest priority)
     * 2. #[Validate(..., as: 'field_name')] parameter
     * 3. Property name (default)
     */
    private function resolveFieldName(ReflectionProperty $property): string
    {
        // Check for #[Field] attribute (highest priority)
        $fieldAttributes = $property->getAttributes(Field::class);
        if ($fieldAttributes !== []) {
            /** @var Field $field */
            $field = $fieldAttributes[0]->newInstance();
            return $field->name;
        }

        // Check for #[Validate] with 'as' parameter
        $validateAttributes = $property->getAttributes(Validate::class);
        foreach ($validateAttributes as $attribute) {
            /** @var Validate $validate */
            $validate = $attribute->newInstance();
            if ($validate->as !== null) {
                return $validate->as;
            }
        }

        // Default: use property name
        return $property->getName();
    }

    private function extractPropertyRules(ReflectionProperty $property): ?RuleInterface
    {
        $rules = [];
        $nullableRule = null;

        // Extract RuleInterface attributes (simple rules without dependencies)
        foreach ($property->getAttributes(RuleInterface::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $rule = $attribute->newInstance();

            if ($nullableRule === null && $rule instanceof Nullable) {
                $nullableRule = $rule;
                continue;
            }

            $rules[] = $rule;
        }

        // Extract #[Validate('...')] string rules
        foreach ($property->getAttributes(Validate::class) as $attribute) {
            /** @var Validate $validate */
            $validate = $attribute->newInstance();
            $rule = $this->ruleFactory->createRule($validate->rules);

            if ($nullableRule === null && $rule instanceof Nullable) {
                $nullableRule = $rule;
                continue;
            }

            $rules[] = $rule;
        }

        // Extract RuleAttribute for rules with dependencies (Unique, Exists, etc.)
        foreach ($property->getAttributes(RuleAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            /** @var RuleAttribute $ruleAttr */
            $ruleAttr = $attribute->newInstance();
            $rule = $ruleAttr->buildRule($this->ruleFactory);

            if ($nullableRule === null && $rule instanceof Nullable) {
                $nullableRule = $rule;
                continue;
            }

            $rules[] = $rule;
        }

        $countRules = count($rules);

        if ($countRules === 0) {
            return $nullableRule;
        }

        if ($countRules === 1) {
            if ($nullableRule !== null) {
                return new IfThen($nullableRule->inverse(...), $rules[0]);
            }

            return $rules[0];
        }

        return $nullableRule !== null ? new IfThen($nullableRule->inverse(...), new AllOf(...$rules))
            : new AllOf(...$rules);
    }
}
