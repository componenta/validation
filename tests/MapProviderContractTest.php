<?php
declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Provider\MapValidationProvider;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class MapProviderContractTest extends TestCase
{
    public function testMapMissIsDelegatedToTheNextProviderWithoutReflection(): void
    {
        $container = new class implements ContainerInterface {
            public function has(string $id): bool { return false; }
            public function get(string $id): mixed { throw new \LogicException($id); }
        };
        $rules = new RuleFactory();
        $rules->register('application_required', static fn () => new Required());
        $provider = new MapValidationProvider([], new ValidatorFactory($container, $rules), $rules);

        self::assertNull($provider->provide(ApplicationRulesDto::class));
    }

    public function testUsesTheInjectedRuleFactoryLikeTheAttributeProvider(): void
    {
        $container = new class implements ContainerInterface {
            public function has(string $id): bool { return false; }
            public function get(string $id): mixed { throw new \LogicException($id); }
        };
        $rules = new RuleFactory();
        $rules->register('application_required', static fn () => new Required());
        $validators = new ValidatorFactory($container);
        $providers = [
            new \Componenta\Validation\Provider\AttributeValidationProvider($validators, $rules),
            new MapValidationProvider([ApplicationRulesDto::class => ['name' => 'application_required']], $validators, $rules),
        ];
        foreach ($providers as $provider) {
            $validator = $provider->provide(ApplicationRulesDto::class);
            self::assertTrue($validator->validate(['name' => 'Ada']));
            self::assertSame('validation.required', $validator->validate([])->get('name')->messageId);
        }
    }

    public function testProvidesRulesAndValidatorServicesWithoutLoadingDtoClasses(): void
    {
        $service = new Validator(['token' => new Required()]);
        $container = new class($service) implements ContainerInterface {
            public function __construct(private ValidatorInterface $service) {}
            public function has(string $id): bool { return $id === ValidatorInterface::class; }
            public function get(string $id): mixed
            {
                if (!$this->has($id)) { throw new \LogicException($id); }
                return $this->service;
            }
        };
        $rules = new RuleFactory();
        $provider = new MapValidationProvider([
            'Unloaded\RegistrationDto' => ['email' => 'required|email', 'age' => 'required|int'],
            'Unloaded\ServiceDto' => ValidatorInterface::class,
        ], new ValidatorFactory($container, $rules), $rules);

        $validator = $provider->provide('\UNLOADED\RegistrationDto');
        self::assertTrue($validator->validate(['email' => 'user@example.com', 'age' => 25]));
        self::assertSame(['email', 'age'], array_keys($validator->validate([])->toArray()));
        self::assertSame($service, $provider->provide('Unloaded\ServiceDto'));
        self::assertSame($service, $provider->provide('Unloaded\ServiceDto'));
        self::assertNull($provider->provide('Unloaded\EmptyDto'));
    }
}

final class ApplicationRulesDto
{
    #[\Componenta\Validation\Attribute\Validate('application_required')]
    public string $name;
}
