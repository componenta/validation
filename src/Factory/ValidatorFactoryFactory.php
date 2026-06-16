<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Config\Config;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\Formatter\MessageFormatterInterface;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Walker\Walker;
use Componenta\Validation\Walker\WalkerInterface;
use Psr\Container\ContainerInterface;

class ValidatorFactoryFactory
{
    public function __invoke(ContainerInterface $container): ValidatorFactory
    {
        /**
         * @var Config $config
         */
        $config = $container->get(ConfigKey::CONFIG);

        return new ValidatorFactory(
            $container,
            $container->has(RuleFactoryInterface::class) ? $container->get(RuleFactoryInterface::class) : new RuleFactory(),
            $container->has(WalkerInterface::class) ? $container->get(WalkerInterface::class) : new Walker(),
            $container->get(MessageFormatterInterface::class),
            $config->get(ConfigKey::DEFAULT_LOCALE, ConfigKey::LOCALE_EN),
        );
    }
}
