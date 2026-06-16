<?php

namespace Componenta\Validation;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Exception\ValidationExceptionInterface;

/**
 * Validator contract.
 *
 * Validates data using configured validation rules.
 * Returns true on success or error collector on failure.
 * Can throw exception on failure if configured via context.
 */
interface ValidatorInterface
{
    /**
     * Validate the given data.
     *
     * @param iterable $data Input data to validate
     * @param ContextInterface|null $context Optional validation context
     * @return true|ErrorMessageCollectorInterface True if valid, error collector otherwise
     * @throws ValidationExceptionInterface When validation fails and THROW_ON_FAILURE_ATTRIBUTE is true
     */
    public function validate(iterable $data, ?ContextInterface $context = null): true|ErrorMessageCollectorInterface;
}
