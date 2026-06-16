<?php

namespace Componenta\Validation\Provider;

use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\ValidatorInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * Validation provider using ValidatedBy attribute.
 *
 * Scans classes for ValidatedBy attribute and resolves validator from container.
 * Provides automatic validator discovery for classes marked with ValidatedBy.
 */
final readonly class ValidatedByProvider implements ValidationProviderInterface
{
    /**
     * @param ContainerInterface $container DI container for resolving validators
     */
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (!class_exists($entryId)) {
            return null;
        }

        $validatorClass = $this->scanValidatedByAttribute($entryId);

        if ($validatorClass === null) {
            return null;
        }

        return $this->container->get($validatorClass);
    }

    /**
     * Scan class for ValidatedBy attribute and extract validator class.
     *
     * @param class-string $className Class name to scan
     * @return class-string<ValidatorInterface>|null Validator class or null
     */
    private function scanValidatedByAttribute(string $className): ?string
    {
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(ValidatedBy::class);

        if ($attributes === []) {
            return null;
        }

        /** @var ValidatedBy $validatedBy */
        $validatedBy = $attributes[0]->newInstance();

        return $validatedBy->validator;
    }
}
