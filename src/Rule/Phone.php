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

/** Validates phone numbers through the package's declared libphonenumber dependency. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Phone implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.phone.not_string';
    public const string INVALID_PHONE_MESSAGE_ID = 'validation.phone.invalid';
    public const string INVALID_REGION_MESSAGE_ID = 'validation.phone.invalid_region';

    public string $name {
        get => 'phone';
    }

    /** @param string|null $region ISO 3166-1 alpha-2 region code. */
    public function __construct(private readonly ?string $region = null) {}

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

            return $this->region === null
                || $phoneUtil->getRegionCodeForNumber($phoneNumber) === strtoupper($this->region);
        } catch (NumberParseException) {
            return false;
        }
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, $this->name);
        $collector = new ErrorMessageCollector();

        if (!is_string($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_STRING_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));

            return $collector;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($value, $this->region);

            if (!$phoneUtil->isValidNumber($phoneNumber)) {
                $collector->add($path, new ErrorMessage($context, self::INVALID_PHONE_MESSAGE_ID, [
                    'region' => $this->region ?? 'international',
                ]));

                return $collector;
            }

            if ($this->region !== null) {
                $actualRegion = $phoneUtil->getRegionCodeForNumber($phoneNumber);
                if ($actualRegion !== strtoupper($this->region)) {
                    $collector->add($path, new ErrorMessage($context, self::INVALID_REGION_MESSAGE_ID, [
                        'region' => $this->region,
                        'actual_region' => $actualRegion,
                    ]));

                    return $collector;
                }
            }

            return true;
        } catch (NumberParseException $exception) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_PHONE_MESSAGE_ID, [
                'region' => $this->region ?? 'international',
                'error' => $exception->getMessage(),
            ]));

            return $collector;
        }
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Phone number must be a string, :type given.',
            self::INVALID_PHONE_MESSAGE_ID => 'Phone number is not valid for :region format.',
            self::INVALID_REGION_MESSAGE_ID => 'Phone number must be from :region region, but :actual_region was detected.',
        ];
    }
}
