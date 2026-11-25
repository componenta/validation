<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Config\ConfigKey as BaseConfigKey;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\ConfigProvider;
use Componenta\Validation\Factory\MessageFormatterFactory;
use Componenta\Validation\Factory\RuleFactoryFactory;
use Componenta\Validation\Factory\ValidationProviderFactory;
use Componenta\Validation\Factory\ValidatorFactoryFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Formatter\MessageFormatterInterface;
use Componenta\Validation\Provider\ValidationProviderInterface;
use Componenta\Validation\Rule\RuleFactoryInterface;
use PHPUnit\Framework\TestCase;

final class ConfigCompatibilityTest extends TestCase
{
    public function testPackageConfigKeyInheritsSharedConfigKeys(): void
    {
        self::assertSame(BaseConfigKey::DEPENDENCIES, ConfigKey::DEPENDENCIES);
        self::assertSame(BaseConfigKey::FACTORIES, ConfigKey::FACTORIES);
        self::assertSame('VALIDATION_DEFAULT_LOCALE', ConfigKey::DEFAULT_LOCALE);
    }

    public function testConfigProviderExportsTheValidationFactoriesAndLocaleDefaults(): void
    {
        $config = (new ConfigProvider())();

        self::assertSame([
            ValidatorFactoryInterface::class => ValidatorFactoryFactory::class,
            ValidationProviderInterface::class => ValidationProviderFactory::class,
            RuleFactoryInterface::class => RuleFactoryFactory::class,
            MessageFormatterInterface::class => MessageFormatterFactory::class,
        ], $config[BaseConfigKey::DEPENDENCIES][BaseConfigKey::FACTORIES]);
        self::assertSame([ConfigKey::LOCALE_EN, ConfigKey::LOCALE_RU], $config[ConfigKey::USED_LOCALES]);
        self::assertSame(ConfigKey::LOCALE_EN, $config[ConfigKey::DEFAULT_LOCALE]);
    }
}
