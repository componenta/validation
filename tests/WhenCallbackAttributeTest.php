<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Attribute\WhenCallback;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\IsInt;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class WhenCallbackAttributeTest extends TestCase
{
    public function testConditionRunsOnlyDuringValidationAndReceivesTheCurrentFieldAndValue(): void
    {
        $calls = [];
        $attribute = new WhenCallback(
            static function (mixed $value, ContextInterface $context) use (&$calls): bool {
                $calls[] = [$value, $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE)];

                return $value === null;
            },
            then: 'required',
            else: 'string',
        );
        $validator = new Validator(['note' => $attribute->buildRule(new RuleFactory())]);

        self::assertSame([], $calls);
        $errors = $validator->validate([]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.required', $errors->get('note')->messageId);
        self::assertTrue($validator->validate(['note' => 'ready']));
        self::assertSame([[null, 'note'], ['ready', 'note']], $calls);
    }

    public function testOnlyTheSelectedBranchValidatesTheCurrentValue(): void
    {
        $calls = [];
        $attribute = new WhenCallback(
            static fn (mixed $value): bool => is_string($value),
            then: static function (mixed $value, ContextInterface $context) use (&$calls): true|ErrorMessageCollectorInterface {
                $calls[] = ['then', $value, $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE)];

                return (new Email())->validate($value, $context);
            },
            else: static function (mixed $value, ContextInterface $context) use (&$calls): true|ErrorMessageCollectorInterface {
                $calls[] = ['else', $value, $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE)];

                return (new IsInt(strict: true))->validate($value, $context);
            },
        );
        $validator = new Validator(['value' => $attribute->buildRule(new RuleFactory())]);

        self::assertSame([], $calls);
        self::assertTrue($validator->validate(['value' => 'ada@example.com']));
        self::assertTrue($validator->validate(['value' => 42]));
        $errors = $validator->validate(['value' => 'invalid']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.email.invalid', $errors->get('value')->messageId);
        $errors = $validator->validate(['value' => 4.2]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.is_int.not_int', $errors->get('value')->messageId);
        self::assertSame([
            ['then', 'ada@example.com', 'value'],
            ['else', 42, 'value'],
            ['then', 'invalid', 'value'],
            ['else', 4.2, 'value'],
        ], $calls);
    }

    public function testBranchCallbackMustReturnAValidationResult(): void
    {
        $attribute = new WhenCallback(
            static fn (): bool => true,
            then: static fn (): bool => false,
        );
        $validator = new Validator(['value' => $attribute->buildRule(new RuleFactory())]);

        $this->expectException(\TypeError::class);
        $validator->validate(['value' => 'invalid']);
    }

    #[RequiresPhp('>=8.5.0')]
    public function testNativeClosureAndFirstClassFunctionUseTheCurrentValidationData(): void
    {
        require_once __DIR__ . '/Fixtures/NativeWhenCallbacks.inc';
        $validator = $this->provide(NativeWhenCallbackDto::class);

        self::assertTrue($validator->validate([]));
        $errors = $validator->validate(['publish' => true, 'email' => 'invalid']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame(['email', 'summary'], array_keys($errors->toArray()));
        self::assertSame('validation.email.invalid', $errors->get('email')->messageId);
        self::assertSame('validation.required', $errors->get('summary')->messageId);
        self::assertTrue($validator->validate(['publish' => true, 'email' => 'ada@example.com', 'summary' => 'Ready']));
    }

    #[RequiresPhp('>=8.5.0')]
    public function testNativeFirstClassMethodAndStaticClosureValidateTheSelectedBranch(): void
    {
        require_once __DIR__ . '/Fixtures/NativeWhenCallbacks.inc';
        $validator = $this->provide(NativeWhenRuleCallbackDto::class);

        self::assertTrue($validator->validate(['publish' => true, 'value' => 'ada@example.com']));
        self::assertTrue($validator->validate(['value' => 42]));
        $errors = $validator->validate(['publish' => true, 'value' => 'invalid']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.email.invalid', $errors->get('value')->messageId);
        $errors = $validator->validate(['value' => 'ada@example.com']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.is_int.not_int', $errors->get('value')->messageId);
    }

    #[RequiresPhp('>=8.5.13')]
    public function testFirstClassTraitConditionsKeepThePrivateScopeOfEachConsumingClass(): void
    {
        require_once __DIR__ . '/Fixtures/TraitWhenCallbacks.inc';
        $required = $this->provide(TraitRequiredWhenCallbackDto::class);
        $optional = $this->provide(TraitOptionalWhenCallbackDto::class);

        $errors = $required->validate([]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.required', $errors->get('value')->messageId);
        self::assertTrue($optional->validate([]));
        self::assertTrue($required->validate(['value' => 'ready']));
    }

    /** @param class-string $class */
    private function provide(string $class): ValidatorInterface
    {
        $rules = new RuleFactory();
        $validators = new ValidatorFactory($this->createStub(ContainerInterface::class), $rules);
        $validator = (new AttributeValidationProvider($validators, $rules))->provide($class);
        self::assertNotNull($validator);

        return $validator;
    }
}
