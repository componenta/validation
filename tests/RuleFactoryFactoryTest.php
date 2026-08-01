<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Factory\RuleFactoryFactory;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\Exists;
use Componenta\Validation\Rule\MimeType;
use Componenta\Validation\Rule\Unique;
use Cycle\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class RuleFactoryFactoryTest extends TestCase
{
    public function testDefersInfrastructureUntilTheRuleNeedsIt(): void
    {
        $database = $this->createMock(DatabaseInterface::class);
        $detector = $this->createMock(MimeTypeDetectorInterface::class);
        $resolved = [];
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $id) use ($database, $detector, &$resolved): object {
                $resolved[] = $id;

                return match ($id) {
                    DatabaseInterface::class => $database,
                    MimeTypeDetectorInterface::class => $detector,
                    default => throw new \LogicException('Unexpected service: ' . $id),
                };
            },
        );

        $factory = (new RuleFactoryFactory())($container);

        self::assertSame([], $resolved);
        self::assertInstanceOf(Email::class, $factory->createRule('email'));
        self::assertSame([], $resolved);

        self::assertInstanceOf(Exists::class, $factory->createRule('exists:products,id'));
        self::assertSame([DatabaseInterface::class], $resolved);
        self::assertInstanceOf(Unique::class, $factory->createRule('unique:products,slug'));
        self::assertSame([DatabaseInterface::class], $resolved);

        self::assertInstanceOf(MimeType::class, $factory->createRule('mime_type:image/png'));
        self::assertSame([DatabaseInterface::class, MimeTypeDetectorInterface::class], $resolved);
    }
}
