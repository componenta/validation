<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Attribute\Callback;
use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Provider\AttributeValidationProvider;
use Componenta\Validation\Rule\IsInt;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class CallbackAttributeTest extends TestCase
{
    public function testCallbackReceivesTheValueAndContextDuringValidation(): void
    {
        $calls = [];
        $attribute = new Callback(static function (mixed $value, ContextInterface $context) use (&$calls): true|ErrorMessageCollectorInterface {
            $calls[] = [$value, $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE)];

            return (new IsInt(strict: true))->validate($value, $context);
        });
        $validator = new Validator(['quantity' => $attribute->buildRule(new RuleFactory())]);

        self::assertSame([], $calls);
        self::assertTrue($validator->validate(['quantity' => 2]));
        $errors = $validator->validate(['quantity' => 'invalid']);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.is_int.not_int', $errors->get('quantity')->messageId);
        self::assertSame([[2, 'quantity'], ['invalid', 'quantity']], $calls);
    }

    #[RequiresPhp('>=8.5.0')]
    public function testNativeCallbacksComposeWithValidateOnAnAliasedField(): void
    {
        require_once __DIR__ . '/Fixtures/NativeCallback.inc';

        $rules = new RuleFactory();
        $factory = new ValidatorFactory($this->createStub(ContainerInterface::class), $rules);
        $validator = (new AttributeValidationProvider($factory, $rules))->provide(NativeCallbackDto::class);
        self::assertNotNull($validator);

        self::assertTrue($validator->validate(['invite_code' => 'ABC123']));
        $lengthErrors = $validator->validate(['invite_code' => 'ABC']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $lengthErrors);
        self::assertSame('validation.length.exact', $lengthErrors->get('invite_code')->messageId);
        $formatErrors = $validator->validate(['invite_code' => 'abc123']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $formatErrors);
        self::assertSame('validation.regex.no_match', $formatErrors->get('invite_code')->messageId);
        $missingErrors = $validator->validate([], Context::stopOnFirstFailure());
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $missingErrors);
        self::assertSame('validation.required', $missingErrors->get('invite_code')->messageId);
    }
}
