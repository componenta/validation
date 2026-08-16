<?php

declare(strict_types=1);

namespace Componenta\Validation\Definition;

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\ValidatorInterface;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

/**
 * Extracts exportable validation metadata without retaining reflection objects.
 *
 * @phpstan-type RuleDescriptor array{kind: 'definition', definition: string}|array{
 *     kind: 'rule'|'rule_attribute',
 *     class: class-string,
 *     arguments: array<array-key, mixed>
 * }
 * @phpstan-type RulesDefinition array{
 *     kind: 'rules',
 *     fields: array<string, non-empty-list<RuleDescriptor>>
 * }
 * @phpstan-type ValidatorServiceDefinition array{
 *     kind: 'validator',
 *     class: class-string<ValidatorInterface>
 * }
 */
final readonly class AttributeValidatorDefinitionExtractor
{
    /**
     * @param class-string|ReflectionClass<object> $class
     * @return array<string, mixed>|null
     */
    public function extract(string|ReflectionClass $class): ?array
    {
        $reflection = is_string($class) ? new ReflectionClass($class) : $class;
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            $descriptors = $this->propertyDescriptors($property);
            if ($descriptors === []) {
                continue;
            }

            $field = $this->fieldName($property);
            if ($field === '') {
                throw new InvalidArgumentException(sprintf(
                    'Validation field name for "%s::$%s" must not be empty.',
                    $reflection->getName(),
                    $property->getName(),
                ));
            }

            if (array_key_exists($field, $fields)) {
                throw new InvalidArgumentException(sprintf(
                    'Validation field "%s" is declared by more than one property of "%s".',
                    $field,
                    $reflection->getName(),
                ));
            }

            $fields[$field] = $descriptors;
        }

        $validatedBy = $reflection->getAttributes(ValidatedBy::class);
        if (count($validatedBy) > 1) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" declares #[ValidatedBy] more than once.',
                $reflection->getName(),
            ));
        }

        if ($fields !== [] && $validatedBy !== []) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" cannot combine #[ValidatedBy] with property validation attributes.',
                $reflection->getName(),
            ));
        }

        if ($validatedBy !== []) {
            /** @var ValidatedBy $attribute */
            $attribute = $validatedBy[0]->newInstance();
            $validator = $attribute->validator;

            if (!class_exists($validator) || !is_a($validator, ValidatorInterface::class, true)) {
                throw new InvalidArgumentException(sprintf(
                    '#[ValidatedBy] on "%s" must reference a class implementing %s; got "%s".',
                    $reflection->getName(),
                    ValidatorInterface::class,
                    $validator,
                ));
            }

            return [
                'kind' => 'validator',
                'class' => $validator,
            ];
        }

        return $fields === [] ? null : [
            'kind' => 'rules',
            'fields' => $fields,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function propertyDescriptors(ReflectionProperty $property): array
    {
        $descriptors = [];

        foreach ($property->getAttributes(RuleInterface::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $attribute->newInstance();
            $descriptors[] = [
                'kind' => 'rule',
                'class' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        foreach ($property->getAttributes(Validate::class) as $attribute) {
            /** @var Validate $validate */
            $validate = $attribute->newInstance();
            $descriptors[] = [
                'kind' => 'definition',
                'definition' => $validate->rules,
            ];
        }

        foreach ($property->getAttributes(RuleAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $attribute->newInstance();
            $descriptors[] = [
                'kind' => 'rule_attribute',
                'class' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        return $descriptors;
    }

    private function fieldName(ReflectionProperty $property): string
    {
        $fieldAttributes = $property->getAttributes(Field::class);
        if (count($fieldAttributes) > 1) {
            throw new InvalidArgumentException(sprintf(
                'Property "%s::$%s" declares #[Field] more than once.',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));
        }

        if ($fieldAttributes !== []) {
            /** @var Field $field */
            $field = $fieldAttributes[0]->newInstance();
            return $field->name;
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
