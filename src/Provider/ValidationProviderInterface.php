<?php

namespace Componenta\Validation\Provider;

use Componenta\Validation\ValidatorInterface;

/**
 * Validation provider interface.
 *
 * Creates validator instances for given identifiers (typically class names).
 * Implementations may use different strategies like attribute scanning or configuration.
 */
interface ValidationProviderInterface
{
    /**
     * Create validator for given identifier.
     *
     * @param string $entryId Identifier (typically fully qualified class name)
     * @return ValidatorInterface|null Validator instance or null if not found
     */
    public function provide(string $entryId): ?ValidatorInterface;
}
