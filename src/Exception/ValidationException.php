<?php

namespace Componenta\Validation\Exception;

use Componenta\Validation\Error\ErrorMessageCollectorInterface;

class ValidationException extends \RuntimeException implements ValidationExceptionInterface
{
    public function __construct(
        public readonly ErrorMessageCollectorInterface $errors,
        string $message = 'Validation failed',
    ) {
        $fields = [];
        foreach ($errors as $field => $error) {
            $fields[] = $field;
        }

        if ($fields !== []) {
            $message .= ': ' . implode(', ', $fields);
        }

        parent::__construct($message);
    }
}
