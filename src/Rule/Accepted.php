<?php

namespace Componenta\Validation\Rule;

use Attribute;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Accepted rule: value must be accepted.
 *
 * Accepted values:
 * - true
 * - 1
 * - "1"
 * - "true"
 * - "on"
 * - "yes"
 *
 * Example:
 *   #[Accepted]
 *   public bool $terms;
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Accepted implements RuleInterface
{
    public const string NOT_ACCEPTED_MESSAGE_ID = 'validation.accepted.not_accepted';

    public string $name {
        get => 'accepted';
    }

    public function __construct() {}

    /**
     * Quick check.
     */
    public function __invoke(mixed $value): bool
    {
        return $this->isAccepted($value);
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this->isAccepted($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        $collector->add(
            $path,
            new ErrorMessage($context, self::NOT_ACCEPTED_MESSAGE_ID)
        );

        return $collector;
    }

    private function isAccepted(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value === true;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower($value),
                ['1', 'true', 'on', 'yes'],
                true
            );
        }

        return false;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_ACCEPTED_MESSAGE_ID => 'The value must be accepted.',
        ];
    }
}