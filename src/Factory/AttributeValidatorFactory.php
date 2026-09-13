<?php
declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\RuleAttribute;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\Internal\AttributeMetadata;
use Componenta\Validation\Rule\RuleComposer;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\ValidatorInterface;
use InvalidArgumentException;
use ReflectionClass;

/** @internal
 *  @phpstan-import-type Metadata from AttributeMetadata
 */
final readonly class AttributeValidatorFactory
{
    public function __construct(
        private ValidatorFactoryInterface $validators,
        private RuleFactoryInterface $rules,
    ) {}

    /** @param ReflectionClass<object> $class
     *  @param Metadata $metadata
     */
    public function create(ReflectionClass $class, array $metadata): ?ValidatorInterface
    {
        $fields = [];
        foreach ($metadata['properties'] as $propertyName => $positions) {
            $attributes = $class->getProperty($propertyName)->getAttributes();
            $instances = [];
            $instance = static function (int $position) use ($attributes, &$instances): object {
                return $instances[$position] ??= $attributes[$position]->newInstance();
            };
            $fieldRules = [];
            foreach ($positions['rules'] as $position) {
                /** @var RuleInterface $rule */
                $rule = $instance($position);
                $fieldRules[] = $rule;
            }
            $alias = null;
            foreach ($positions['validate'] as $position) {
                /** @var Validate $validate */
                $validate = $instance($position);
                $fieldRules[] = $this->rules->createRule($validate->rules);
                $alias ??= $validate->as;
            }
            foreach ($positions['attributes'] as $position) {
                /** @var RuleAttribute $rule */
                $rule = $instance($position);
                $fieldRules[] = $rule->buildRule($this->rules);
            }

            if (count($positions['field']) > 1) {
                throw new InvalidArgumentException(sprintf(
                    'Property "%s::$%s" declares #[Field] more than once.', $class->getName(), $propertyName,
                ));
            }
            $field = $alias ?? $propertyName;
            if ($positions['field'] !== []) {
                /** @var Field $attribute */
                $attribute = $instance($positions['field'][0]);
                $field = $attribute->name;
            }
            if ($field === '') {
                throw new InvalidArgumentException(sprintf(
                    'Validation field name for "%s::$%s" must not be empty.', $class->getName(), $propertyName,
                ));
            }
            if (array_key_exists($field, $fields)) {
                throw new InvalidArgumentException(sprintf(
                    'Validation field "%s" is declared by more than one property of "%s".', $field, $class->getName(),
                ));
            }
            $fields[$field] = RuleComposer::all(...$fieldRules);
        }

        $validatedBy = $metadata['validated_by'];
        if (count($validatedBy) > 1) {
            throw new InvalidArgumentException(sprintf('Class "%s" declares #[ValidatedBy] more than once.', $class->getName()));
        }
        if ($fields !== [] && $validatedBy !== []) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" cannot combine #[ValidatedBy] with property validation attributes.', $class->getName(),
            ));
        }
        if ($validatedBy !== []) {
            /** @var ValidatedBy $attribute */
            $attribute = $class->getAttributes()[$validatedBy[0]]->newInstance();
            $service = $attribute->validator;
            self::assertService($service, $class->getName());
            return $this->validators->create($service);
        }
        return $fields === [] ? null : $this->validators->createFrom($fields);
    }

    private static function assertService(string $service, string $entry): void
    {
        if (!is_a($service, ValidatorInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(
                '#[ValidatedBy] on "%s" must reference a class or interface implementing %s; got "%s".',
                $entry, ValidatorInterface::class, $service,
            ));
        }
    }
}
