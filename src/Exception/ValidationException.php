<?php

namespace Componenta\Validation\Exception;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;

class ValidationException extends \RuntimeException implements ValidationExceptionInterface
{
    public function __construct(
        public readonly ErrorMessageCollectorInterface $errors,
        string $message = 'Validation failed',
    ) {
        $fields = array_keys($errors->toArray());

        if ($fields !== []) {
            $message .= ': ' . implode(', ', $fields);
        }

        parent::__construct($message);
    }
}