<?php

namespace Componenta\Validation;

use Componenta\Validation\Factory\ValidatorFactoryInterface;

/**
 * Interface for objects that can create their own validator.
 *
 * Allows DTOs and commands to define validation rules programmatically.
 */
interface ValidatableInterface
{
    /**
     * Create validator instance with validation rules for this object.
     *
     */
    public static function createValidator(ValidatorFactoryInterface $factory): ValidatorInterface;
}
