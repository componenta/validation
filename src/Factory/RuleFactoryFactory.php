<?php

declare(strict_types=1);

namespace Componenta\Validation\Factory;

use Componenta\Detector\MimeTypeDetectorInterface;
use Componenta\Validation\Rule\RuleFactory;
use Componenta\Validation\Rule\RuleFactoryInterface;
use Cycle\Database\DatabaseInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating rule factory.
 *
 * Creates RuleFactory instance with database and detector dependencies.
 */
final readonly class RuleFactoryFactory
{
    /**
     * Create rule factory instance.
     *
     * @param ContainerInterface $container DI container
     * @return RuleFactoryInterface Rule factory instance
     */
    public function __invoke(ContainerInterface $container): RuleFactory
    {
        return new RuleFactory(
            $container->get(DatabaseInterface::class),
            $container->get(MimeTypeDetectorInterface::class),
        );
    }

}
