<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Config\ConfigKey as BaseConfigKey;
use Componenta\Validation\ConfigKey;
use PHPUnit\Framework\TestCase;

final class ConfigCompatibilityTest extends TestCase
{
    public function testPackageConfigKeyInheritsSharedConfigKeys(): void
    {
        self::assertSame(BaseConfigKey::DEPENDENCIES, ConfigKey::DEPENDENCIES);
        self::assertSame(BaseConfigKey::FACTORIES, ConfigKey::FACTORIES);
        self::assertSame('VALIDATION_DEFAULT_LOCALE', ConfigKey::DEFAULT_LOCALE);
    }
}
