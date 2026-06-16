<?php

namespace Componenta\Validation\Rule;
use Attribute;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessage;
use Componenta\Validation\Error\ErrorMessageCollector;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * NotIn rule: value must not be one of the forbidden values.
 *
 * Uses strict comparison by default.
 *
 * Example:
 *   new NotIn(['admin', 'root', 'system'])
 */

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class NotIn implements RuleInterface
{
    public const string NOT_IN_MESSAGE_ID = 'validation.not_in';

    public string $name {
        get => 'not_in';
    }

    /**
     * @param array<int, mixed> $forbidden Forbidden values
     * @param bool $strict Use strict comparison (===)
     */
    public function __construct(
        private readonly array $forbidden,
        private readonly bool $strict = true,
    ) {}

    public function __invoke(mixed $value): bool
    {
        return !in_array($value, $this->forbidden, $this->strict);
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if ($this($value)) {
            return true;
        }

        $collector = new ErrorMessageCollector();
        $path = (string) $context->getAttribute(ContextInterface::CURRENT_PATH_ATTRIBUTE, '');
        $collector->add($path, new ErrorMessage($context, self::NOT_IN_MESSAGE_ID, [
            'values' => implode(', ', array_map($this->stringify(...), $this->forbidden)),
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
            self::NOT_IN_MESSAGE_ID => 'The value must not be one of: :values.',
        ];
    }
}
