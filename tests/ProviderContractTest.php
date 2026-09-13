<?php
declare(strict_types=1);
namespace Componenta\Validation\Tests;

use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\Validation\Context;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidationProviderFactory;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Provider\ValidationProviderInterface;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleFactoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ProviderContractTest extends TestCase
{
    #[DataProvider('environments')]
    public function testValidationRemainsAvailableWithoutBuildArtifacts(string $environment): void
    {
        $container = new ProviderContractContainer();
        $rules = new RuleFactory();
        $container->entries = [
            Config::class => new Config([], new Environment(['APP_ENV' => $environment])),
            RuleFactoryInterface::class => $rules,
            ValidatorFactoryInterface::class => new ValidatorFactory($container, $rules),
        ];
        $provider = (new ValidationProviderFactory())($container);
        $validator = $provider->provide(ProviderRequiredDto::class);
        self::assertNotNull($validator);
        self::assertTrue($validator->validate(['name' => 'Ada']));
        $errors = $validator->validate([]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame(['name'], array_keys($errors->toArray()));
        self::assertSame('validation.required', $errors->get('name')->messageId);
        self::assertNull($provider->provide('unknown-validation-entry'));
    }

    public static function environments(): iterable
    {
        yield 'development' => ['development'];
        yield 'production' => ['production'];
    }
}

final class ProviderRequiredDto
{
    #[Required]
    public string $name;
}

final class ProviderContractContainer implements ContainerInterface
{
    public array $entries = [];
    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? throw new \RuntimeException('Unknown service: ' . $id);
    }
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
