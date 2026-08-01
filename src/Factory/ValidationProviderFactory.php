<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Config\Config;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Provider\CompiledAttributeValidationProvider;
use Componenta\Validation\Provider\CompositeValidationProvider;
use Componenta\Validation\Provider\MappedValidationProvider;
use Componenta\Validation\Provider\ValidatableProvider;
use Componenta\Validation\Provider\ValidatedByProvider;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Factory for creating the composite validation provider.
 *
 * Attribute-based providers remain development-only. When available, compiled
 * plans replace reflection for discovered classes without changing that policy.
 */
final readonly class ValidationProviderFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CompositeValidationProvider
    {
        /** @var Config $config */
        $config = $container->get(ConfigKey::CONFIG);
        /** @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $container->get(ValidatorFactoryInterface::class);
        $devMode = !$config->environment?->bool('production', false) ?? true;

        $provider = new CompositeValidationProvider(
            new ValidatableProvider($validatorFactory),
            $mappedProvider = new MappedValidationProvider($container),
        );

        foreach ($config->array(ConfigKey::VALIDATORS_MAP, []) as $entry => $validator) {
            $mappedProvider->register($entry, $validator);
        }

        if ($devMode) {
            /** @var RuleFactoryInterface $ruleFactory */
            $ruleFactory = $container->get(RuleFactoryInterface::class);
            $attributeProvider = new AttributeValidationProvider($validatorFactory, $ruleFactory);
            $compiledPlans = $config->array(ConfigKey::ATTRIBUTE_PLANS, []);

            if ($compiledPlans !== []) {
                $attributeProvider = new CompiledAttributeValidationProvider(
                    $validatorFactory,
                    $ruleFactory,
                    $compiledPlans,
                    $attributeProvider,
                );
            }

            $provider->add($attributeProvider);
            $provider->add(new ValidatedByProvider($container));
        }

        return $provider;
    }
}
