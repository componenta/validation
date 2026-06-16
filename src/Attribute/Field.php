<?php

declare(strict_types=1);

namespace Componenta\Validation\Attribute;

use Attribute;

/**
 * Attribute to override field name used in validation rules array.
 *
 * When present, this attribute takes priority over the `as` parameter in Validate attribute.
 * Allows mapping property name to a different validation field name.
 *
 * Example:
 * ```php
 * #[Field('user_email')]
 * #[Validate('required|email')]
 * public string $email;
 *
 * // Creates: ['user_email' => AllOf(Required, Email)]
 * // Instead of: ['email' => AllOf(Required, Email)]
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
readonly class Field
{
    /**
     * @param string $name Custom field name for validation rules mapping
     */
    public function __construct(
        public string $name,
    ) {}
}
