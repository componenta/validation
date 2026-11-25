<?php

declare(strict_types=1);

namespace Componenta\Validation\Tests;

use Componenta\Validation\Attribute\Field;
use Componenta\Validation\Attribute\WhenCallback;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use Componenta\Validation\Rule\AllOf;
use Componenta\Validation\Rule\Email;
use Componenta\Validation\Rule\IsInt;
use Componenta\Validation\Rule\Required;

function validatePublishedEmail(mixed $value, ContextInterface $context): bool
{
    $data = $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);

    return ($data['publish'] ?? false) === true;
}

final class NativeWhenCallbackDto
{
    #[Field('summary')]
    #[WhenCallback(static function (mixed $value, ContextInterface $context): bool {
        $data = $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);

        return ($data['publish'] ?? false) === true;
    }, then: 'required')]
    public ?string $note = null;

    public function __construct(
        #[WhenCallback(validatePublishedEmail(...), then: 'required|email')]
        public ?string $email = null,
    ) {}
}

final class NativeWhenRuleCallbackDto
{
    #[WhenCallback(
        validatePublishedEmail(...),
        then: self::validatePublishedValue(...),
        else: static function (mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface {
            return (new IsInt(strict: true))->validate($value, $context);
        },
    )]
    public mixed $value = null;

    private static function validatePublishedValue(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        return (new AllOf(new Required(), new Email()))->validate($value, $context);
    }
}
