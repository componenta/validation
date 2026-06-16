<?php

declare(strict_types=1);

namespace Componenta\Validation\Rule;

use Componenta\Validation\ContextInterface;
use Componenta\Validation\Error\ErrorMessageCollectorInterface;

/**
 * IfElse: conditional validation with two branches.
 *
 * If condition returns true, apply ifTrue rule.
 * Otherwise, apply ifFalse rule.
 *
 * Example:
 *   new IfElse(
 *       fn($value, $context) => $value['type'] === 'email',
 *       new Email(),
 *       new Phone()
 *   )
 */
final class IfElse implements RuleInterface
{
    private(set) string $name;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param callable(mixed, ContextInterface): bool $callback Condition callback
     * @param RuleInterface $ifTrue Rule to apply if condition is true
     * @param RuleInterface $ifFalse Rule to apply if condition is false
     * @param string|null $name Optional custom name
     */
    public function __construct(
        callable $callback,
        private readonly RuleInterface $ifTrue,
        private readonly RuleInterface $ifFalse,
        ?string $name = null
    ) {
        $this->callback = $callback;
        $this->name = $name ?? sprintf(
            'if_else(%s,%s)',
            $this->ifTrue->name,
            $this->ifFalse->name
        );
    }

    public function validate(mixed $value, ContextInterface $context): true|ErrorMessageCollectorInterface
    {
        if (($this->callback)($value, $context)) {
            return $this->ifTrue->validate($value, $context);
        }

        return $this->ifFalse->validate($value, $context);
    }
}
