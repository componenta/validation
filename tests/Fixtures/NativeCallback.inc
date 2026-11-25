<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Attribute\Callback;
use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\Validate;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\Length;
use Componenta\Validation\Rule\Regex;

final class NativeCallbackDto
{
    #[Field('invite_code')]
    #[Validate('required')]
    #[Callback(self::validateLength(...))]
    #[Callback(static function (mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface {
        return (new Regex('/\A[A-Z0-9]+\z/'))->validate($value, $context);
    })]
    public ?string $inviteCode = null;

    private static function validateLength(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        return (new Length(min: 6, max: 6))->validate($value, $context);
    }
}
