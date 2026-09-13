<?php
declare(strict_types=1);
namespace Componenta\Validation\Tests;

use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Length;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\OneOf;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ProviderRegressionTest extends TestCase
{
    public function testNestedNullableCompositionRetainsTheNullBypass(): void
    {
        $rule = new AllOf(new AllOf(new Nullable(), new Email()), new Length(max: 255));
        self::assertTrue($rule->validate(null, new Context()));
        self::assertTrue($rule->validate('user@example.com', new Context()));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('invalid', new Context()));
    }

    public function testOneOfTreatsNullableAsANullAlternativeOnly(): void
    {
        $rule = new OneOf(new Nullable(), new Email());
        self::assertTrue($rule->validate(null, new Context()));
        self::assertTrue($rule->validate('user@example.com', new Context()));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('invalid', new Context()));
    }

    public function testNullableAcrossSeparateAttributesRetainsTheNullBypass(): void
    {
        $provider = self::provider([]);
        $validator = $provider->provide(NullableProviderDto::class);
        self::assertNotNull($validator);
        self::assertTrue($validator->validate(['email' => null]));
        self::assertTrue($validator->validate(['email' => 'user@example.com']));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['email' => 'invalid']));
    }

    public function testValidatedByResolvesAnInterfaceServiceId(): void
    {
        $validator = new class implements DelegatedValidatorContract {
            public function validate(iterable $data, ?ContextInterface $context = null): true|ErrorMessageCollectorInterface { return true; }
        };
        $provider = self::provider([DelegatedValidatorContract::class => $validator]);
        self::assertSame($validator, $provider->provide(InterfaceDelegatedDto::class));
    }

    private static function provider(array $entries): AttributeValidationProvider
    {
        $container = new class($entries) implements ContainerInterface {
            public function __construct(private array $entries) {}
            public function get(string $id): mixed { return $this->entries[$id] ?? throw new \RuntimeException($id); }
            public function has(string $id): bool { return array_key_exists($id, $this->entries); }
        };
        $rules = new RuleFactory();
        return new AttributeValidationProvider(new ValidatorFactory($container, $rules), $rules);
    }
}
final class NullableProviderDto
{
    #[Length(max: 255)]
    #[Validate('nullable|email')]
    public ?string $email = null;
}
interface DelegatedValidatorContract extends ValidatorInterface {}
#[ValidatedBy(DelegatedValidatorContract::class)]
final class InterfaceDelegatedDto {}
