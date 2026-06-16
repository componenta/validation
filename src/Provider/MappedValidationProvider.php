<?php

namespace Componenta\Validation\Provider;

use Componenta\Validation\ValidatorInterface;
use Psr\Container\ContainerInterface;

/**
 * Validation provider using static mapping.
 *
 * Resolves validators from container based on explicitly registered mappings.
 * Requires manual registration of entity-to-validator mappings.
 */
final class MappedValidationProvider implements ValidationProviderInterface
{
    /** @var array<string, class-string<ValidatorInterface>> */
    private array $validators = [];

    /**
     * @param ContainerInterface $container DI container for resolving validators
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (!isset($this->validators[$entryId])) {
            return null;
        }

        return $this->container->get($this->validators[$entryId]);
    }

    /**
     * Register mapping between entity and validator.
     *
     * @param string $id Entity identifier (typically class name)
     * @param class-string<ValidatorInterface> $validatorClass Validator class name
     */
    public function register(string $id, string $validatorClass): void
    {
        $this->validators[$id] = $validatorClass;
    }

    /**
     * Check if validator registered for given identifier.
     *
     * @param string $id Entity identifier
     * @return bool True if validator registered
     */
    public function has(string $id): bool
    {
        return isset($this->validators[$id]);
    }
}

