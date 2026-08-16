<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Rule\RuleFactory;
use Cycle\Database\DatabaseInterface;
use Psr\Container\ContainerInterface;

/** Creates a RuleFactory while keeping database and MIME integrations optional. */
final readonly class RuleFactoryFactory
{
    public function __invoke(ContainerInterface $container): RuleFactory
    {
        $database = $container->has(DatabaseInterface::class)
            ? $container->get(DatabaseInterface::class)
            : null;
        $detector = $container->has(MimeTypeDetectorInterface::class)
            ? $container->get(MimeTypeDetectorInterface::class)
            : null;

        if ($database !== null && !$database instanceof DatabaseInterface) {
            throw new \InvalidArgumentException(sprintf(
                '%s service must implement %s.',
                DatabaseInterface::class,
                DatabaseInterface::class,
            ));
        }

        if ($detector !== null && !$detector instanceof MimeTypeDetectorInterface) {
            throw new \InvalidArgumentException(sprintf(
                '%s service must implement %s.',
                MimeTypeDetectorInterface::class,
                MimeTypeDetectorInterface::class,
            ));
        }

        return new RuleFactory($database, $detector);
    }
}
