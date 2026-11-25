<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Context;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleInterface;
use Componenta\Validation\Validator;
use Componenta\Validation\ValidatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class NestedValidationTest extends TestCase
{
    #[DataProvider('outerData')]
    public function testNestedValidationKeepsOuterRequiredRules(array $data): void
    {
        $factory = $this->factory();
        $inner = $factory->createFrom(['email' => new Nullable()]);
        $nested = new class($inner) implements RuleInterface {
            public function __construct(private ValidatorInterface $inner) {}
            public string $name { get => 'nested'; }
            public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
            {
                return $this->inner->validate(['email' => null]);
            }
        };
        $outer = $factory->createFrom(['nested' => $nested, 'email' => new Required()]);

        $result = $outer->validate($data);

        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
        self::assertSame(['email'], array_keys($result->toArray()));
        self::assertTrue($outer->validate(['nested' => 'run', 'email' => 'present']));
    }

    public static function outerData(): iterable
    {
        yield 'empty email' => [['nested' => 'run', 'email' => '']];
        yield 'missing email' => [['nested' => 'run']];
    }

    public function testSuspendedValidationsKeepTheirOwnRulesAndProcessedPaths(): void
    {
        $factory = $this->factory();
        $pause = new class implements RuleInterface {
            public string $name { get => 'pause'; }
            public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
            {
                \Fiber::suspend();
                return true;
            }
        };
        $required = $factory->createFrom(['pause' => $pause, 'email' => new Required()]);
        $optional = $factory->createFrom(['email' => new Nullable(), 'pause' => $pause]);
        $first = new \Fiber(static fn () => $required->validate(['pause' => 'first']));
        $second = new \Fiber(static fn () => $optional->validate(['email' => null, 'pause' => 'second']));

        $first->start();
        $second->start();
        $first->resume();
        $second->resume();

        $result = $first->getReturn();
        self::assertInstanceOf(ErrorMessageCollectorInterface::class, $result);
        self::assertSame(['email'], array_keys($result->toArray()));
        self::assertTrue($second->getReturn());
    }

    public function testRuleContextsKeepTheHistoryAvailableAtTheirOwnField(): void
    {
        $rule = new class implements RuleInterface {
            /** @var list<ContextInterface> */
            public array $observed = [];
            public string $name { get => 'observe'; }

            public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
            {
                $this->observed[] = $context;

                return true;
            }
        };
        $initial = new Context([
            ContextInterface::PROCESSED_FIELDS_ATTRIBUTE => ['seed'],
            ContextInterface::PROCESSED_RULES_ATTRIBUTE => ['previous'],
        ]);
        $validator = new Validator(['first' => $rule, 'second' => $rule]);

        self::assertTrue($validator->validate(['first' => 1, 'second' => 2], $initial));

        [$first, $second] = $rule->observed;
        $fieldAttributes = [
            ContextInterface::CURRENT_PATH_ATTRIBUTE,
            ContextInterface::CURRENT_FIELD_ATTRIBUTE,
            ContextInterface::CURRENT_RULE_ATTRIBUTE,
            ContextInterface::VALIDATION_DATA_ATTRIBUTE,
        ];
        self::assertSame($fieldAttributes, array_values(array_intersect(array_keys($first->attributes), $fieldAttributes)));
        self::assertSame('first', $first->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE));
        self::assertSame(['seed'], $first->getAttribute(ContextInterface::PROCESSED_FIELDS_ATTRIBUTE));
        self::assertSame(['previous'], $first->getAttribute(ContextInterface::PROCESSED_RULES_ATTRIBUTE));
        self::assertSame('second', $second->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE));
        self::assertSame(['seed', 'first'], $second->getAttribute(ContextInterface::PROCESSED_FIELDS_ATTRIBUTE));
        self::assertSame(['previous', 'observe'], $second->getAttribute(ContextInterface::PROCESSED_RULES_ATTRIBUTE));
        self::assertSame('first', $second->getAttribute(ContextInterface::LAST_PROCESSED_FIELD_ATTRIBUTE));
        self::assertSame('observe', $second->getAttribute(ContextInterface::LAST_PROCESSED_RULE_ATTRIBUTE));
    }

    private function factory(): ValidatorFactory
    {
        return new ValidatorFactory(new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \LogicException('No external validator service is needed.'); }
            public function has(string $id): bool { return false; }
        });
    }
}
