<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Config\Config;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\Definition\AttributeValidatorDefinitionExtractor;
use Componenta\Validation\Definition\ValidatorDefinitionFactory;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Provider\CompiledValidationProvider;
use Componenta\Validation\Provider\CompositeValidationProvider;
use Componenta\Validation\Provider\MappedValidationProvider;
use Componenta\Validation\Provider\ValidatableProvider;
use Componenta\Validation\Provider\ValidatedByProvider;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/** Creates the environment-appropriate validation provider chain. */
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
        /** @var RuleFactoryInterface $ruleFactory */
        $ruleFactory = $container->get(RuleFactoryInterface::class);

        $isDevelopment = $config->environment?->match(
            'APP_ENV',
            'development',
            default: 'development',
            strict: true,
        ) ?? true;

        $provider = new CompositeValidationProvider(
            new ValidatableProvider($validatorFactory),
            $mappedProvider = new MappedValidationProvider($container),
        );

        foreach ($config->array(ConfigKey::VALIDATORS_MAP, []) as $entry => $validator) {
            $mappedProvider->register($entry, $validator);
        }

        if ($isDevelopment) {
            $extractor = new AttributeValidatorDefinitionExtractor();
            $provider->add(new AttributeValidationProvider(
                $validatorFactory,
                $ruleFactory,
                $extractor,
            ));
            $provider->add(new ValidatedByProvider($container));

            return $provider;
        }

        $compiled = $config->get(ConfigKey::COMPILED_VALIDATORS, null);
        if ($compiled !== null) {
            if (!is_array($compiled)) {
                throw new \InvalidArgumentException(sprintf(
                    '%s must be an array; got %s.',
                    ConfigKey::COMPILED_VALIDATORS,
                    get_debug_type($compiled),
                ));
            }

            $provider->add(new CompiledValidationProvider(
                $compiled,
                $validatorFactory,
                new ValidatorDefinitionFactory($ruleFactory),
            ));
        }

        return $provider;
    }
}
