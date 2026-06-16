<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Url rule: value must be a valid URL.
 *
 * Uses PHP's filter_var with FILTER_VALIDATE_URL.
 * Optionally restricts to specific protocols.
 *
 * Example:
 *   new Url() // any valid URL
 *   new Url(['https']) // only HTTPS
 *   new Url(['http', 'https']) // HTTP or HTTPS
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Url implements RuleInterface
{
    public const string NOT_STRING_MESSAGE_ID = 'validation.url.not_string';
    public const string INVALID_URL_MESSAGE_ID = 'validation.url.invalid';
    public const string INVALID_PROTOCOL_MESSAGE_ID = 'validation.url.invalid_protocol';

    public string $name {
        get => 'url';
    }

    /**
     * @param array<int, string>|null $protocols Allowed protocols (null = any)
     */
    public function __construct(
        private readonly ?array $protocols = null,
    ) {}

    public function __invoke(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        if ($this->protocols !== null) {
            $scheme = parse_url($value, PHP_URL_SCHEME);
            if ($scheme === null || !in_array(strtolower($scheme), array_map('strtolower', $this->protocols), true)) {
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

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $collector->add($path, new ErrorMessage($context, self::INVALID_URL_MESSAGE_ID));
            return $collector;
        }

        if ($this->protocols !== null) {
            $scheme = parse_url($value, PHP_URL_SCHEME);
            if ($scheme === null || !in_array(strtolower($scheme), array_map('strtolower', $this->protocols), true)) {
                $collector->add($path, new ErrorMessage($context, self::INVALID_PROTOCOL_MESSAGE_ID, [
                    'protocols' => implode(', ', $this->protocols),
                    'actual' => $scheme ?? 'none',
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
            self::INVALID_URL_MESSAGE_ID => 'The URL is not valid.',
            self::INVALID_PROTOCOL_MESSAGE_ID => 'The URL must use one of these protocols: :protocols.',
        ];
    }
}
