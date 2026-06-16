<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Validates that a value is a valid email address.
 *
 * Uses PHP's `filter_var` for practical RFC-compliant validation.
 * Suitable for common real-world email addresses.
 *
 * Message IDs:
 * - Email::NOT_STRING_MESSAGE_ID - when the value is not a string.
 * - Email::INVALID_EMAIL_MESSAGE_ID - when the value is not a valid email.
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Email implements RuleInterface
{
    /**
     * Message ID when the value is not a string.
     */
    public const string NOT_STRING_MESSAGE_ID = 'validation.email.not_string';

    /**
     * Message ID when the value is not a valid email.
     */
    public const string INVALID_EMAIL_MESSAGE_ID = 'validation.email.invalid';

    /**
     * Rule name (used in context and collectors).
     */
    public string $name {
        get => 'email';
    }

    /**
     * Validates the given value as an email.
     *
     * Can be used as a callable: `($rule)($value)`.
     */
    public function __invoke(mixed $value): bool
    {
        return is_string($value) && $this->check($value);
    }

    /**
     * Validates the value within a context.
     *
     * Returns `true` if valid, otherwise an ErrorMessageCollectorInterface
     * containing validation errors.
     *
     * @param mixed $value The value to validate
     * @param ContextInterface $context Validation context
     *
     * @return true|ErrorMessageCollectorInterface
     */
    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, $this->name);

        // Check that value is a string
        if (!is_string($value)) {
            $collector->add($path, new ErrorMessage(
                $context,
                self::NOT_STRING_MESSAGE_ID,
                ['type' => get_debug_type($value)],
            ));

            return $collector;
        }

        // Check email format
        if (!$this->check($value)) {
            $collector->add($path, new ErrorMessage(
                $context,
                self::INVALID_EMAIL_MESSAGE_ID,
            ));

            return $collector;
        }

        return true;
    }

    /**
     * Checks if the value is a valid email using PHP's filter_var.
     */
    private function check(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Returns default English message templates for this rule.
     *
     * @return array<string, string> Message ID => Template
     */
    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Email must be a string, :type given.',
            self::INVALID_EMAIL_MESSAGE_ID => 'Email address is not valid.',
        ];
    }
}
