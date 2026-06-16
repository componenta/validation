<?php

namespace Componenta\Validation\Provider;

use Componenta\Validation\ValidatorInterface;
use Componenta\Validation\ValidatableInterface;
use Componenta\Validation\Factory\ValidatorFactoryInterface;

/**
 * Validation provider for self-validating objects.
 *
 * Resolves validators from classes implementing ValidatableInterface.
 * Calls static createValidator method on the class to get validator instance.
 */
final readonly class ValidatableProvider implements ValidationProviderInterface
{
    public function __construct(
        private ValidatorFactoryInterface $factory,
    ) {}

    public function provide(string $entryId): ?ValidatorInterface
    {
        if (!is_subclass_of($entryId, ValidatableInterface::class)) {
            return null;
        }

        return $entryId::createValidator($this->factory);
    }
}
