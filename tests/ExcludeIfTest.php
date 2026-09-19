<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\ExcludeIf;
use Componenta\Validation\Rule\OneOf;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Rule\Sequential;
use Componenta\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExcludeIfTest extends TestCase
{
    #[DataProvider('declarations')]
    public function testExcludesTheFieldRegardlessOfRulePosition(RuleInterface $rule): void
    {
        $validator = new Validator(['title' => $rule]);

        self::assertTrue($validator->validate(['is_draft' => true], Context::stopOnFirstFailure()));
        foreach ([false, 'true'] as $inactive) {
            $errors = $validator->validate(['is_draft' => $inactive]);
            self::assertInstanceOf(ErrorMessageCollectorInterface::class, $errors);
            self::assertSame(['title'], array_keys($errors->toArray()));
            self::assertSame('validation.required', $errors->get('title')->messageId);
        }
        self::assertTrue($validator->validate(['is_draft' => true]));
        self::assertTrue($validator->validate(['is_draft' => false, 'title' => 'Present']));
    }

    public static function declarations(): iterable
    {
        yield 'guard first' => [new AllOf(new ExcludeIf('is_draft', true), new Required())];
        yield 'guard last' => [new AllOf(new Required(), new ExcludeIf('is_draft', true))];
        yield 'nested guard' => [new AllOf(new Required(), new AllOf(new ExcludeIf('is_draft', true)))];
        yield 'string guard first' => [(new RuleFactory())->createRule('exclude_if:is_draft,true|required')];
        yield 'string guard last' => [(new RuleFactory())->createRule('required|exclude_if:is_draft,true')];
        yield 'one of guard first' => [new OneOf(new ExcludeIf('is_draft', true), new Required())];
        yield 'one of guard last' => [new OneOf(new Required(), new ExcludeIf('is_draft', true))];
        yield 'string one of guard first' => [(new RuleFactory())->createRule('oneof:exclude_if:is_draft,true|required')];
        yield 'string one of guard last' => [(new RuleFactory())->createRule('oneof:required|exclude_if:is_draft,true')];
        yield 'sequential guard first' => [new Sequential(new ExcludeIf('is_draft', true), new Required())];
        yield 'sequential guard last' => [new Sequential(new Required(), new ExcludeIf('is_draft', true))];
    }

    #[DataProvider('composites')]
    public function testExcludedRulesAreNotInvokedAndOtherExceptionsAreUnchanged(string $composite): void
    {
        $failure = new \RuntimeException('The ordinary rule failed.');
        $constraint = new class ($failure) implements RuleInterface {
            public string $name = 'throwing';
            public int $calls = 0;

            public function __construct(private \RuntimeException $failure) {}

            public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
            {
                ++$this->calls;
                throw $this->failure;
            }
        };
        $validator = new Validator(['title' => new $composite($constraint, new ExcludeIf('is_draft', true))]);

        self::assertTrue($validator->validate(['is_draft' => true]));
        self::assertSame(0, $constraint->calls);
        try {
            $validator->validate(['is_draft' => false]);
            self::fail('The exception from an active rule must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(1, $constraint->calls);
        self::assertTrue($validator->validate(['is_draft' => true]));
        self::assertSame(1, $constraint->calls);
    }

    public static function composites(): iterable
    {
        yield 'all of' => [AllOf::class];
        yield 'one of' => [OneOf::class];
        yield 'sequential' => [Sequential::class];
    }
}
