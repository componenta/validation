<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Uuid rule: value must be a valid UUID.
 *
 * Supports UUID versions 1-5 and nil UUID.
 * RFC 4122 compliant.
 *
 * Example:
 *   new Uuid() // any version
 *   new Uuid(4) // only v4
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Uuid implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.uuid.not_string';
    public const string INVALID_UUID_MESSAGE_ID = 'validation.uuid.invalid';
    public const string INVALID_VERSION_MESSAGE_ID = 'validation.uuid.invalid_version';

    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';

    public string $name {
        get => 'uuid';
    }

    /**
     * @param int|null $version Required UUID version (1-5), null for any
     */
    public function __construct(
        private readonly ?int $version = null,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (!preg_match(self::PATTERN, $value)) {
            return false;
        }
        if ($this->version !== null && strtolower($value) !== self::NIL_UUID) {
            $actualVersion = (int) $value[14];
            if ($actualVersion !== $this->version) {
                return false;
            }
        }
        return true;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');

        if (!is_string($value)) {
            $collector->add($path, new ErrorMessage($context, self::NOT_STRING_MESSAGE_ID, [
                'type' => get_debug_type($value),
            ]));
            return $collector;
        }

        if (!preg_match(self::PATTERN, $value)) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_UUID_MESSAGE_ID));
            return $collector;
        }

        if ($this->version !== null && strtolower($value) !== self::NIL_UUID) {
            $actualVersion = (int) $value[14]; // Version is at position 14
            if ($actualVersion !== $this->version) {
                $collector->add($path, new ErrorMessage($context, self::INVALID_VERSION_MESSAGE_ID, [
                    'expected' => $this->version,
                    'actual' => $actualVersion,
                ]));
                return $collector;
            }
        }

        return true;
    }

    public static function getMessages(): array
    {
        return [
            self::NOT_STRING_MESSAGE_ID => 'Value must be a string, :type given.',
            self::INVALID_UUID_MESSAGE_ID => 'The value is not a valid UUID.',
            self::INVALID_VERSION_MESSAGE_ID => 'Expected UUID version :expected, got version :actual.',
        ];
    }
}
