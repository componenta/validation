<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Config\Config;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Provider\CompositeValidationProvider;
use Componenta\Validation\Provider\MappedValidationProvider;
use Componenta\Validation\Provider\ValidatableProvider;
use Componenta\Validation\Provider\ValidatedByProvider;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Factory for creating composite validation provider.
 *
 * Creates provider chain with different strategies based on environment.
 * In development mode includes attribute scanning providers for convenience.
 * In production mode uses only explicitly registered validators for performance.
 */
final readonly class ValidationProviderFactory
{
    /**
     * Create validation provider with configured provider chain.
     *
     * @param ContainerInterface $container DI container
     * @return CompositeValidationProvider Configured validation provider
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CompositeValidationProvider
    {
        /** @var Config $config */
        $config = $container->get(ConfigKey::CONFIG);

        $devMode = !$config?->environment->bool('production', false) ?? true;

        $provider = new CompositeValidationProvider(
            new ValidatableProvider($container->get(ValidatorFactoryInterface::class)),
            $mappedProvider = new MappedValidationProvider($container)
        );

        // Register static mappings from configuration
        foreach ($config->array(ConfigKey::VALIDATORS_MAP, []) as $entry => $validator) {
            $mappedProvider->register($entry, $validator);
        }

        // Add attribute-based providers in development mode
        if ($devMode) {
            $provider->add(
                new AttributeValidationProvider(
                    $container->get(ValidatorFactoryInterface::class),
                    $container->get(RuleFactoryInterface::class))
            );

            $provider->add(new ValidatedByProvider($container));
        }

        return $provider;
    }
}
