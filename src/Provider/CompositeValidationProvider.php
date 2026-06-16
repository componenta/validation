<?php

namespace Componenta\Validation\Provider;

use Componenta\Validation\ValidatorInterface;

/**
 * Composite validation provider delegating to multiple providers.
 *
 * Implements Chain of Responsibility pattern.
 * Tries each registered provider sequentially until one returns a validator.
 * Allows dynamic addition and prioritization of providers.
 */
final class CompositeValidationProvider implements ValidationProviderInterface
{
    /** @var array<int, ValidationProviderInterface> */
    private array $providers;

    /**
     * @param ValidationProviderInterface ...$providers Initial providers in priority order
     */
    public function __construct(ValidationProviderInterface ...$providers)
    {
        $this->providers = $providers;
    }

    public function provide(string $entryId): ?ValidatorInterface
    {
        foreach ($this->providers as $provider) {
            $validator = $provider->provide($entryId);

            if ($validator !== null) {
                return $validator;
            }
        }

        return null;
    }

    /**
     * Add provider to end of chain.
     *
     * @param ValidationProviderInterface $provider Provider to add
     */
    public function add(ValidationProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Add provider to beginning of chain with highest priority.
     *
     * @param ValidationProviderInterface $provider Provider to prepend
     */
    public function prepend(ValidationProviderInterface $provider): void
    {
        array_unshift($this->providers, $provider);
    }
}
