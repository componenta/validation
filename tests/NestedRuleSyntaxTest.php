<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\ArrayOf;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Length;
use Componenta\Validation\Rule\OneOf;
use Componenta\Validation\Rule\Regex;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NestedRuleSyntaxTest extends TestCase
{
    #[DataProvider('customComposites')]
    public function testCustomCompositePreservesParameterizedChildren(string $name, bool $withEmail): void
    {
        $factory = new RuleFactory();
        $factory->register(['any', 'either'], static fn (array $rules): OneOf => new OneOf(...$rules), composite: true);
        $factory->register('custom', static fn (array $rules): OneOf => new OneOf(...$rules));
        $factory->alias('choice', 'custom', composite: true);
        $definition = $name . ':' . ($withEmail ? 'email|' : '') . 'length:2,3';
        $expected = new Validator(['value' => $withEmail
            ? new OneOf(new Email(), new Length(2, 3))
            : new OneOf(new Length(2, 3))]);

        $validator = new Validator(['value' => $factory->createRule($definition)]);
        foreach (['ok', 'abc', 'a@example.com', 'x', 'longer', 'ok'] as $value) {
            $result = $validator->validate(['value' => $value]);
            $control = $expected->validate(['value' => $value]);
            if ($control === true) {
                self::assertTrue($result);
            } else {
                self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
                self::assertSame($control->toArray(), $result->toArray());
            }
        }
    }

    public static function customComposites(): iterable
    {
        foreach (['any', 'either', 'choice'] as $name) {
            yield $name . ', comma in child parameters' => [$name, false];
            yield $name . ', alternative children' => [$name, true];
        }
    }

    #[DataProvider('nestedPatterns')]
    public function testNestedRegexAndFollowingRuleMatchDirectComposition(string $prefix, string $pattern, string $accepted): void
    {
        $factory = new RuleFactory();
        $factory->alias('items', 'arrayof');
        $factory->alias('pattern', 'regex');
        $factory->register('every', static fn (array $rules): AllOf => new AllOf(...$rules), composite: true);
        $expected = new Validator(['value' => new AllOf(new ArrayOf(new Regex($pattern)), new Required())]);
        $validator = new Validator(['value' => $factory->createRule($prefix . $pattern . '|required')]);

        foreach ([[$accepted], ['green'], [], [$accepted]] as $value) {
            $result = $validator->validate(['value' => $value]);
            $control = $expected->validate(['value' => $value]);
            if ($control === true) {
                self::assertTrue($result);
            } else {
                self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
                self::assertSame($control->toArray(), $result->toArray());
            }
        }
    }

    public static function nestedPatterns(): iterable
    {
        yield 'alternation' => ['allof:arrayof:regex:', '~^(red|blue)$~', 'blue'];
        yield 'parameter comma' => ['allof:arrayof:regex:', '~^(red|blue){1,3}$~', 'redblue'];
        yield 'paired delimiter' => ['allof:arrayof:regex:', '{^(red|blue)$}i', 'BLUE'];
        yield 'nested paired delimiter' => ['allof:arrayof:regex:', '(^(red|blue)$)i', 'BLUE'];
        yield 'escaped delimiter' => ['allof:arrayof:regex:', '~^(red|blue|red\~blue)$~', 'red~blue'];
        yield 'pipe delimiter' => ['allof:arrayof:regex:', '|^red\|blue$|', 'red|blue'];
        yield 'whitespace' => ['allof: arrayof: regex: ', '~^(red|blue)$~', 'blue'];
        yield 'deeper nesting' => ['allof:allof:arrayof:regex:', '~^(red|blue)$~', 'blue'];
        yield 'aliases' => ['allof:items:pattern:', '~^(red|blue)$~', 'blue'];
        yield 'custom composite' => ['every:every:items:pattern:', '~^(red|blue)$~', 'blue'];
    }

    public function testNestedFactoryExceptionIsPropagatedWithoutReplacement(): void
    {
        $failure = new DomainException('custom child creation failed');
        $factory = new RuleFactory();
        $factory->register('any', static fn (array $rules): OneOf => new OneOf(...$rules), composite: true);
        $factory->register('broken', static function (array $arguments) use ($failure): never {
            throw $failure;
        });

        try {
            $factory->createRule('any:length:2,3|broken');
            self::fail('The child factory exception must propagate.');
        } catch (DomainException $caught) {
            self::assertSame($failure, $caught);
        }
    }
}
