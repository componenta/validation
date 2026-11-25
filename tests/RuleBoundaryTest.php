<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Alpha;
use Componenta\Validation\Rule\AlphaDash;
use Componenta\Validation\Rule\AlphaNumeric;
use Componenta\Validation\Rule\DateFormat;
use Componenta\Validation\Rule\FileSize;
use Componenta\Validation\Rule\GreaterThan;
use Componenta\Validation\Rule\LessThan;
use Componenta\Validation\Rule\Range;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\Uuid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

final class RuleBoundaryTest extends TestCase
{
    #[DataProvider('wholeStringRules')]
    public function testValidatesTheWholeStringIncludingItsFinalCharacter(RuleInterface $rule, string $value): void
    {
        self::assertTrue($rule->validate($value, new Context()));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate($value . "\n", new Context()));
    }

    public static function wholeStringRules(): iterable
    {
        yield 'Unicode letters' => [new Alpha(), 'Pérez'];
        yield 'ASCII letters' => [new Alpha(ascii: true), 'Adams'];
        yield 'letters and numbers' => [new AlphaNumeric(), 'Pérez2'];
        yield 'letters and dashes' => [new AlphaDash(), 'Pérez-2'];
        yield 'UUID' => [new Uuid(), '550e8400-e29b-41d4-a716-446655440000'];
    }

    public function testNumericRangeDistinguishesAdjacentIntegersAtThePlatformLimit(): void
    {
        $limit = PHP_INT_MAX - 1;
        $rule = new Range(max: $limit);

        self::assertTrue($rule($limit));
        self::assertFalse($rule(PHP_INT_MAX));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate(PHP_INT_MAX, new Context()));
    }

    public function testNanCannotSatisfyARange(): void
    {
        $rule = new Range(min: 0, max: 10);

        self::assertFalse($rule(NAN));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate(NAN, new Context()));
    }

    public function testFieldComparisonKeepsIntegerPrecision(): void
    {
        $larger = new GreaterThan('limit');
        $smaller = new LessThan('limit');

        self::assertTrue($larger(PHP_INT_MAX, PHP_INT_MAX - 1));
        self::assertTrue($smaller(PHP_INT_MAX - 1, PHP_INT_MAX));
        self::assertTrue($larger->validate(PHP_INT_MAX, Context::validationData(['limit' => PHP_INT_MAX - 1])));
        self::assertTrue($smaller->validate(PHP_INT_MAX - 1, Context::validationData(['limit' => PHP_INT_MAX])));
    }

    public function testDateRangeAcceptsUnixTimestamps(): void
    {
        $rule = new Range(min: '2025-01-01T00:00:00Z', max: 1735776000);

        self::assertTrue($rule->validate(1735689601, new Context()));
        self::assertFalse($rule(1735776001));
    }

    #[DataProvider('invalidBounds')]
    public function testInvalidRangeBoundsAreRejected(string $bound, bool $declarative): void
    {
        $this->expectException(InvalidArgumentException::class);
        if ($declarative) {
            (new RuleFactory())->createRule('max:' . $bound);
        } else {
            new Range(min: '2025-01-01', max: $bound);
        }
    }

    public static function invalidBounds(): iterable
    {
        yield 'invalid declarative bound' => ['invalid-number', true];
        yield 'invalid date bound' => ['invalid-date', false];
    }

    public function testDateFormatReportsInvalidInputContainingANullByte(): void
    {
        $rule = new DateFormat('Y-m-d');
        $value = "2025-01-01\0";

        self::assertTrue($rule('2025-01-01'));
        self::assertFalse($rule($value));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $rule->validate($value, new Context()));
    }

    public function testUnknownFileSizeIsDifferentFromAnEmptyUpload(): void
    {
        $rule = new FileSize(max: 1024);
        $unknown = $this->createStub(UploadedFileInterface::class);
        $unknown->method('getSize')->willReturn(null);
        $empty = $this->createStub(UploadedFileInterface::class);
        $empty->method('getSize')->willReturn(0);

        self::assertTrue($rule($empty));
        self::assertFalse($rule($unknown));
        $errors = $rule->validate($unknown, Context::currentPath('upload'));
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
        self::assertSame(['upload' => 'The file size could not be determined.'], $errors->toArray());
    }
}
