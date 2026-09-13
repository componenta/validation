<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Rule\Nullable;
use Componenta\Validation\Rule\Required;
use Componenta\Validation\Rule\RuleInterface;
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

    private function factory(): ValidatorFactory
    {
        return new ValidatorFactory(new class implements ContainerInterface {
            public function get(string $id): mixed { throw new \LogicException('No external validator service is needed.'); }
            public function has(string $id): bool { return false; }
        });
    }
}
