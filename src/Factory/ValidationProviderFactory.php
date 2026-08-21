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
use InvalidArgumentException;
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
        $config = $container->get(Config::class);
        /** @var ValidatorFactoryInterface $validatorFactory */
        $validatorFactory = $container->get(ValidatorFactoryInterface::class);
        /** @var RuleFactoryInterface $ruleFactory */
        $ruleFactory = $container->get(RuleFactoryInterface::class);

        $environment = $config->environment;
        $isDevelopment = $environment === null
            || $environment->match(
                'APP_ENV',
                'development',
                default: 'development',
                strict: true,
            );

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
        if ($compiled === null) {
            if ($config->bool(ConfigKey::REQUIRE_COMPILED_VALIDATORS, false)) {
                throw new InvalidArgumentException(sprintf(
                    'Compiled validation map is missing from %s; run app:build before starting a non-development application.',
                    ConfigKey::COMPILED_VALIDATORS,
                ));
            }

            return $provider;
        }

        if (!is_array($compiled)) {
            throw new InvalidArgumentException(sprintf(
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

        return $provider;
    }
}
