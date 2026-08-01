<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Rule\RuleFactory;
use Cycle\Database\DatabaseInterface;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Factory for creating rule factory.
 *
 * Infrastructure dependencies remain lazy because most validation rules are
 * pure and do not need a database connection or a MIME detector.
 */
final readonly class RuleFactoryFactory
{
    public function __invoke(ContainerInterface $container): RuleFactory
    {
        return new RuleFactory(
            static fn(): DatabaseInterface => self::database($container),
            static fn(): MimeTypeDetectorInterface => self::detector($container),
        );
    }

    private static function database(ContainerInterface $container): DatabaseInterface
    {
        $database = $container->get(DatabaseInterface::class);

        if (!$database instanceof DatabaseInterface) {
            throw new UnexpectedValueException(sprintf(
                'Container entry "%s" must implement %s; got %s.',
                DatabaseInterface::class,
                DatabaseInterface::class,
                get_debug_type($database),
            ));
        }

        return $database;
    }

    private static function detector(ContainerInterface $container): MimeTypeDetectorInterface
    {
        $detector = $container->get(MimeTypeDetectorInterface::class);

        if (!$detector instanceof MimeTypeDetectorInterface) {
            throw new UnexpectedValueException(sprintf(
                'Container entry "%s" must implement %s; got %s.',
                MimeTypeDetectorInterface::class,
                MimeTypeDetectorInterface::class,
                get_debug_type($detector),
            ));
        }

        return $detector;
    }
}
