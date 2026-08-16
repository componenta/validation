<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Config\Config;
use Componenta\Config\Environment;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\ConfigKey;
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Definition\AttributeValidatorDefinitionExtractor;
use Componenta\Validation\Definition\ValidatorDefinitionFactory;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidationProviderFactory;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Provider\CompiledValidationProvider;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Length;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\OneOf;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class CompiledContractsRegressionTest extends TestCase
{
    public function testNestedNullableCompositionRetainsTheNullBypass(): void
    {
        $rule = new AllOf(
            new AllOf(new Nullable(), new Email()),
            new Length(max: 255),
        );

        self::assertTrue($rule->validate(null, new Context()));
        self::assertTrue($rule->validate('user@example.com', new Context()));
        self::assertInstanceOf(
            ErrorMessageCollectorInterface::class,
            $rule->validate('not-an-email', new Context()),
        );
    }

    public function testOneOfTreatsNullableAsANullAlternativeOnly(): void
    {
        $rule = new OneOf(new Nullable(), new Email());

        self::assertTrue($rule->validate(null, new Context()));
        self::assertTrue($rule->validate('user@example.com', new Context()));
        self::assertInstanceOf(
            ErrorMessageCollectorInterface::class,
            $rule->validate('not-an-email', new Context()),
        );
    }

    public function testCompiledMetadataKeepsNullableAcrossSeparateAttributes(): void
    {
        $definition = (new AttributeValidatorDefinitionExtractor())->extract(NullableCompiledDto::class);
        self::assertIsArray($definition);

        $container = new ArrayContainer();
        $ruleFactory = new RuleFactory();
        $validatorFactory = new ValidatorFactory($container, $ruleFactory);
        $provider = new CompiledValidationProvider([
            'version' => ConfigKey::COMPILED_VALIDATORS_VERSION,
            'validators' => [NullableCompiledDto::class => $definition],
        ], $validatorFactory, new ValidatorDefinitionFactory($ruleFactory));

        $validator = $provider->provide(NullableCompiledDto::class);
        self::assertNotNull($validator);
        self::assertTrue($validator->validate(['email' => null]));
        self::assertTrue($validator->validate(['email' => 'user@example.com']));
    }

    public function testValidatedByAcceptsAnInterfaceServiceId(): void
    {
        self::assertSame([
            'kind' => 'validator',
            'class' => DelegatedValidatorContract::class,
        ], (new AttributeValidatorDefinitionExtractor())->extract(InterfaceDelegatedDto::class));
    }

    public function testIntegrationCanRequireACompiledMapOutsideDevelopment(): void
    {
        $config = new Config([
            ConfigKey::REQUIRE_COMPILED_VALIDATORS => true,
        ], new Environment(['APP_ENV' => 'production']));
        $container = new ArrayContainer([
            ConfigKey::CONFIG => $config,
            ValidatorFactoryInterface::class => new StubValidatorFactory(),
            RuleFactoryInterface::class => new RuleFactory(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('run app:build');

        (new ValidationProviderFactory())($container);
    }
}

final class ArrayContainer implements ContainerInterface
{
    /** @param array<string, mixed> $entries */
    public function __construct(private array $entries = []) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class ("Missing $id") extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {};
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class StubValidatorFactory implements ValidatorFactoryInterface
{
    public function create(string $cls): ValidatorInterface
    {
        throw new \LogicException('Validator service creation is not expected in this test.');
    }

    public function createFrom(array $rules): ValidatorInterface
    {
        return new Validator((new RuleFactory())->createRules($rules));
    }
}

final class NullableCompiledDto
{
    #[Length(max: 255)]
    #[Validate('nullable|email')]
    public ?string $email = null;
}

interface DelegatedValidatorContract extends ValidatorInterface {}

#[ValidatedBy(DelegatedValidatorContract::class)]
final class InterfaceDelegatedDto {}
