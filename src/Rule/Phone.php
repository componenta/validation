<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Attribute;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * Validates that a value is a valid phone number using libphonenumber-for-php.
 *
 * Supports international phone number formats with optional region validation.
 *
 * **Note:** Requires `componenta/validation-phone` package with `giggsey/libphonenumber-for-php` dependency.
 *
 * Message IDs:
 * - Phone::NOT_STRING_MESSAGE_ID - when the value is not a string.
 * - Phone::INVALID_PHONE_MESSAGE_ID - when the value is not a valid phone number.
 * - Phone::INVALID_REGION_MESSAGE_ID - when the phone number is not from the specified region.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Phone implements RuleInterface
{
    /**
     * Message ID when the value is not a string.
     */
    public const string NOT_STRING_MESSAGE_ID = 'validation.phone.not_string';

    /**
     * Message ID when the value is not a valid phone number.
     */
    public const string INVALID_PHONE_MESSAGE_ID = 'validation.phone.invalid';

    /**
     * Message ID when the phone number is not from the specified region.
     */
    public const string INVALID_REGION_MESSAGE_ID = 'validation.phone.invalid_region';

    /**
     * Rule name (used in context and collectors).
     */
    public string $name {
        get => 'phone';
    }

    /**
     * @param string|null $region ISO 3166-1 alpha-2 country code (e.g., 'US', 'RU', 'GB').
     *                            If null, validates phone number in international format.
     */
    public function __construct(
        private readonly ?string $region = null,
    ) {}

    /**
     * Validates the given value as a phone number.
     *
     * Can be used as a callable: `($rule)($value)`.
     */
    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($value, $this->region);

            if (!$phoneUtil->isValidNumber($phoneNumber)) {
                return false;
            }

            // If region is specified, verify the phone number is from that region
            if ($this->region !== null) {
                return $phoneUtil->getRegionCodeForNumber($phoneNumber) === strtoupper($this->region);
            }

            return true;
        } catch (NumberParseException) {
            return false;
        }
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
                ['type' => get_debug_type($value)]
            ));

            return $collector;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($value, $this->region);

            // Check if phone number is valid
            if (!$phoneUtil->isValidNumber($phoneNumber)) {
                $collector->add($path, new ErrorMessage(
                    $context,
                    self::INVALID_PHONE_MESSAGE_ID,
                    ['region' => $this->region ?? 'international']
                ));

                return $collector;
            }

            // If region is specified, verify the phone number is from that region
            if ($this->region !== null) {
                $numberRegion = $phoneUtil->getRegionCodeForNumber($phoneNumber);
                if ($numberRegion !== strtoupper($this->region)) {
                    $collector->add($path, new ErrorMessage(
                        $context,
                        self::INVALID_REGION_MESSAGE_ID,
                        [
                            'region' => $this->region,
                            'actual_region' => $numberRegion,
                        ]
                    ));

                    return $collector;
                }
            }

            return true;
        } catch (NumberParseException $e) {
            $collector->add($path, new ErrorMessage(
                $context,
                self::INVALID_PHONE_MESSAGE_ID,
                [
                    'region' => $this->region ?? 'international',
                    'error' => $e->getMessage(),
                ]
            ));

            return $collector;
        }
    }

    /**
     * Returns default English message templates for this rule.
     *
     * @return array<string, string> Message ID => Template
     */
    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Phone number must be a string, :type given.',
            self::INVALID_PHONE_MESSAGE_ID => 'Phone number is not valid for :region format.',
            self::INVALID_REGION_MESSAGE_ID => 'Phone number must be from :region region, but :actual_region was detected.',
        ];
    }
}
