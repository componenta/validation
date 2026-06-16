<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\IfThen;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RuleFactoryTest extends TestCase
{
    public function testCreatesSingleRuleFromStringDefinition(): void
    {
        $factory = new RuleFactory();

        self::assertInstanceOf(Email::class, $factory->createRule('email'));
    }

    public function testPipeSeparatedRulesAreComposedWithAllOf(): void
    {
        $factory = new RuleFactory();

        $rule = $factory->createRule('required|email|length:5,255');
        $context = new Context();

        self::assertInstanceOf(AllOf::class, $rule);
        self::assertTrue($rule->validate('user@example.com', $context));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('bad', $context));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('', $context));
    }

    public function testNullablePipeRulesValidateOnlyNonNullValues(): void
    {
        $factory = new RuleFactory();

        $rule = $factory->createRule('nullable|email');
        $context = new Context();

        self::assertInstanceOf(IfThen::class, $rule);
        self::assertTrue($rule->validate(null, $context));
        self::assertTrue($rule->validate('user@example.com', $context));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate('bad', $context));
    }

    public function testCustomRulesCanBeRegisteredAndAliased(): void
    {
        $factory = new RuleFactory();
        $factory->register(['starts_with', 'prefix'], static fn (array $params): RuleInterface => new class ((string) $params[0]) implements RuleInterface {
            public string $name {
                get => 'starts_with';
            }

            public function __construct(private readonly string $prefix) {}

            public function __invoke(mixed $value): bool
            {
                return is_string($value) && str_starts_with($value, $this->prefix);
            }

            public function validate(mixed $value, \Componenta\Validation\ContextInterface $context): true|\Componenta\Validation\Error\ErrorMessageCollectorInterface
            {
                return $this($value) ? true : new \Componenta\Validation\Error\ErrorMessageCollector();
            }
        });

        $context = new Context();

        self::assertTrue($factory->createRule('prefix:usr_')->validate('usr_123', $context));
        self::assertInstanceOf(
            ErrorMessageCollectorInterface::class,
            $factory->createRule('prefix:usr_')->validate('admin_123', $context),
        );
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rule: "missing_rule"');

        (new RuleFactory())->createRule('missing_rule');
    }

    public function testCreateRulesKeepsRuleInstancesAndParsesStrings(): void
    {
        $factory = new RuleFactory();
        $required = new Required();

        $rules = $factory->createRules([
            'name' => $required,
            'email' => 'email',
        ]);

        self::assertSame($required, $rules['name']);
        self::assertInstanceOf(Email::class, $rules['email']);
    }
}
