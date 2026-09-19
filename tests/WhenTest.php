<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\When;
use Componenta\Validation\Validator;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WhenTest extends TestCase
{
    public function testBooleanConditionRequiresTheFieldWhenEnabled(): void
    {
        $validator = new Validator(['value' => new When('enabled:true', new Required())]);

        $errors = $validator->validate(['enabled' => true]);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.required', $errors->get('value')->messageId);
        self::assertTrue($validator->validate(['enabled' => true, 'value' => 'present']));
        self::assertTrue($validator->validate(['enabled' => false]));
    }

    #[DataProvider('conditions')]
    public function testStringConditionSelectsOnlyTheExpectedValueAndType(string $literal, mixed $active, mixed $inactive): void
    {
        $validator = new Validator(['value' => new When('enabled:' . $literal, new Required())]);

        $errors = $validator->validate(['enabled' => $active]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.required', $errors->get('value')->messageId);
        self::assertTrue($validator->validate(['enabled' => $active, 'value' => 'present']));
        self::assertTrue($validator->validate(['enabled' => $inactive]));
    }

    public static function conditions(): iterable
    {
        yield 'false' => ['false', false, 'false'];
        yield 'null' => ['null', null, 'null'];
        yield 'zero' => ['0', 0, false];
        yield 'integer and float remain distinct' => ['1', 1, 1.0];
        yield 'negative integer' => ['-2', -2, '-2'];
        yield 'decimal' => ['1.5', 1.5, '1.5'];
        yield 'exponent' => ['1e2', 100.0, 100];
        yield 'boolean literal spelling' => ['TRUE', true, 'true'];
        yield 'ordinary string remains case sensitive' => ['published', 'published', 'Published'];
        yield 'string containing colons' => ['token:a:b', 'token:a:b', 'token'];
    }

    #[DataProvider('explicitElseDeclarations')]
    public function testExplicitElseRuleValidatesTheFalseBranch(string $declaration): void
    {
        $rule = match ($declaration) {
            'direct' => new When('enabled:true', new Required(), new Email()),
            'callback' => new When(static fn (): bool => false, new Required(), new Email()),
            'factory' => (new RuleFactory())->createRule('when:enabled:true,required,email'),
        };
        $validator = new Validator(['value' => $rule]);

        if ($declaration !== 'callback') {
            self::assertTrue($validator->validate(['enabled' => true, 'value' => 'present']));
        }
        self::assertTrue($validator->validate(['enabled' => false, 'value' => 'a@example.com']));
        $errors = $validator->validate(['enabled' => false, 'value' => 'present']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.email.invalid', $errors->get('value')->messageId);
        self::assertTrue($validator->validate(['enabled' => false, 'value' => 'a@example.com']));
    }

    public static function explicitElseDeclarations(): iterable
    {
        yield 'direct rules' => ['direct'];
        yield 'callback condition' => ['callback'];
        yield 'string rule' => ['factory'];
    }

    public function testCallbackIsEvaluatedForEveryValidationWithItsCurrentState(): void
    {
        $enabled = false;
        $validator = new Validator(['value' => new When(
            static function () use (&$enabled): bool { return $enabled; },
            new Required(),
        )]);

        self::assertTrue($validator->validate([]));
        $enabled = true;
        $errors = $validator->validate([]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame('validation.required', $errors->get('value')->messageId);
        $enabled = false;
        self::assertTrue($validator->validate([]));
    }

    public function testCallbackExceptionIsPropagatedWithoutReplacement(): void
    {
        $failure = new DomainException('condition failed');
        $validator = new Validator(['value' => new When(
            static function () use ($failure): never { throw $failure; },
            new Required(),
        )]);

        try {
            $validator->validate([]);
            self::fail('The condition exception must propagate.');
        } catch (DomainException $caught) {
            self::assertSame($failure, $caught);
        }
    }
}
