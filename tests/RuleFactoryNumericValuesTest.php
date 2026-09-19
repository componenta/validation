<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Range;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleFactoryNumericValuesTest extends TestCase
{
    #[DataProvider('bounds')]
    public function testNumericBoundsPreserveTheValueOfDirectRules(string $definition, Range $controlRule, int|float $accepted, int|float $rejected): void
    {
        $control = new Validator(['value' => $controlRule]);
        self::assertTrue($control->validate(['value' => $accepted]));
        $expected = $control->validate(['value' => $rejected]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $expected);

        $validator = new Validator(['value' => (new RuleFactory())->createRule($definition)]);

        self::assertTrue($validator->validate(['value' => $accepted]));
        $errors = $validator->validate(['value' => $rejected]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame($expected->toArray(), $errors->toArray());
    }

    public static function bounds(): iterable
    {
        yield 'minimum exponent' => ['min:1e-2', new Range(min: 0.01), 0.02, 0.005];
        yield 'maximum exponent' => ['max:1e-2', new Range(max: 0.01), 0.005, 0.02];
        yield 'range exponent' => ['range:1e-2,2e-2', new Range(min: 0.01, max: 0.02), 0.015, 0.025];
        yield 'negative bound' => ['min:-1e-2', new Range(min: -0.01), -0.005, -0.02];
        yield 'positive exponent' => ['min:1e2', new Range(min: 100.0), 150, 99];
        yield 'uppercase exponent' => ['max:2E+2', new Range(max: 200.0), 200, 201];
        yield 'decimal control' => ['min:0.01', new Range(min: 0.01), 0.02, 0.005];
        yield 'integer control' => ['max:2', new Range(max: 2), 2, 3];
    }

    #[DataProvider('conditions')]
    public function testConditionalRulesPreserveTheExpectedValueAndType(string $literal, mixed $active, mixed $inactive): void
    {
        $factory = new RuleFactory();
        $required = new Validator(['value' => $factory->createRule('required_if:level,' . $literal)]);
        $prohibited = new Validator(['value' => $factory->createRule('prohibited_if:level,' . $literal)]);

        $missing = $required->validate(['level' => $active]);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $missing);
        self::assertSame('validation.required_if', $missing->get('value')->messageId);
        self::assertTrue($required->validate(['level' => $active, 'value' => 'present']));
        self::assertTrue($required->validate(['level' => $inactive]));

        $unexpected = $prohibited->validate(['level' => $active, 'value' => 'present']);
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $unexpected);
        self::assertSame('validation.prohibited_if', $unexpected->get('value')->messageId);
        self::assertTrue($prohibited->validate(['level' => $active]));
        self::assertTrue($prohibited->validate(['level' => $inactive, 'value' => 'present']));
    }

    public static function conditions(): iterable
    {
        yield 'fractional exponent' => ['1e-2', 0.01, 0];
        yield 'positive exponent remains float' => ['1e2', 100.0, 100];
        yield 'uppercase exponent remains float' => ['2E+2', 200.0, 200];
        yield 'negative value with exponent' => ['-1e-2', -0.01, 0];
        yield 'integer remains int' => ['2', 2, 2.0];
        yield 'negative integer remains int' => ['-2', -2, -2.0];
        yield 'zero remains int' => ['0', 0, false];
        yield 'decimal remains float' => ['0.01', 0.01, '0.01'];
        yield 'boolean control' => ['TRUE', true, 'true'];
        yield 'null control' => ['null', null, 'null'];
    }
}
