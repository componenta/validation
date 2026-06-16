<?php

namespace Componenta\Validation;

use Componenta\Validation\Factory\MessageFormatterFactory;
use Componenta\Validation\Factory\RuleFactoryFactory;
use Componenta\Validation\Factory\ValidationProviderFactory;
use Componenta\Validation\Factory\ValidatorFactoryFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Formatter\MessageFormatterInterface;
use Componenta\Validation\Provider\ValidationProviderInterface;
use Componenta\Validation\Rule\RuleFactoryInterface;

/**
 * Validation library configuration provider.
 *
 * Registers validation services in dependency injection container.
 */
class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getFactories(): array
    {
        return [
            ValidatorFactoryInterface::class => ValidatorFactoryFactory::class,
            ValidationProviderInterface::class => ValidationProviderFactory::class,
            RuleFactoryInterface::class => RuleFactoryFactory::class,
            MessageFormatterInterface::class => MessageFormatterFactory::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            ConfigKey::USED_LOCALES => [
                ConfigKey::LOCALE_EN,
                ConfigKey::LOCALE_RU,
            ],

            ConfigKey::DEFAULT_LOCALE => ConfigKey::LOCALE_EN
        ];
    }
}