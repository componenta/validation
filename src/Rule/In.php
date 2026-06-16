<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * In rule: value must be one of the allowed values.
 *
 * Uses strict comparison by default.
 *
 * Example:
 *   new In(['draft', 'published', 'archived'])
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class In implements RuleInterface
{
    public const string IN_MESSAGE_ID = 'validation.in';

    public string $name {
        get => 'in';
    }

    /**
     * @param array<int, mixed> $allowed Allowed values
     * @param bool $strict Use strict comparison (===)
     */
    public function __construct(
        private readonly array $allowed,
        private readonly bool $strict = true,
    ) {}

    public function __invoke(mixed $value): bool
    {
        return in_array($value, $this->allowed, $this->strict);
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::IN_MESSAGE_ID, [
            'values' => implode(', ', array_map($this->stringify(...), $this->allowed)),
        ]));

        return $collector;
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return gettype($value);
    }

    public static function getMessages(): array
    {
        return [
            self::IN_MESSAGE_ID => 'The value must be one of: :values.',
        ];
    }
}
