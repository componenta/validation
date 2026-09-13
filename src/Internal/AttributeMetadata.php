<?php
declare(strict_types=1);

namespace Componenta\Validation\Internal;

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\Rule\RuleInterface;
use ReflectionClass;

/**
 * @internal Native attribute locations, never evaluated arguments or rule instances.
 * @phpstan-type PropertyMetadata array{rules: list<int>, validate: list<int>, attributes: list<int>, field: list<int>}
 * @phpstan-type Metadata array{properties: array<string, PropertyMetadata>, validated_by: list<int>, complete: bool}
 */
final class AttributeMetadata
{
    /** @param ReflectionClass<object> $class
     *  @return Metadata
     */
    public static function inspect(ReflectionClass $class): array
    {
        $properties = [];
        $complete = true;
        foreach ($class->getProperties() as $property) {
            $locations = ['rules' => [], 'validate' => [], 'attributes' => [], 'field' => []];
            foreach ($property->getAttributes() as $position => $attribute) {
                $name = $attribute->getName();
                if (is_a($name, RuleInterface::class, true)) {
                    $locations['rules'][] = $position;
                }
                if (is_a($name, Validate::class, true)) {
                    $locations['validate'][] = $position;
                }
                if (is_a($name, RuleAttribute::class, true)) {
                    $locations['attributes'][] = $position;
                }
                if (is_a($name, Field::class, true)) {
                    $locations['field'][] = $position;
                }
                if (!self::isLoaded($name)) {
                    $complete = false;
                }
            }
            if ($locations['rules'] !== [] || $locations['validate'] !== [] || $locations['attributes'] !== []) {
                $properties[$property->getName()] = $locations;
            }
        }
        $validatedBy = [];
        foreach ($class->getAttributes() as $position => $attribute) {
            if (is_a($attribute->getName(), ValidatedBy::class, true)) {
                $validatedBy[] = $position;
            }
            if (!self::isLoaded($attribute->getName())) {
                $complete = false;
            }
        }
        return ['properties' => $properties, 'validated_by' => $validatedBy, 'complete' => $complete];
    }
    private static function isLoaded(string $name): bool
    {
        return class_exists($name, false) || interface_exists($name, false) || trait_exists($name, false);
    }
}
