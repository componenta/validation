<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Factory\ValidatorFactory;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ValidatorFactoryTest extends TestCase
{
    public function testCreateFromBuildsValidatorFromStringRules(): void
    {
        $factory = new ValidatorFactory(new EmptyContainer());

        $validator = $factory->createFrom([
            'email' => 'required|email',
        ]);

        self::assertInstanceOf(Validator::class, $validator);
        self::assertTrue($validator->validate(['email' => 'user@example.com']));
        self::assertInstanceOf(
            ErrorMessageCollectorInterface::class,
            $validator->validate(['email' => 'bad']),
        );
    }

    public function testCreateResolvesValidatorFromContainer(): void
    {
        $validator = new Validator(['email' => (new RuleFactory())->createRule('email')]);
        $factory = new ValidatorFactory(new class ($validator) implements ContainerInterface {
            public function __construct(private readonly Validator $validator) {}

            public function get(string $id): mixed
            {
                return $this->validator;
            }

            public function has(string $id): bool
            {
                return true;
            }
        });

        self::assertSame($validator, $factory->create('validator.email'));
    }
}

final class EmptyContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        throw new \RuntimeException(sprintf('Unknown service "%s"', $id));
    }

    public function has(string $id): bool
    {
        return false;
    }
}
