<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Password strength rule with optional confirmation.
 *
 * Uses a bitmask to configure required character classes.
 *
 * Bitmask flags:
 *  - self::REQUIRE_UPPER
 *  - self::REQUIRE_LOWER
 *  - self::REQUIRE_DIGIT
 *  - self::REQUIRE_SPECIAL
 *
 * Example:
 *   new Password(min: 8, flags: Password::REQUIRE_UPPER | Password::REQUIRE_LOWER);
 *   new Password(min: 8, confirmationField: 'password_confirmation');
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Password implements RuleInterface
{
    public const int REQUIRE_UPPER   = 1 << 0; // A-Z
    public const int REQUIRE_LOWER   = 1 << 1; // a-z
    public const int REQUIRE_DIGIT   = 1 << 2; // 0-9
    public const int REQUIRE_SPECIAL = 1 << 3; // symbols

    public const string NOT_STRING_MESSAGE_ID = 'validation.password.not_string';
    public const string TOO_SHORT_MESSAGE_ID = 'validation.password.too_short';
    public const string MISSING_UPPER_MESSAGE_ID = 'validation.password.missing_upper';
    public const string MISSING_LOWER_MESSAGE_ID = 'validation.password.missing_lower';
    public const string MISSING_DIGIT_MESSAGE_ID = 'validation.password.missing_digit';
    public const string MISSING_SPECIAL_MESSAGE_ID = 'validation.password.missing_special';
    public const string NOT_CONFIRMED_MESSAGE_ID = 'validation.password.not_confirmed';

    public function __construct(
        private int $min = 8,
        private int $flags = self::REQUIRE_UPPER | self::REQUIRE_LOWER | self::REQUIRE_DIGIT | self::REQUIRE_SPECIAL,
        private ?string $confirmationField = null,
    ) {}

    public string $name {
        get => 'password';
    }

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (mb_strlen($value) < $this->min) {
            return false;
        }
        if (($this->flags & self::REQUIRE_UPPER) && !preg_match('/[A-Z]/', $value)) {
            return false;
        }
        if (($this->flags & self::REQUIRE_LOWER) && !preg_match('/[a-z]/', $value)) {
            return false;
        }
        if (($this->flags & self::REQUIRE_DIGIT) && !preg_match('/\d/', $value)) {
            return false;
        }
        if (($this->flags & self::REQUIRE_SPECIAL) && !preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, $this->name);

        if (!is_string($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_STRING_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        if (mb_strlen($value) < $this->min) {
            $collector->add($path, new ErrorMessage($context, self::TOO_SHORT_MESSAGE_ID, [
                'min' => $this->min,
            ]));
        }

        if (($this->flags & self::REQUIRE_UPPER) && !preg_match('/[A-Z]/', $value)) {
            $collector->add($path, new ErrorMessage($context, self::MISSING_UPPER_MESSAGE_ID));
        }

        if (($this->flags & self::REQUIRE_LOWER) && !preg_match('/[a-z]/', $value)) {
            $collector->add($path, new ErrorMessage($context, self::MISSING_LOWER_MESSAGE_ID));
        }

        if (($this->flags & self::REQUIRE_DIGIT) && !preg_match('/\\d/', $value)) {
            $collector->add($path, new ErrorMessage($context, self::MISSING_DIGIT_MESSAGE_ID));
        }

        if (($this->flags & self::REQUIRE_SPECIAL) && !preg_match('/[^A-Za-z0-9]/', $value)) {
            $collector->add($path, new ErrorMessage($context, self::MISSING_SPECIAL_MESSAGE_ID));
        }

        if ($this->confirmationField !== null) {
            $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);
            $confirmation = $data[$this->confirmationField] ?? null;

            if ($value !== $confirmation) {
                $collector->add($path, new ErrorMessage($context, self::NOT_CONFIRMED_MESSAGE_ID, [
                    'confirmation' => $this->confirmationField,
                ]));
            }
        }

        return $collector->isEmpty() ? true : $collector;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Password must be a string, :type given.',
            self::TOO_SHORT_MESSAGE_ID => 'Password must be at least :min characters long.',
            self::MISSING_UPPER_MESSAGE_ID => 'Password must contain at least one uppercase letter.',
            self::MISSING_LOWER_MESSAGE_ID => 'Password must contain at least one lowercase letter.',
            self::MISSING_DIGIT_MESSAGE_ID => 'Password must contain at least one digit.',
            self::MISSING_SPECIAL_MESSAGE_ID => 'Password must contain at least one special character.',
            self::NOT_CONFIRMED_MESSAGE_ID => 'Password confirmation does not match.',
        ];
    }
}
