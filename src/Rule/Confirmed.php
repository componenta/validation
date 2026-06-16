<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Confirms that the field value matches another field (usually *_confirmation).
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Confirmed implements RuleInterface
{
    public const string NOT_MATCH_MESSAGE_ID = 'validation.confirmed.not_match';

    public function __construct(
        private readonly string $confirmationField = 'confirmation'
    ) {}

    public string $name {
        get => 'confirmed';
    }

    /**
     * Quick check if two values match.
     */
    public function __invoke(mixed $value, mixed $confirmation = null): bool
    {
        return $value === $confirmation;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        $confirmedValue = $data[$this->confirmationField] ?? null;

        if ($this($value, $confirmedValue)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $collector->add($path, new ErrorMessage($context, self::NOT_MATCH_MESSAGE_ID, [
            'confirmation' => $this->confirmationField,
        ]));
        return $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_MATCH_MESSAGE_ID => 'The value does not match :confirmation.',
        ];
    }
}
