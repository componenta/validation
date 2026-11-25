<?php
declare(strict_types=1);
namespace Componenta\Validation\Tests;

use Attribute;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\Attribute\ValidatedBy;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Factory\ValidatorFactoryInterface;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class NativeAttributeProviderTest extends TestCase
{
    public function testConflictingDeclarationsAreRejectedBeforeConstructingRules(): void
    {
        $provider = new AttributeValidationProvider($this->createStub(ValidatorFactoryInterface::class), new RuleFactory());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot combine #[ValidatedBy] with property validation attributes');
        $provider->provide(ConflictingValidationDto::class);
    }

    public function testEachProvidedValidatorEvaluatesNativeAttributesOnceWithFreshArguments(): void
    {
        NativeStateRule::$constructions = 0;
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \LogicException($id); }
            public function has(string $id): bool { return false; }
        };
        $rules = new RuleFactory();
        $provider = new AttributeValidationProvider(new ValidatorFactory($container, $rules), $rules);

        $first = $provider->provide(NativeStateDto::class);
        self::assertNotNull($first);
        self::assertTrue($first->validate(['value' => 1]));
        self::assertTrue($first->validate(['value' => 2]));
        $second = $provider->provide(NativeStateDto::class);
        self::assertNotNull($second);
        self::assertTrue($second->validate(['value' => 1]));
        self::assertSame(2, NativeStateRule::$constructions);
    }
}

#[ValidatedBy(ValidatorInterface::class)]
final class ConflictingValidationDto
{
    #[Validate('unregistered-application-rule')]
    public string $value;
}
final class NativeRuleState
{
    public int $calls = 0;
}
#[Attribute(Attribute::TARGET_PROPERTY)]
final class NativeStateRule implements RuleInterface
{
    public static int $constructions = 0;
    public string $name { get => 'native-state'; }
    public function __construct(private NativeRuleState $state) { ++self::$constructions; }
    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($value !== ++$this->state->calls) {
            throw new \RuntimeException('Rule state leaked between provided validators.');
        }
        return true;
    }
}
final class NativeStateDto
{
    #[NativeStateRule(new NativeRuleState())]
    public int $value;
}
