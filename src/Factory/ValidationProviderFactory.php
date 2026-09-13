<?php
declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Config\Config;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Provider\CompositeValidationProvider;
use Componenta\Validation\Provider\MappedValidationProvider;
use Componenta\Validation\Provider\MapValidationProvider;
use Componenta\Validation\Provider\ValidatableProvider;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Psr\Container\ContainerInterface;

/** @phpstan-import-type Entry from MapValidationProvider */
final readonly class ValidationProviderFactory
{
    /** @param array<string, Entry>|null $map */
    public function __construct(private ?array $map = null) {}

    public function __invoke(ContainerInterface $container): CompositeValidationProvider
    {
        /** @var Config $config */
        $config = $container->get(Config::class);
        /** @var ValidatorFactoryInterface $validators */
        $validators = $container->get(ValidatorFactoryInterface::class);
        /** @var RuleFactoryInterface $rules */
        $rules = $container->get(RuleFactoryInterface::class);
        $mapped = new MappedValidationProvider($container);
        foreach ($config->array(ConfigKey::VALIDATORS_MAP, []) as $entry => $validator) {
            if (!is_string($entry) || trim($entry) === '' || !is_string($validator) || trim($validator) === '') {
                throw new \InvalidArgumentException(ConfigKey::VALIDATORS_MAP . ' must map non-empty entry names to non-empty validator service IDs.');
            }
            $mapped->register($entry, $validator);
        }
        $providers = [
            new ValidatableProvider($validators),
            $mapped,
        ];
        if ($this->map !== null) {
            $providers[] = new MapValidationProvider($this->map, $validators, $rules);
        }
        $providers[] = new AttributeValidationProvider($validators, $rules);
        return new CompositeValidationProvider(...$providers);
    }
}
