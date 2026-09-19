<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\In;
use Componenta\Validation\Rule\Regex;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleRegistrationTest extends TestCase
{
    #[DataProvider('regexRules')]
    public function testRegexAliasPreservesTheCompletePattern(string $name, string $pattern, string $accepted, string $rejected): void
    {
        $factory = new RuleFactory();
        $factory->alias('pattern', 'regex');
        $validator = new Validator(['value' => $factory->createRule($name . ':' . $pattern)]);

        self::assertTrue($validator->validate(['value' => $accepted]));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => $rejected]));
    }

    public static function regexRules(): iterable
    {
        yield 'native quantifier comma' => ['regex', '~^[a-z]{1,3}$~', 'abc', 'abcd'];
        yield 'alias quantifier comma' => ['pattern', '~^[a-z]{1,3}$~', 'abc', 'abcd'];
        yield 'native alternation' => ['regex', '~^(red|blue)$~', 'blue', 'green'];
        yield 'alias alternation' => ['pattern', '~^(red|blue)$~', 'blue', 'green'];
    }

    #[DataProvider('pipePatterns')]
    public function testRegexAliasKeepsTheFollowingPipeRules(string $pattern): void
    {
        $factory = new RuleFactory();
        $factory->alias('pattern', 'regex');
        $validator = new Validator([
            'value' => $factory->createRule('required | PATTERN:' . $pattern . ' | length:3,3'),
        ]);

        self::assertTrue($validator->validate(['value' => 'BAR']));
        foreach (['baz', 'longer', ''] as $rejected) {
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => $rejected]));
        }
    }

    public static function pipePatterns(): iterable
    {
        yield 'slash delimiter' => ['/^(foo|bar|longer)$/i'];
        yield 'nested paired delimiter' => ['{^(fo{2}|bar|longer)$}i'];
        yield 'escaped delimiter' => ['~^(fo\~o|bar|longer)$~i'];
    }

    #[DataProvider('registeredRules')]
    public function testExplicitFactoryRegistrationDeterminesTheValidation(string $name, string $definition): void
    {
        $factory = new RuleFactory();
        $factory->alias('pattern', 'regex');
        $factory->register($name, static fn (array $arguments): Regex => new Regex('~^selected$~'));
        $validator = new Validator(['value' => $factory->createRule($definition)]);

        self::assertTrue($validator->validate(['value' => 'selected']));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'original']));
    }

    public static function registeredRules(): iterable
    {
        yield 'ordinary registration' => ['email', 'email'];
        yield 'regex registration' => ['regex', 'regex:~^original$~'];
        yield 'regex registration through alias' => ['regex', 'pattern:~^original$~'];
    }

    public function testOwnRegistrationTakesPriorityOverAnExistingRegexAlias(): void
    {
        $factory = new RuleFactory();
        $factory->alias('pattern', 'regex');
        $factory->register('pattern', static fn (array $arguments): In => new In($arguments));
        $validator = new Validator(['value' => $factory->createRule('pattern:red,blue')]);

        self::assertTrue($validator->validate(['value' => 'red']));
        self::assertTrue($validator->validate(['value' => 'blue']));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $validator->validate(['value' => 'green']));
    }

    public function testRegisteredRegexFactoryReceivesTheWholePatternAndKeepsItsException(): void
    {
        $failure = new DomainException('application regex factory failed');
        $factory = new RuleFactory();
        $factory->alias('pattern', 'regex');
        $factory->register('regex', static function (array $arguments) use ($failure): never {
            self::assertSame(['~^(red|blue){1,3}$~'], $arguments);
            throw $failure;
        });

        foreach (['regex', 'pattern'] as $name) {
            $caught = null;
            try {
                $factory->createRule($name . ':~^(red|blue){1,3}$~');
            } catch (DomainException $error) {
                $caught = $error;
            }
            self::assertSame($failure, $caught);
        }
    }
}
