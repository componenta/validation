<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;

/**
 * Validation attribute for defining rules using string syntax.
 *
 * Allows specifying validation rules as pipe-separated string.
 * Rules are parsed and instantiated by RuleFactory.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
readonly class Validate
{
    /**
     * @param string $rules Pipe-separated validation rules
     * @param string|null $as Custom field name for validation (overrides property name in rules array)
     */
    public function __construct(
        public string $rules,
        public ?string $as = null,
    ) {}
}
