<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\In;
use Componenta\Validation\Rule\OneOf;
use Componenta\Validation\Rule\Regex;
use Componenta\Validation\Rule\RuleComposer;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Validator;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompositeRuleRegistrationTest extends TestCase
{
    #[DataProvider('aliases')]
    public function testAliasPreservesTheTargetCompositeRule(string $target, string $alias, string $parameters, mixed $accepted, mixed $rejected): void
    {
        $factory = new RuleFactory();
        $factory->alias($alias, $target);
        $factory->alias('pattern', 'regex');

        foreach ([$target, $alias] as $name) {
            $validator = new Validator(['value' => $factory->createRule($name . ':' . $parameters)]);

            self::assertTrue($validator->validate(['value' => $accepted]) === true, $name);
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => $rejected]));
        }
    }

    public static function aliases(): iterable
    {
        yield 'allof' => ['allof', 'both', 'required|email', 'a@example.com', 'bad'];
        yield 'oneof comma' => ['oneof', 'either', 'email,url', 'https://example.com', 'bad'];
        yield 'oneof parameterized children' => ['oneof', 'either', 'email|length:2,3', 'ok', 'nope'];
        yield 'arrayof single child' => ['arrayof', 'items', 'email', ['a@example.com'], ['bad']];
        yield 'arrayof composed child' => ['arrayof', 'items', 'email|length:5,255', ['a@example.com'], ['bad']];
        yield 'oneof with regex alias' => ['oneof', 'either', 'pattern:~^(red|blue){1,3}$~|email', 'blue', 'green'];
    }

    #[DataProvider('builtInNames')]
    public function testExplicitRegistrationCreatesTheCompositeRule(string $name): void
    {
        $factory = new RuleFactory();
        $factory->register($name, static fn (array $arguments): Regex => new Regex('~^selected$~'));
        $validator = new Validator(['value' => $factory->createRule($name . ':email')]);

        self::assertTrue($validator->validate(['value' => 'selected']) === true);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'original']));
    }

    public static function builtInNames(): iterable
    {
        foreach (['allof', 'all_of', 'oneof', 'one_of', 'arrayof', 'array_of'] as $name) {
            yield $name => [$name];
        }
    }

    public function testExistingAliasesUseTheReplacementFactoryAndItsParameterMode(): void
    {
        $factory = new RuleFactory();
        $factory->alias('either', 'oneof');
        $factory->register('oneof', static fn (array $arguments): In => new In($arguments));

        foreach (['oneof', 'one_of', 'either'] as $name) {
            $validator = new Validator(['value' => $factory->createRule($name . ':email,url')]);
            self::assertTrue($validator->validate(['value' => 'email']) === true);
            self::assertTrue($validator->validate(['value' => 'url']) === true);
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'green']));
        }
    }

    #[DataProvider('registrationOrders')]
    public function testAnOwnRegistrationTakesPriorityOverCompositeAliasMetadata(bool $aliasFirst): void
    {
        $factory = new RuleFactory();
        if ($aliasFirst) {
            $factory->alias('choice', 'oneof', composite: true);
        }
        $factory->register('choice', static fn (array $arguments): In => new In($arguments));
        if (!$aliasFirst) {
            $factory->alias('choice', 'oneof', composite: true);
        }
        $validator = new Validator(['value' => $factory->createRule('choice:email,url')]);

        self::assertTrue($validator->validate(['value' => 'email']) === true);
        self::assertTrue($validator->validate(['value' => 'url']) === true);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'green']));
    }

    public static function registrationOrders(): iterable
    {
        yield 'alias first' => [true];
        yield 'registration first' => [false];
    }

    public function testReplacementFactoryReceivesRawParametersAndItsExceptionIsNotChanged(): void
    {
        $failure = new DomainException('application composite factory failed');
        $factory = new RuleFactory();
        $factory->alias('either', 'oneof');
        $factory->register('oneof', static function (array $arguments) use ($failure): never {
            self::assertSame(['red', 'blue'], $arguments);
            throw $failure;
        });

        foreach (['oneof', 'either'] as $name) {
            $caught = null;
            try {
                $factory->createRule($name . ':red,blue');
            } catch (DomainException $error) {
                $caught = $error;
            }
            self::assertSame($failure, $caught);
        }
    }

    public function testCompositeReplacementReceivesAllNestedRulesThroughAnExistingAlias(): void
    {
        $factory = new RuleFactory();
        $factory->alias('both', 'allof');
        $factory->register('allof', static fn (array $rules): OneOf => new OneOf(...$rules), composite: true);

        foreach (['allof', 'all_of', 'both'] as $name) {
            $validator = new Validator(['value' => $factory->createRule($name . ':email|length:2,3')]);
            self::assertTrue($validator->validate(['value' => 'ok']) === true);
            self::assertTrue($validator->validate(['value' => 'a@example.com']) === true);
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'nope']));
        }
    }

    public function testArrayOfReplacementReceivesTheCompleteComposedChild(): void
    {
        $factory = new RuleFactory();
        $factory->alias('items', 'arrayof');
        $factory->register('arrayof', static fn (array $rules): RuleInterface => RuleComposer::all(...$rules), composite: true);

        foreach (['arrayof', 'array_of', 'items'] as $name) {
            $validator = new Validator(['value' => $factory->createRule($name . ':email|length:5,255')]);
            self::assertTrue($validator->validate(['value' => 'a@example.com']) === true);
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'bad']));
        }
    }

    public function testAnExplicitCompositeAliasStillParsesNestedParametersForACustomFactory(): void
    {
        $factory = new RuleFactory();
        $factory->register('custom', static fn (array $rules): OneOf => new OneOf(...$rules));
        $factory->alias('choice', 'custom', composite: true);
        $validator = new Validator(['value' => $factory->createRule('choice:email,url')]);

        self::assertTrue($validator->validate(['value' => 'a@example.com']) === true);
        self::assertTrue($validator->validate(['value' => 'https://example.com']) === true);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'bad']));
    }
}
