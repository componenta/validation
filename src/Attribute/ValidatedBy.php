<?php

namespace Componenta\Validation\Attribute;

use Attribute;
use Componenta\Validation\ValidatorInterface;

/**
 * Attribute linking entity class to its validator.
 *
 * Marks a class as validatable and specifies which validator should be used.
 * Used by ContainerValidationProvider in dynamic mode to resolve validators.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class ValidatedBy
{
    /**
     * @param class-string<ValidatorInterface> $validator Validator class name
     */
    public function __construct(
        public string $validator,
    ) {}
}
