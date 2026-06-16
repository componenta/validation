<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Closure;
use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * Conditional validation with two branches based on field value or callback.
 *
 * Usage:
 *   new When('status:published', new Required(), new Nullable())
 *   new When(fn($v, $ctx) => ..., new AllOf(new Required(), new IsString()), new Nullable())
 *
 * String condition format: "field:value"
 *   - field: name of another field in the validation data
 *   - value: expected value (auto-cast: "true"->bool, "null"->null, numeric->int/float)
 *   - If no colon, field presence is checked (equivalent to "field:true")
 */
final class When implements RuleInterface
{
    public string $name {
        get => 'when';
    }

    /** @var callable(mixed, ContextInterface): bool */
    private $callback;

    /**
     * @param string|callable(mixed, ContextInterface): bool $condition "field:value" or callable
     * @param RuleInterface $then Rule to apply when condition is true
     * @param RuleInterface $else Rule to apply when condition is false
     */
    public function __construct(
        string|callable $condition,
        private readonly RuleInterface $then,
        private readonly RuleInterface $else = new Nullable(),
    ) {
        if (!is_callable($condition)) {
            if (!str_contains($condition, ':')) {
                throw new \InvalidArgumentException('When condition must be in "field:value" format, got: "' . $condition . '"');
            }

            [$field, $expected] = explode(':', $condition, 2);

            $condition = static function (mixed $value, ContextInterface $context) use ($field, $expected): bool {
                $data = (array) $context->getAttribute(ContextInterface::VALIDATION_DATA_ATTRIBUTE, []);

                return ($data[$field] ?? null) === $expected;
            };
        }

        $this->callback = $condition;
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if (($this->callback)($value, $context)) {
            return $this->then->validate($value, $context);
        }

        return $this->else->validate($value, $context);
    }
}
