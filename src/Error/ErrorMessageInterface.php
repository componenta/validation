<?php

namespace Componenta\Validation\Error;

use Componenta\Validation\ContextInterface;

/**
 * Represents a single validation error.
 *
 * Implementations must be immutable and contain all information required
 * to format a user-friendly message later.
 *
 * How the message is formatted is an implementation detail (for example,
 * via a MessageFormatter), not a contract requirement.
 */
interface ErrorMessageInterface extends \Stringable
{
    /**
     * Returns the formatted error message.
     */
    public function toString(): string;

    /**
     * Context in which the error occurred.
     *
     * Provides locale, formatter, current field, rule, path and any custom attributes.
     */
    public ContextInterface $context { get; }
}