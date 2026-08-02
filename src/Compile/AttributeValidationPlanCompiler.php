<?php

declare(strict_types=1);

namespace Componenta\Validation\Compile;

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Rule\RuleInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

/**
 * Converts validation attributes into scalar constructor descriptors during
 * the application build. Runtime providers can then build rules without
 * scanning class metadata through Reflection.
 */
final readonly class AttributeValidationPlanCompiler
{
    /**
     * @param iterable<mixed> $classes
     * @return array<class-string, array<string, list<array<string, mixed>>>>
     */
    public function compile(iterable $classes): array
    {
        $plans = [];

        foreach ($classes as $class) {
            if (!is_string($class) || !class_exists($class)) {
                continue;
            }

            $classPlan = $this->compileClass(new ReflectionClass($class));
            if ($classPlan !== []) {
                $plans[$class] = $classPlan;
            }
        }

        ksort($plans);

        return $plans;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, list<array<string, mixed>>>
     */
    private function compileClass(ReflectionClass $reflection): array
    {
        $plan = [];

        foreach ($reflection->getProperties() as $property) {
            $rules = $this->compileProperty($property);
            if ($rules !== []) {
                $plan[$this->fieldName($property)] = $rules;
            }
        }

        return $plan;
    }

    /** @return list<array<string, mixed>> */
    private function compileProperty(ReflectionProperty $property): array
    {
        $descriptors = [];

        foreach ($property->getAttributes(RuleInterface::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $descriptors[] = [
                'type' => 'rule',
                'class' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        foreach ($property->getAttributes(Validate::class) as $attribute) {
            /** @var Validate $validate */
            $validate = $attribute->newInstance();
            $descriptors[] = ['type' => 'definition', 'rules' => $validate->rules];
        }

        foreach ($property->getAttributes(RuleAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $descriptors[] = [
                'type' => 'rule_attribute',
                'class' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        return $descriptors;
    }

    private function fieldName(ReflectionProperty $property): string
    {
        $field = $property->getAttributes(Field::class)[0] ?? null;
        if ($field !== null) {
            /** @var Field $fieldAttribute */
            $fieldAttribute = $field->newInstance();
            return $fieldAttribute->name;
        }

        foreach ($property->getAttributes(Validate::class) as $attribute) {
            /** @var Validate $validate */
            $validate = $attribute->newInstance();
            if ($validate->as !== null) {
                return $validate->as;
            }
        }

        return $property->getName();
    }
}
