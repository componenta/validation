<?php

namespace Componenta\Validation\Exception;

/**
 * Marker interface for validation exceptions.
 *
 * Thrown when validation fails and THROW_ON_FAILURE_ATTRIBUTE is enabled.
 */
interface ValidationExceptionInterface extends \Throwable
{
}