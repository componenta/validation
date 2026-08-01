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
use Componenta\Validation\ValidatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use UnexpectedValueException;

/**
 * Factory for creating composite validation provider.
 *
 * Explicit, Validatable, and #[Validate] strategies are available in every
 * environment. Development additionally enables dynamic #[ValidatedBy]
 * lookup; production keeps that convention out of the hot path.
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
        $config = $container->get(ConfigKey::CONFIG);
        $validatorFactory = $container->get(ValidatorFactoryInterface::class);
        $ruleFactory = $container->get(RuleFactoryInterface::class);

        if (!$config instanceof Config) {
            throw self::invalidEntry(ConfigKey::CONFIG, Config::class, $config);
        }

        if (!$validatorFactory instanceof ValidatorFactoryInterface) {
            throw self::invalidEntry(
                ValidatorFactoryInterface::class,
                ValidatorFactoryInterface::class,
                $validatorFactory,
            );
        }

        if (!$ruleFactory instanceof RuleFactoryInterface) {
            throw self::invalidEntry(
                RuleFactoryInterface::class,
                RuleFactoryInterface::class,
                $ruleFactory,
            );
        }

        $devMode = $config->environment?->match(
            'APP_ENV',
            'development',
            'development',
        ) ?? true;

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

        $provider = new CompositeValidationProvider(
            new ValidatableProvider($validatorFactory),
            $mappedProvider = new MappedValidationProvider($container),
            $attributeProvider,
        );

        // Register static mappings from configuration.
        foreach ($config->array(ConfigKey::VALIDATORS_MAP, []) as $entry => $validator) {
            if (!is_string($entry)
                || !is_string($validator)
                || !is_a($validator, ValidatorInterface::class, true)
            ) {
                throw new UnexpectedValueException(sprintf(
                    'Validation map must contain class-string keys and %s class-string values.',
                    ValidatorInterface::class,
                ));
            }

            $mappedProvider->register($entry, $validator);
        }

        // Dynamic class-to-validator lookup is a development convenience.
        if ($devMode) {
            $provider->add(new ValidatedByProvider($container));
        }

        return $provider;
    }

    private static function invalidEntry(
        string $id,
        string $expected,
        mixed $entry,
    ): UnexpectedValueException {
        return new UnexpectedValueException(sprintf(
            'Container entry "%s" must implement %s; got %s.',
            $id,
            $expected,
            get_debug_type($entry),
        ));
    }
}
