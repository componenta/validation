<?php
declare(strict_types=1);

namespace Componenta\Validation\Provider;

use Componenta\Validation\Factory\AttributeValidatorFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Internal\AttributeMetadata;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\ValidatorInterface;
use ReflectionClass;

/** Provides fresh native attribute rules and resolves #[ValidatedBy] services. */
final readonly class AttributeValidationProvider implements ValidationProviderInterface
{
    private AttributeValidatorFactory $factory;

    public function __construct(ValidatorFactoryInterface $validatorFactory, RuleFactoryInterface $ruleFactory)
    {
        $this->factory = new AttributeValidatorFactory($validatorFactory, $ruleFactory);
    }

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (!class_exists($entryId)) {
            return null;
        }
        $class = new ReflectionClass($entryId);
        return $this->factory->create($class, AttributeMetadata::inspect($class));
    }
}
